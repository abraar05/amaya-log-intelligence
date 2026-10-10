<?php
session_start();
$configPath = __DIR__ . '/config.php';
$config = file_exists($configPath) ? require $configPath : require __DIR__ . '/config.example.php';
date_default_timezone_set($config['timezone'] ?? 'Asia/Dhaka');
$demo = (bool)($config['demo_mode'] ?? true);

if ($demo) {
    http_response_code(403);
    echo 'Import disabled in demo mode. Configure database and set demo_mode=false.';
    exit;
}

if (!isset($_FILES['csvFile']) || $_FILES['csvFile']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo 'No file uploaded.';
    exit;
}

$file = $_FILES['csvFile'];
$tmpPath = $file['tmp_name'];
$fileName = $file['name'];
$fileSize = $file['size'];

$allowedTypes = ['text/csv', 'text/plain', 'application/csv', 'application/octet-stream', 'application/binary'];
if (!in_array($file['type'], $allowedTypes) && !pathinfo($fileName, PATHINFO_EXTENSION) === 'csv') {
    http_response_code(400);
    echo 'Invalid file type. Only CSV files are allowed.';
    exit;
}

if ($fileSize > 5 * 1024 * 1024) {
    http_response_code(400);
    echo 'File too large. Maximum size: 5MB.';
    exit;
}

$handle = fopen($tmpPath, 'r');
if (!$handle) {
    http_response_code(500);
    echo 'Unable to open uploaded file.';
    exit;
}

$headers = fgetcsv($handle, 10000, ',');
if (!$headers) {
    fclose($handle);
    http_response_code(400);
    echo 'Empty CSV file.';
    exit;
}

// Normalize headers: trim whitespace, lowercase for matching
$headers = array_map('trim', $headers);

// Expected columns per DATA_MODEL.md
$expectedFields = ['department', 'source_name', 'record_date', 'imei', 'serial_number', 'invoice_number', 'movement_type', 'quantity', 'warehouse', 'status', 'notes'];
$headerMap = [];
foreach ($expectedFields as $field) {
    $lower = strtolower($field);
    $index = array_search($lower, array_map('strtolower', $headers));
    if ($index !== false) {
        $headerMap[$field] = $index;
    }
}

$records = [];
$rowNum = 0;
while (($row = fgetcsv($handle, 10000, ',')) !== false) {
    $rowNum++;
    // Skip rows where all fields are empty
    if (array_sum(array_map('trim', $row)) === 0) continue;

    $record = [];
    foreach ($expectedFields as $field) {
        $idx = $headerMap[$field] ?? null;
        $record[$field] = ($idx !== null && $idx < count($row)) ? trim($row[$idx]) : '';
    }

    // Validate required: IMEI must be present and not empty
    if (trim($record['imei']) === '') {
        continue; // Skip rows without IMEI
    }

    // Preserve IMEI as string (never numeric)
    $record['imei'] = (string)$record['imei'];

    // Set quality_status based on checks
    $record['quality_status'] = 'unreviewed';

    // Build a simple payload for traceability
    $payload = json_encode([
        'original_row' => $row,
        'header_map' => $headerMap,
        'import_row' => $record,
        'row_num' => $rowNum,
        'file_name' => $fileName
    ]);

    $records[] = [
        'source_id' => null, // Will be set when source is registered
        'record_key' => $record['imei'],
        'payload' => $payload,
        'quality_status' => 'unreviewed'
    ];
}
fclose($handle);

if (empty($records)) {
    http_response_code(400);
    echo 'No valid records found (all missing IMEI).';
    exit;
}

// Database connection
$db = $config['db'] ?? [];
if (!$demo && isset($db['host'], $db['name'], $db['user'], $db['pass'])) {
    try {
        $pdo = new PDO(
            "mysql:host={$db['host']};dbname={$db['name']};charset={$db['charset']}",
            $db['user'], $db['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );

        // Prepare insert statement
        $stmt = $pdo->prepare(
            "INSERT INTO imported_records (source_id, record_key, payload, quality_status) VALUES (:source_id, :record_key, :payload, :quality_status)"
        );

        foreach ($records as $rec) {
            $stmt->execute([
                ':source_id' => $rec['source_id'],
                ':record_key' => $rec['record_key'],
                ':payload' => $rec['payload'],
                ':quality_status' => $rec['quality_status']
            ]);
        }

        // Log the import
        $auditStmt = $pdo->prepare("INSERT INTO audit_log (actor, action, details) VALUES (:actor, :action, :details)");
        $auditStmt->execute([
            ':actor' => 'System',
            ':action' => 'CSV import',
            ':details' => "Imported {$count} records from {$fileName}"
        ]);

        echo json_encode([
            'success' => true,
            'imported' => count($records),
            'message' => "Successfully imported {$count} records."
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo 'Database error: ' . $e->getMessage();
    }
} else {
    http_response_code(500);
    echo 'Database not configured. Set demo_mode=false and configure config.php.';
}