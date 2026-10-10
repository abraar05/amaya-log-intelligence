<?php
// Run once after configuring config.php, then DELETE this file.
$config = file_exists(__DIR__.'/config.php') ? require __DIR__.'/config.php' : require __DIR__.'/config.example.php';
if (($config['demo_mode'] ?? true) === true) { exit('Demo mode is enabled. Set demo_mode=false in config.php before installation.'); }
$db=$config['db'];
try {
 $pdo=new PDO("mysql:host={$db['host']};dbname={$db['name']};charset={$db['charset']}",$db['user'],$db['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $sql=[
 "CREATE TABLE IF NOT EXISTS departments (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL UNIQUE, owner_name VARCHAR(120) NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)",
 "CREATE TABLE IF NOT EXISTS data_sources (id INT AUTO_INCREMENT PRIMARY KEY, department_id INT NULL, name VARCHAR(180) NOT NULL, source_type VARCHAR(50) NOT NULL, source_url TEXT NULL, last_synced_at DATETIME NULL, status VARCHAR(30) NOT NULL DEFAULT 'pending', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL)",
 "CREATE TABLE IF NOT EXISTS imported_records (id BIGINT AUTO_INCREMENT PRIMARY KEY, source_id INT NULL, record_key VARCHAR(190) NULL, payload LONGTEXT NOT NULL, quality_status VARCHAR(30) NOT NULL DEFAULT 'unreviewed', imported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_record_key(record_key), FOREIGN KEY (source_id) REFERENCES data_sources(id) ON DELETE SET NULL)",
 "CREATE TABLE IF NOT EXISTS audit_log (id BIGINT AUTO_INCREMENT PRIMARY KEY, actor VARCHAR(120) NOT NULL, action VARCHAR(120) NOT NULL, details TEXT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)"
 ];
 foreach($sql as $q)$pdo->exec($q);
 foreach(['Logistics','Sales','Finance','Human resources','Customer service','Operations'] as $name){$st=$pdo->prepare('INSERT IGNORE INTO departments(name) VALUES(?)');$st->execute([$name]);}
 echo '<h1>Amaya Log Intelligence database installed</h1><p>Tables created. Delete install.php now, then disable demo mode and connect the application routes to database persistence before production use.</p>';
} catch(Throwable $e){http_response_code(500);echo 'Installation failed. Check database configuration and permissions.';}
