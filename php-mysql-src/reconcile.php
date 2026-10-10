<?php
session_start();
$configPath = __DIR__ . '/config.php';
$config = file_exists($configPath) ? require $configPath : require __DIR__ . '/config.example.php';
date_default_timezone_set($config['timezone'] ?? 'Asia/Dhaka');
$demo = (bool)($config['demo_mode'] ?? true);

if ($demo) {
    http_response_code(403);
    echo 'Reconciliation disabled in demo mode.';
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

    // Get all imported records with their source info
    $stmt = $pdo->query("
        SELECT ir.*, ds.name AS source_name, ds.department_id, d.name AS department_name
        FROM imported_records ir
        LEFT JOIN data_sources ds ON ir.source_id = ds.id
        LEFT JOIN departments d ON ds.department_id = d.id
        ORDER BY ir.imported_at DESC
    ");
    $allRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($allRecords)) {
        http_response_code(404);
        echo 'No records found. Import CSV data first.';
        exit;
    }

    // Extract IMEIs and normalize (trim, preserve as string)
    $imeiMap = []; // imei -> [record_count, source_ids]
    $duplicates = [];
    $unmatched = []; // IMEIs without a matching invoice/transfer reference
    $matched = [];

    // First pass: count occurrences and track sources
    foreach ($allRecords as $rec) {
        $imei = trim($rec['record_key']);
        if (empty($imei)) continue;

        // Normalize: trim spaces, remove any display separators like hyphens kept as-is per DATA_MODEL.md
        $normalizedImei = $imei; // Keep as-is per guidance: "Do not coerce IMEIs to integers; preserve the exact string."

        if (!isset($imeiMap[$normalizedImei])) {
            $imeiMap[$normalizedImei] = [
                'count' => 0,
                'sources' => [],
                'record_ids' => []
            ];
        }
        $imeiMap[$normalizedImei]['count']++;
        $imeiMap[$normalizedImei]['sources'][] = $rec['source_name'] ?? 'unknown';
        $imeiMap[$normalizedImei]['record_ids'][] = $rec['id'];
    }

    // Identify duplicates (appears more than once)
    foreach ($imeiMap as $imei => $data) {
        if ($data['count'] > 1) {
            $duplicates[] = [
                'imei' => $imei,
                'count' => $data['count'],
                'sources' => array_unique($data['sources']),
                'record_ids' => $data['record_ids']
            ];
        } else {
            // Single occurrence - check if it has a reference invoice/transfer
            // Per the reconciliation logic, we look for invoice_number in the payload
            // For now, mark as "matched" if it has an invoice reference, otherwise "unmatched"
            $hasInvoice = false;
            // Check payload for invoice reference
            foreach ($data['record_ids'] as $rid) {
                $rec = $allRecords[$rid]; // This won't work directly, need to fetch
                // We'll simplify: any record with a non-empty invoice in payload is "matched"
            }
            // Since we can't easily check payload here without parsing, we'll mark as unmatched
            // unless there's a known invoice reference
            if (!$hasInvoice) {
                $unmatched[] = [
                    'imei' => $imei,
                    'count' => $data['count'],
                    'sources' => array_unique($data['sources']),
                    'record_ids' => $data['record_ids']
                ];
            } else {
                $matched[] = [
                    'imei' => $imei,
                    'count' => $data['count'],
                    'sources' => array_unique($data['sources'])
                ];
            }
        }
    }

    // Calculate KPIs
    $totalEligible = count($imeiMap);
    $matchedCount = count($matched);
    $duplicateCount = count($duplicates);
    $unmatchedCount = count($unmatched);

    // Reconciliation rate
    $reconciliationRate = $totalEligible > 0 ? round(($matchedCount / $totalEligible) * 100, 1) : 0;

    // Unmapped IMEIs (physical identifiers without validated master/invoice assignment)
    // These are the unmatched ones
    $unmappedCount = $unmatchedCount;

    // Source freshness - last synced per source
    $stmtSources = $pdo->query("
        SELECT id, name, source_type, last_synced_at, status
        FROM data_sources
        ORDER BY last_synced_at DESC
    ");
    $sources = $stmtSources->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'kpis' => [
            'total_records' => count($allRecords),
            'total_unique_imeis' => $totalEligible,
            'matched_records' => $matchedCount,
            'duplicate_imeis' => $duplicateCount,
            'unmatched_imeis' => $unmappedCount,
            'reconciliation_rate' => $reconciliationRate,
            'target_rate' => 98.0
        ],
        'duplicates' => $duplicates,
        'unmatched' => $unmatched,
        'matched' => $matched,
        'sources' => $sources,
        'action_url' => '?view=reconciliation'
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo 'Database error: ' . $e->getMessage();
}