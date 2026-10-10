<?php
session_start();
$configPath = __DIR__ . '/config.php';
$config = file_exists($configPath) ? require $configPath : require __DIR__ . '/config.example.php';
date_default_timezone_set($config['timezone'] ?? 'Asia/Dhaka');
$demo = (bool)($config['demo_mode'] ?? true);

if ($demo) {
    http_response_code(403);
    echo 'Data quality checks disabled in demo mode.';
    exit;
}

$db = $config['db'] ?? [];
if (!isset($db['host'], $db['name'], $db['user'], $db['pass'])) {
    http_response_code(500);
    echo 'Database not configured.';
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host={$db['host']};dbname={$db['name']};charset={$db['charset']}",
        $db['user'], $db['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    // Get all imported records with source info
    $stmt = $pdo->query("
        SELECT ir.*, ds.name AS source_name, ds.department_id, d.name AS department_name
        FROM imported_records ir
        LEFT JOIN data_sources ds ON ir.source_id = ds.id
        LEFT JOIN departments d ON ds.department_id = d.id
    ");
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($records)) {
        http_response_code(404);
        echo 'No records found. Import CSV data first.';
        exit;
    }

    $issues = [];
    $totalRecords = count($records);

    // 1. Check for duplicate IMEIs
    $imeiCounts = [];
    foreach ($records as $rec) {
        $imei = trim($rec['record_key']);
        if (!empty($imei)) {
            if (!isset($imeiCounts[$imei])) {
                $imeiCounts[$imei] = 0;
            }
            $imeiCounts[$imei]++;
        }
    }

    $duplicateImeis = [];
    foreach ($imeiCounts as $imei => $count) {
        if ($count > 1) {
            $duplicateImeis[] = [
                'imei' => $imei,
                'count' => $count,
                'occurrences' => array_filter($records, fn($r) => trim($r['record_key']) === $imei)
            ];
        }
    }

    if (!empty($duplicateImeis)) {
        $issues[] = [
            'severity' => 'High',
            'title' => 'Duplicate IMEI identifiers',
            'description' => count($duplicateImeis) . ' IMEIs appear more than once in the data set.',
            'category' => 'deduplication',
            'affected_count' => array_sum(array_map(fn($d) => $d['count'] - 1, $duplicateImeis))
        ];
    }

    // 2. Check for missing/invalid identifiers
    $missingIdentifiers = 0;
    foreach ($records as $rec) {
        $imei = trim($rec['record_key']);
        $serial = trim($rec['payload'] ?? '');
        // Check if payload has serial number
        if (empty($imei)) {
            $missingIdentifiers++;
        }
    }

    if ($missingIdentifiers > 0) {
        $issues[] = [
            'severity' => 'High',
            'title' => 'Missing identifiers',
            'description' => "$missingIdentifiers records are missing IMEI/Serial Number.",
            'category' => 'missing_data',
            'affected_count' => $missingIdentifiers
        ];
    }

    // 3. Check for unmapped IMEIs (no invoice reference)
    $unmapped = 0;
    foreach ($records as $rec) {
        $payload = json_decode($rec['payload'] ?? '{}', true);
        if ($payload && isset($payload['invoice_number']) && empty($payload['invoice_number'])) {
            $unmapped++;
        }
    }

    if ($unmapped > 0) {
        $issues[] = [
            'severity' => 'High',
            'title' => 'Unmatched records',
            'description' => "$unmapped device records do not have a verified sales or transfer invoice.",
            'category' => 'reconciliation',
            'affected_count' => $unmapped
        ];
    }

    // 4. Check source freshness
    $staleSources = 0;
    $stmtSources = $pdo->query("SELECT id, name, last_synced_at, status FROM data_sources");
    $sources = $stmtSources->fetchAll(PDO::FETCH_ASSOC);
    foreach ($sources as $src) {
        if ($src['last_synced_at']) {
            $lastSynced = new DateTime($src['last_synced_at']);
            $now = new DateTime();
            $interval = $now->diff($lastSynced);
            $hours = $interval->h + ($interval->days * 24);
            if ($hours > 60) {
                $staleSources++;
            }
        }
    }

    if ($staleSources > 0) {
        $issues[] = [
            'severity' => 'Medium',
            'title' => 'Stale data sources',
            'description' => "$staleSources source" . ($staleSources > 1 ? 's' : '') . ' have not been refreshed in over 60 minutes.',
            'category' => 'freshness',
            'affected_count' => $staleSources
        ];
    }

    // 5. Check for incomplete rows (missing department, date, etc.)
    $incompleteRows = 0;
    foreach ($records as $rec) {
        $payload = json_decode($rec['payload'] ?? '{}', true);
        if ($payload) {
            // Check for essential fields
            $essentialFilled = true;
            foreach (['department', 'record_date', 'warehouse'] as $field) {
                if (empty($payload[$field] ?? '')) {
                    $essentialFilled = false;
                    break;
                }
            }
            if (!$essentialFilled) {
                $incompleteRows++;
            }
        }
    }

    if ($incompleteRows > 0) {
        $issues[] = [
            'severity' => 'Medium',
            'title' => 'Incomplete records',
            'description' => "$incompleteRows imported rows are missing department, date, or warehouse fields.",
            'category' => 'completeness',
            'affected_count' => $incompleteRows
        ];
    }

    // Calculate quality score
    $deductionPerIssue = 2; // 2 points per issue category
    $baseScore = 100;
    $totalDeduction = count($issues) * $deductionPerIssue;
    $qualityScore = max(0, $baseScore - $totalDeduction);
    // Cap at 100, minimum disclosure
    $qualityScore = min(100, max(0, $qualityScore));

    // Update or insert quality score tracking
    $stmt = $pdo->prepare("
        CREATE TABLE IF NOT EXISTS quality_scores (
            id INT AUTO_INCREMENT PRIMARY KEY,
            calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            score INT NOT NULL,
            issue_count INT NOT NULL,
            total_records INT NOT NULL
        )
    ");
    $stmt->execute();

    $insScore = $pdo->prepare("INSERT INTO quality_scores (score, issue_count, total_records) VALUES (?, ?, ?)");
    $insScore->execute([$qualityScore, count($issues), $totalRecords]);

    echo json_encode([
        'success' => true,
        'quality_score' => $qualityScore,
        'score_change' => 0, // Would compare with previous in production
        'total_records' => $totalRecords,
        'issues_found' => count($issues),
        'issue_breakdown' => array_count_values(array_map(fn($i) => $i['severity'], $issues)),
        'issues' => $issues,
        'recommendations' => $this->getRecommendations($issues),
        'action_url' => '?view=data-quality'
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo 'Database error: ' . $e->getMessage();
}

function getRecommendations($issues) {
    $recommendations = [];
    foreach ($issues as $issue) {
        $cat = $issue['category'];
        if ($cat === 'deduplication') {
            $recommendations[] = 'Remove duplicate IMEI entries by keeping the most recent record and removing older duplicates.';
        } elseif ($cat === 'missing_data') {
            $recommendations[] = 'Import complete records with IMEI/Serial Number for all devices.';
        } elseif ($cat === 'reconciliation') {
            $recommendations[] = 'Verify invoice references for unmatched records and complete the matching process.';
        } elseif ($cat === 'freshness') {
            $recommendations[] = 'Schedule regular CSV imports from Google Sheets or re-upload updated exports.';
        } elseif ($cat === 'completeness') {
            $recommendations[] = 'Ensure all required fields (department, record_date, warehouse) are populated during import.';
        }
    }
    return $recommendations;
}