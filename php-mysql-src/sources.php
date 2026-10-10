<?php
session_start();
$configPath = __DIR__ . '/config.php';
$config = file_exists($configPath) ? require $configPath : require __DIR__ . '/config.example.php';
date_default_timezone_set($config['timezone'] ?? 'Asia/Dhaka');
$demo = (bool)($config['demo_mode'] ?? true);

if ($demo) {
    http_response_code(403);
    echo 'Source management disabled in demo mode.';
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

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $action = $_POST['action'];

        // Register a new data source
        if ($action === 'register') {
            $sourceName = trim($_POST['source_name'] ?? '');
            $department = trim($_POST['department'] ?? '');
            $sourceType = trim($_POST['source_type'] ?? '');
            $publishedUrl = trim($_POST['published_url'] ?? '');

            if (empty($sourceName) || empty($department) || empty($sourceType)) {
                http_response_code(400);
                echo 'Source name, department, and type are required.';
                exit;
            }

            // Find or create department
            $deptStmt = $pdo->prepare("SELECT id FROM departments WHERE name = ?");
            $deptStmt->execute([$department]);
            $dept = $deptStmt->fetch();
            $departmentId = $dept ? $dept['id'] : null;
            if (!$departmentId) {
                $insDept = $pdo->prepare("INSERT INTO departments (name) VALUES (?)");
                $insDept->execute([$department]);
                $departmentId = $pdo->lastInsertId();
            }

            // Determine status based on source type
            $status = 'connected';
            if ($sourceType === 'Google Sheets CSV URL' && empty($publishedUrl)) {
                $status = 'pending';
            } elseif ($sourceType === 'Manual CSV upload') {
                $status = 'connected';
            }

            $stmt = $pdo->prepare(
                "INSERT INTO data_sources (department_id, name, source_type, source_url, last_synced_at, status) VALUES (:dept_id, :name, :type, :url, NOW(), :status)"
            );
            $stmt->execute([
                ':dept_id' => $departmentId,
                ':name' => $sourceName,
                ':type' => $sourceType,
                ':url' => $publishedUrl,
                ':status' => $status
            ]);

            // Log the source registration
            $auditStmt = $pdo->prepare("INSERT INTO audit_log (actor, action, details) VALUES (:actor, :action, :details)");
            $auditStmt->execute([
                ':actor' => 'System',
                ':action' => 'source_register',
                ':details' => "Registered source: {$sourceName} ({$sourceType}) for department: {$department}"
            ]);

            echo json_encode([
                'success' => true,
                'message' => "Source '{$sourceName}' registered successfully.",
                'status' => $status
            ]);
        }

        // List all sources
        if ($action === 'list') {
            $stmt = $pdo->query("SELECT ds.*, d.name AS department_name FROM data_sources ds LEFT JOIN departments d ON ds.department_id = d.id ORDER BY ds.created_at DESC");
            $sources = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode([
                'success' => true,
                'sources' => $sources
            ]);
        }

        // Get source details
        if ($action === 'get') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id === 0) {
                http_response_code(400);
                echo 'Source ID required.';
                exit;
            }
            $stmt = $pdo->prepare("SELECT ds.*, d.name AS department_name FROM data_sources ds LEFT JOIN departments d ON ds.department_id = d.id WHERE ds.id = ?");
            $stmt->execute([$id]);
            $source = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode([
                'success' => true,
                'source' => $source
            ]);
        }

        // Update source status/sync
        if ($action === 'update') {
            $id = (int)($_POST['id'] ?? 0);
            $status = trim($_POST['status'] ?? '');

            if ($id === 0) {
                http_response_code(400);
                echo 'Source ID required.';
                exit;
            }

            $stmt = $pdo->prepare("UPDATE data_sources SET status = ?, last_synced_at = NOW() WHERE id = ?");
            $stmt->execute([$status, $id]);

            echo json_encode([
                'success' => true,
                'message' => "Source #{$id} status updated to {$status}."
            ]);
        }

        // Delete source
        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id === 0) {
                http_response_code(400);
                echo 'Source ID required.';
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM data_sources WHERE id = ?");
            $stmt->execute([$id]);

            echo json_encode([
                'success' => true,
                'message' => "Source #{$id} deleted."
            ]);
        }

    } else {
        http_response_code(405);
        echo 'Invalid request method.';
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Database error: ' . $e->getMessage();
}