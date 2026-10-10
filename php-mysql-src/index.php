<?php
session_start();
$configPath = __DIR__ . '/config.php';
$config = file_exists($configPath) ? require $configPath : require __DIR__ . '/config.example.php';
date_default_timezone_set($config['timezone'] ?? 'Asia/Dhaka');
$demo = (bool)($config['demo_mode'] ?? true);
$pdo = null;
if (!$demo && isset($config['db'])) {
    try {
        $db = $config['db'];
        $pdo = new PDO(
            "mysql:host={$db['host']};dbname={$db['name']};charset={$db['charset']}",
            $db['user'], $db['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    } catch (Throwable $e) { $dbError = 'Database connection failed. Check config.php.'; }
}
$view = preg_replace('/[^a-z-]/', '', $_GET['view'] ?? 'overview');
$allowed = ['overview','departments','sources','reconciliation','reports','data-quality','activity','settings'];
if (!in_array($view, $allowed, true)) $view = 'overview';

// Database connection when not demo mode
$pdo = null;
$demo = (bool)($config['demo_mode'] ?? true);
if (!$demo) {
    $db = $config['db'] ?? [];
    if (isset($db['host'], $db['name'], $db['user'], $db['pass'])) {
        try {
            $pdo = new PDO(
                "mysql:host={$db['host']};dbname={$db['name']};charset={$db['charset']}",
                $db['user'], $db['pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );
        } catch (Throwable $e) {
            $dbError = 'Database connection failed. Check config.php.';
        }
    }
}
$nav = [
 ['overview','Overview','layout-dashboard'],['departments','Departments','building-2'],
 ['sources','Data sources','database'],['reconciliation','IMEI reconciliation','scan-line'],
 ['reports','Reports & insights','chart-no-axes-combined'],['data-quality','Data quality','shield-check'],
 ['activity','Activity log','history'],['settings','Settings','settings']
];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($config['app_name'] ?? 'Amaya Log Intelligence') ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/app.css">
<script src="https://unpkg.com/lucide@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="app-shell">
<aside class="sidebar" id="sidebar">
  <a class="brand" href="?view=overview"><span class="brand-mark"><i data-lucide="layers-3"></i></span><span><b>AMAYA</b><small>LOG INTELLIGENCE</small></span></a>
  <div class="workspace"><span class="workspace-icon">A</span><span><b>Amaya Distribution</b><small>Operations workspace</small></span><i data-lucide="chevrons-up-down"></i></div>
  <div class="nav-label">WORKSPACE</div>
  <nav>
  <?php foreach($nav as [$key,$label,$icon]): ?>
    <a class="nav-item <?= $view===$key?'active':'' ?>" href="?view=<?= $key ?>"><i data-lucide="<?= $icon ?>"></i><span><?= $label ?></span><?php if($key==='data-quality'): ?><em class="nav-count">4</em><?php endif; ?></a>
  <?php endforeach; ?>
  </nav>
  <div class="sidebar-bottom"><div class="sync-status"><span class="pulse"></span><span><b>Workspace status</b><small>All systems operational</small></span></div><a class="profile" href="?view=settings"><div class="avatar">AA</div><span><b>Operations Admin</b><small>Administrator</small></span><i data-lucide="more-horizontal"></i></a></div>
</aside>
<main class="main">
<header class="topbar"><button class="icon-btn mobile-menu" id="menuToggle" aria-label="Open menu"><i data-lucide="menu"></i></button><div class="breadcrumbs"><span>Workspace</span><i data-lucide="chevron-right"></i><b><?= htmlspecialchars(ucwords(str_replace('-',' ',$view))) ?></b></div><div class="top-actions"><div class="searchbox"><i data-lucide="search"></i><input id="globalSearch" placeholder="Search records, IMEIs..." aria-label="Search"></div><button class="icon-btn" id="themeToggle" title="Toggle theme"><i data-lucide="sun-moon"></i></button><button class="icon-btn notification" title="Notifications"><i data-lucide="bell"></i><span></span></button><div class="top-avatar">AA</div></div></header>
<section class="content">
<?php if(isset($dbError)): ?><div class="notice warning"><?= htmlspecialchars($dbError) ?></div><?php endif; ?>
<?php if($view==='overview'): ?>
  <div class="page-heading"><div><div class="eyebrow">SATURDAY, OCTOBER 10, 2026 <span class="live-dot"></span> LIVE WORKSPACE</div><h1>Good morning, team <span class="wave">✦</span></h1><p>Your operational picture, brought together in one place.</p></div><div class="heading-actions"><button class="btn btn-secondary" onclick="window.print()"><i data-lucide="download"></i> Export overview</button><a class="btn btn-primary" href="?view=sources"><i data-lucide="plus"></i> Connect data source</a></div></div>
  <div class="banner"><div class="banner-icon"><i data-lucide="sparkles"></i></div><div class="banner-copy"><b>Your operations briefing</b><p>Reconciliation is progressing steadily. Four data-quality items need review, and one source is awaiting its next refresh. Review flagged records before closing today's report.</p></div><a href="?view=data-quality" class="banner-link">Review insights <i data-lucide="arrow-up-right"></i></a></div>
  <div class="section-head"><div><h2>Executive overview</h2><p>Key metrics across connected operations</p></div><div class="period-select"><i data-lucide="calendar-days"></i><select id="period"><option>Last 30 days</option><option>Last 7 days</option><option>Today</option></select><i data-lucide="chevron-down"></i></div></div>
  <div class="metrics-grid">
    <article class="metric-card"><div class="metric-top"><span>Records processed</span><span class="metric-icon blue"><i data-lucide="database"></i></span></div><div class="metric-value" data-count="24860">24,860</div><div class="metric-foot"><span class="trend positive"><i data-lucide="trending-up"></i> 12.8%</span><span>vs. previous period</span><div class="sparkline blue-line"><span></span><span></span><span></span><span></span><span></span><span></span><span></span></div></div></article>
    <article class="metric-card"><div class="metric-top"><span>IMEI reconciliation</span><span class="metric-icon green"><i data-lucide="scan-line"></i></span></div><div class="metric-value">96.4<span class="unit">%</span></div><div class="metric-foot"><span class="trend positive"><i data-lucide="trending-up"></i> 2.4%</span><span>target: 98%</span><div class="mini-progress"><span style="width:96.4%"></span></div></div></article>
    <article class="metric-card"><div class="metric-top"><span>Data quality flags</span><span class="metric-icon amber"><i data-lucide="shield-alert"></i></span></div><div class="metric-value">04</div><div class="metric-foot"><span class="trend caution">Needs attention</span><span>across 3 sources</span><div class="sparkline amber-line"><span></span><span></span><span></span><span></span><span></span><span></span><span></span></div></div></article>
    <article class="metric-card"><div class="metric-top"><span>Connected sources</span><span class="metric-icon violet"><i data-lucide="workflow"></i></span></div><div class="metric-value">08<span class="unit">/ 10</span></div><div class="metric-foot"><span class="trend neutral">2 pending</span><span>across 6 departments</span><div class="mini-progress violet-progress"><span style="width:80%"></span></div></div></article>
  </div>
  <div class="dashboard-grid">
    <article class="panel performance-panel"><div class="panel-heading"><div><h3>Operational activity</h3><p>Processed records and successful matches</p></div><button class="icon-btn small" title="More options"><i data-lucide="ellipsis"></i></button></div><div class="chart-legend"><span><i class="legend-dot primary-dot"></i> Records processed</span><span><i class="legend-dot secondary-dot"></i> Matched records</span></div><div class="chart-wrap"><canvas id="activityChart" height="230"></canvas></div></article>
    <article class="panel department-panel"><div class="panel-heading"><div><h3>Department health</h3><p>Operational readiness by team</p></div><a href="?view=departments" class="text-link">View all</a></div>
      <div class="dept-row"><div class="dept-avatar blue-bg">LG</div><div class="dept-info"><b>Logistics</b><small>4 sources · Updated 8m ago</small></div><div class="dept-score"><b>98%</b><span class="status-dot good"></span></div></div>
      <div class="dept-row"><div class="dept-avatar green-bg">SL</div><div class="dept-info"><b>Sales</b><small>2 sources · Updated 16m ago</small></div><div class="dept-score"><b>96%</b><span class="status-dot good"></span></div></div>
      <div class="dept-row"><div class="dept-avatar violet-bg">FN</div><div class="dept-info"><b>Finance</b><small>1 source · Updated 1h ago</small></div><div class="dept-score"><b>91%</b><span class="status-dot warn"></span></div></div>
      <div class="dept-row"><div class="dept-avatar amber-bg">HR</div><div class="dept-info"><b>Human resources</b><small>1 source · Updated yesterday</small></div><div class="dept-score"><b>88%</b><span class="status-dot warn"></span></div></div>
      <div class="dept-row"><div class="dept-avatar gray-bg">OP</div><div class="dept-info"><b>Other operations</b><small>2 sources · Sync pending</small></div><div class="dept-score"><b>—</b><span class="status-dot muted"></span></div></div>
    </article>
  </div>
  <div class="dashboard-grid lower-grid">
    <article class="panel"><div class="panel-heading"><div><h3>Recent activity</h3><p>Latest changes across your workspace</p></div><a href="?view=activity" class="text-link">Activity log</a></div><div class="activity-list">
      <div class="activity-item"><span class="activity-icon green"><i data-lucide="check-check"></i></span><div><b>Logistics reconciliation completed</b><p>1,248 records matched against transfer invoices.</p><small>8 minutes ago · Logistics</small></div><span class="activity-tag success-tag">Completed</span></div>
      <div class="activity-item"><span class="activity-icon blue"><i data-lucide="file-spreadsheet"></i></span><div><b>Daily sales sheet imported</b><p>386 new rows added to the reporting workspace.</p><small>16 minutes ago · Sales</small></div><span class="activity-tag">Imported</span></div>
      <div class="activity-item"><span class="activity-icon amber"><i data-lucide="alert-triangle"></i></span><div><b>Duplicate IMEI records detected</b><p>Three identifiers appear more than once in the source data.</p><small>42 minutes ago · Data quality</small></div><span class="activity-tag warning-tag">Review</span></div>
    </div></article>
    <article class="panel insights-panel"><div class="panel-heading"><div><h3>Intelligence notes</h3><p>Signals worth your attention</p></div><span class="ai-badge"><i data-lucide="sparkles"></i> INSIGHTS</span></div><div class="insight"><span class="insight-marker purple"></span><div><b>Reconciliation is below target</b><p>Current match rate is 1.6 points below the 98% target. Review unmatched transfer records first.</p><a href="?view=reconciliation">Open reconciliation <i data-lucide="arrow-right"></i></a></div></div><div class="insight"><span class="insight-marker orange"></span><div><b>Refresh Finance source</b><p>The Finance sheet has not refreshed in over an hour. Confirm the latest export is available.</p><a href="?view=sources">Manage data sources <i data-lucide="arrow-right"></i></a></div></div></article>
  </div>
<?php elseif($view==='departments'): ?>
  <div class="page-heading"><div><div class="eyebrow">WORKSPACE / ORGANIZATION</div><h1>Departments</h1><p>Monitor operational performance and data readiness across teams.</p></div><button class="btn btn-primary" onclick="showToast('Department creation form is ready to be connected to the database.')"><i data-lucide="plus"></i> Add department</button></div>
  <div class="metrics-grid compact-metrics"><article class="metric-card"><div class="metric-top"><span>Total departments</span><span class="metric-icon blue"><i data-lucide="building-2"></i></span></div><div class="metric-value">06</div><div class="metric-foot"><span>Across the workspace</span></div></article><article class="metric-card"><div class="metric-top"><span>Healthy departments</span><span class="metric-icon green"><i data-lucide="check-circle-2"></i></span></div><div class="metric-value">04</div><div class="metric-foot"><span>Within quality threshold</span></div></article><article class="metric-card"><div class="metric-top"><span>Pending updates</span><span class="metric-icon amber"><i data-lucide="clock-3"></i></span></div><div class="metric-value">02</div><div class="metric-foot"><span>Refresh or review needed</span></div></article></div>
  <div class="panel table-panel"><div class="panel-heading"><div><h3>Department directory</h3><p>Department-level ownership and source status</p></div><div class="table-tools"><input class="table-search" placeholder="Filter departments..." data-filter="departmentTable"><button class="btn btn-secondary" onclick="exportTable('departmentTable','departments.csv')"><i data-lucide="download"></i> Export</button></div></div><div class="table-scroll"><table id="departmentTable"><thead><tr><th>Department</th><th>Data sources</th><th>Records</th><th>Quality score</th><th>Last update</th><th>Status</th></tr></thead><tbody>
  <?php foreach([['Logistics','4','12,486','98%','8 min ago','Healthy','LG'],['Sales','2','6,218','96%','16 min ago','Healthy','SL'],['Finance','1','2,840','91%','1 hr ago','Review needed','FN'],['Human resources','1','1,264','88%','Yesterday','Review needed','HR'],['Customer service','1','1,108','94%','2 hr ago','Healthy','CS'],['Operations','1','944','97%','24 min ago','Healthy','OP']] as $d): ?>
  <tr><td><div class="table-person"><span class="dept-avatar blue-bg"><?= $d[6] ?></span><b><?= $d[0] ?></b></div></td><td><?= $d[1] ?></td><td><?= $d[2] ?></td><td><div class="score-cell"><span><?= $d[3] ?></span><div class="score-bar"><span style="width:<?= (int)$d[3] ?>"></span></div></div></td><td><?= $d[4] ?></td><td><span class="pill <?= $d[5]==='Healthy'?'pill-green':'pill-amber' ?>"><?= $d[5] ?></span></td></tr>
  <?php endforeach; ?></tbody></table></div></div>
<?php elseif($view==='sources'): ?>
  <div class="page-heading"><div><div class="eyebrow">DATA FOUNDATION</div><h1>Data sources</h1><p>Connect, validate, and manage the operational data behind your reports.</p></div><button class="btn btn-primary" onclick="document.getElementById('sourceModal').classList.add('show')"><i data-lucide="plus"></i> Add source</button></div>
  <div class="banner subtle-banner"><div class="banner-icon"><i data-lucide="refresh-cw"></i></div><div class="banner-copy"><b>Keep your data current</b><p>Register a published Google Sheets CSV URL or upload a CSV export. Avoid placing private API keys or credentials in URLs.</p></div></div>
  <div class="panel table-panel"><div class="panel-heading"><div><h3>Connected sources</h3><p>Sources registered</p></div><div class="table-tools"><input class="table-search" placeholder="Search sources..." data-filter="sourcesTable"><button class="btn btn-secondary" onclick="document.getElementById('csvFile').click()"><i data-lucide="upload"></i> Import CSV</button><input type="file" id="csvFile" accept=".csv,text/csv" hidden></div></div><div class="table-scroll"><table id="sourcesTable"><thead><tr><th>Source</th><th>Department</th><th>Type</th><th>Records</th><th>Last sync</th><th>Status</th><th>Action</th></tr></thead><tbody>
  <?php 
  global $pdo;
  $sources = [];
  if ($pdo) {
      $stmt = $pdo->query("SELECT ds.*, d.name AS department_name FROM data_sources ds LEFT JOIN departments d ON ds.department_id = d.id ORDER BY ds.created_at DESC");
      $sources = $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
  foreach($sources as $s): ?>
  <tr><td><div class="source-name"><span class="source-symbol <?= $s['source_type']==='Google Sheets'?'sheets-symbol':'csv-symbol' ?>"><i data-lucide="<?= $s['source_type']==='Google Sheets'?'file-spreadsheet':'file-text' ?>"></i></span><b><?= htmlspecialchars($s['name']) ?></b></div></td><td><?= htmlspecialchars($s['department_name'] ?? '—') ?></td><td><?= htmlspecialchars($s['source_type']) ?></td><td><?= number_format($s['record_count'] ?? 0) ?></td><td><?= $s['last_synced_at'] ?? '—' ?></td><td><span class="pill <?= $s['status']=='Connected'?'pill-green':'pill-amber' ?>"><?= htmlspecialchars($s['status']) ?></span></td><td><button class="icon-btn small" title="Configure source" onclick="showToast('Configure source #<?= $s['id'] ?>')"><i data-lucide="settings-2"></i></button></td></tr>
  <?php endforeach; if (empty($sources)): ?><tr><td colspan="7" style="padding:12px;color:#657187">No sources registered. Add a source to get started.</td></tr><?php endif; ?></tbody></table></div></div>
  <div class="panel import-panel"><div class="panel-heading"><div><h3>Import a CSV file</h3><p>Upload exported Google Sheets or Excel CSV data for validation.</p></div><span class="pill pill-blue">CSV supported</span></div><label class="dropzone" for="csvFile"><i data-lucide="cloud-upload"></i><b>Choose a CSV file to import</b><span>Export .xlsx files to CSV first · Maximum file size depends on host limits</span><span class="btn btn-secondary">Browse files</span></label><div id="fileInfo" class="file-info"></div></div>
  <div class="modal-backdrop" id="sourceModal"><div class="modal"><div class="modal-heading"><div><h3>Register a data source</h3><p>Connect a sheet or prepare a manual import.</p></div><button class="icon-btn" onclick="document.getElementById('sourceModal').classList.remove('show')"><i data-lucide="x"></i></button></div><form id="sourceForm"><label>Source name<input required placeholder="e.g. Daily IMEI Movement"></label><label>Department<select><option>Logistics</option><option>Sales</option><option>Finance</option><option>Human resources</option><option>Operations</option><option>Customer service</option></select></label><label>Source type<select><option>Google Sheets CSV URL</option><option>Manual CSV upload</option></select></label><label>Published CSV URL<input type="url" placeholder="https://docs.google.com/spreadsheets/d/.../export?format=csv"></label><div class="form-note"><i data-lucide="info"></i> The URL must be accessible to the server. Private sheets require a secure integration rather than a public link.</div><div class="modal-actions"><button type="button" class="btn btn-secondary" onclick="document.getElementById('sourceModal').classList.remove('show')">Cancel</button><button type="submit" class="btn btn-primary">Save source</button></div></form></div></div>
<?php elseif($view==='reconciliation'): ?>
  <div class="page-heading"><div><div class="eyebrow">LOGISTICS CONTROL</div><h1>IMEI reconciliation</h1><p>Match device identifiers to sales, transfer, and inventory records.</p></div><button class="btn btn-primary" onclick="document.getElementById('csvFile').click()"><i data-lucide="upload"></i> Import records</button></div>
  <div class="metrics-grid"><article class="metric-card"><div class="metric-top"><span>Matched records</span><span class="metric-icon green"><i data-lucide="check-circle-2"></i></span></div><div class="metric-value">18,420</div><div class="metric-foot"><span class="trend positive">96.4% match rate</span></div></article><article class="metric-card"><div class="metric-top"><span>Unmatched</span><span class="metric-icon amber"><i data-lucide="circle-help"></i></span></div><div class="metric-value">688</div><div class="metric-foot"><span>Require invoice matching</span></div></article><article class="metric-card"><div class="metric-top"><span>Duplicate identifiers</span><span class="metric-icon red"><i data-lucide="copy"></i></span></div><div class="metric-value">23</div><div class="metric-foot"><span>Across current data set</span></div></article><article class="metric-card"><div class="metric-top"><span>Unmapped IMEIs</span><span class="metric-icon violet"><i data-lucide="scan-search"></i></span></div><div class="metric-value">129</div><div class="metric-foot"><span>Pending label validation</span></div></article></div>
  <div class="panel table-panel"><div class="panel-heading"><div><h3>Reconciliation queue</h3><p>Sample records for review · Import your own data to work with live records</p></div><div class="table-tools"><input class="table-search" placeholder="Search IMEI or invoice..." data-filter="imeiTable"><select id="statusFilter" class="select-control"><option value="">All statuses</option><option>Matched</option><option>Unmatched</option><option>Duplicate</option></select></div></div><div class="table-scroll"><table id="imeiTable"><thead><tr><th>IMEI / Serial</th><th>Device</th><th>Reference invoice</th><th>Source</th><th>Match status</th><th>Action</th></tr></thead><tbody>
  <?php 
  global $pdo;
  $reconRecords = [];
  if ($pdo) {
      $stmt = $pdo->query("
          SELECT ir.record_key AS imei, ir.payload, ds.name AS source_name,
                 CASE WHEN ir.quality_status = 'matched' THEN 'Matched' 
                      WHEN ir.quality_status = 'duplicate' THEN 'Duplicate' 
                      ELSE 'Unmatched' END AS match_status
          FROM imported_records ir
          LEFT JOIN data_sources ds ON ir.source_id = ds.id
          ORDER BY ir.imported_at DESC
          LIMIT 20
      ");
      $reconRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
  foreach($reconRecords as $r): 
      $payload = json_decode($r['payload'] ?? '{}', true);
      $invoice = $payload['invoice_number'] ?? '';
  ?>
  <tr data-status="<?= htmlspecialchars($r['match_status'] ?? 'Unmatched') ?>"><td><code class="imei-code"><?= htmlspecialchars($r['imei']) ?></code></td><td><?= htmlspecialchars($payload['device_model'] ?? '—') ?></td><td><?= htmlspecialchars($invoice) ?></td><td><?= htmlspecialchars($r['source_name'] ?? '—') ?></td><td><span class="pill <?= $r['match_status']=='Matched'?'pill-green':($r['match_status']=='Duplicate'?'pill-red':'pill-amber') ?>"><?= htmlspecialchars($r['match_status'] ?? 'Unmatched') ?></span></td><td><button class="btn btn-tertiary" onclick="showToast('Record selected for review.')">Review <i data-lucide="arrow-up-right"></i></button></td></tr>
  <?php endforeach; if (empty($reconRecords)): ?><tr><td colspan="6" style="padding:12px;color:#657187">No records imported yet. Use the import feature to add CSV data.</td></tr><?php endif; ?></tbody></table></div><div class="table-footer"><span>Showing 5 example records</span><button class="btn btn-secondary" onclick="exportTable('imeiTable','imei-reconciliation.csv')"><i data-lucide="download"></i> Export CSV</button></div></div>
  <input type="file" id="csvFile" accept=".csv,text/csv" hidden><div id="fileInfo" class="file-info"></div>
<?php elseif($view==='reports'): ?>
  <div class="page-heading"><div><div class="eyebrow">DECISION SUPPORT</div><h1>Reports & insights</h1><p>Turn operational data into a clear, decision-ready view.</p></div><button class="btn btn-primary" onclick="showToast('Report builder opened in preview mode.')"><i data-lucide="plus"></i> Create report</button></div>
  <div class="report-grid"><?php foreach([['Executive operations summary','Cross-department performance, risks, and key movements.','chart-no-axes-combined','blue'],['IMEI reconciliation report','Matched, unmatched, duplicate, and unmapped identifiers.','scan-line','green'],['Stock movement report','Daily inbound/outbound volume and inventory changes.','package','violet'],['Data quality report','Missing fields, invalid values, duplicates, and stale sources.','shield-check','amber'],['Department performance','Source freshness, data completeness, and operational score.','building-2','blue'],['Custom report builder','Choose fields, filters, grouping, and export format.','sliders-horizontal','gray']] as $r): ?><article class="report-card"><div class="report-icon <?= $r[3] ?>"><i data-lucide="<?= $r[2] ?>"></i></div><h3><?= $r[0] ?></h3><p><?= $r[1] ?></p><button class="text-link" onclick="showToast('Report preview is ready to connect to imported data.')">Open report <i data-lucide="arrow-up-right"></i></button></article><?php endforeach; ?></div>
<?php elseif($view==='data-quality'): ?>
  <?php 
  global $pdo;
  $issues = [];
  $issueCount = 0;
  $qualityScore = 94.2;
  if ($pdo) {
      $imeiCounts = [];
      $allStmt = $pdo->query("SELECT * FROM imported_records");
      $allRecords = $allStmt->fetchAll(PDO::FETCH_ASSOC);
      
      foreach ($allRecords as $rec) {
          $imei = trim($rec['record_key']);
          if (!empty($imei)) { $imeiCounts[$imei] ??= 0; $imeiCounts[$imei]++; }
      }
      $dupes = array_filter($imeiCounts, fn($c) => $c > 1);
      $staleSources = 0;
      $srcStmt = $pdo->query("SELECT last_synced_at FROM data_sources");
      $srcs = $srcStmt->fetchAll(PDO::FETCH_ASSOC);
      foreach ($srcs as $s) { if ($s['last_synced_at']) { $last = new DateTime($s['last_synced_at']); $now = new DateTime(); $hours = $now->diff($last)->h + ($now->diff($last)->days * 24); if ($hours > 60) $staleSources++; } }
      $unmapped = 0; foreach ($allRecords as $rec) { $p = json_decode($rec['payload'] ?? '{}', true); if ($p && isset($p['invoice_number']) && empty($p['invoice_number'])) $unmapped++; }
      $incomplete = 0; foreach ($allRecords as $rec) { $p = json_decode($rec['payload'] ?? '{}', true); if ($p && (!empty($p['department']) || !empty($p['record_date']) || !empty($p['warehouse']))) {} else { $incomplete++; } }
      $issueCount = count($dupes) + $staleSources + $unmapped + $incomplete;
      $qualityScore = max(0, 100 - ($issueCount * 2));
      $qualityScore = min(100, max(0, $qualityScore));
      $issues = [
          ['High', 'Duplicate IMEI identifiers', count($dupes) . ' IMEIs appear more than once in Logistics movement records.', 'Logistics', 'red'],
          ['High', 'Missing invoice reference', $unmapped . ' device records do not have a verified sales or transfer invoice.', 'Logistics', 'red'],
          ['Medium', 'Stale Finance source', $staleSources . ' source has not been refreshed in more than 60 minutes.', 'Finance', 'amber'],
          ['Medium', 'Incomplete attendance rows', $incomplete . ' imported rows are missing employee or date fields.', 'Human resources', 'amber']
      ];
  }
  $scoreDisplay = $qualityScore;
  ?>
  <div class="page-heading"><div><div class="eyebrow">TRUST & GOVERNANCE</div><h1>Data quality</h1><p>Find inconsistencies before they affect decisions or stock reconciliation.</p></div><button class="btn btn-primary" onclick="showToast('Quality scan queued. Review results below.')"><i data-lucide="scan-search"></i> Run quality scan</button></div>
  <div class="metrics-grid"><article class="metric-card"><div class="metric-top"><span>Quality score</span><span class="metric-icon green"><i data-lucide="shield-check"></i></span></div><div class="metric-value"><?= $scoreDisplay ?><span class="unit">%</span></div><div class="metric-foot"><span class="trend positive">+1.8% this month</span></div></article><article class="metric-card"><div class="metric-top"><span>Open issues</span><span class="metric-icon amber"><i data-lucide="alert-circle"></i></span></div><div class="metric-value"><?= $issueCount ?></div><div class="metric-foot"><span>Across 3 data sources</span></div></article><article class="metric-card"><div class="metric-top"><span>Duplicate IMEIs</span><span class="metric-icon red"><i data-lucide="copy"></i></span></div><div class="metric-value"><?php echo count($dupes) ?? 23 ?></div><div class="metric-foot"><span>Need deduplication review</span></div></article></div>
  <div class="panel"><div class="panel-heading"><div><h3>Issues requiring attention</h3><p>Prioritized by potential operational impact</p></div><span class="pill pill-amber"><?= $issueCount ?> open items</span></div><div class="issue-list"><?php foreach($issues as $i): ?><div class="issue-row"><span class="severity <?= $i[4] ?>"><?= $i[0] ?></span><div class="issue-copy"><b><?= $i[1] ?></b><p><?= $i[2] ?></p><small><?= $i[3] ?></small></div><button class="btn btn-secondary" onclick="showToast('Issue added to review queue.')">Review <i data-lucide="arrow-right"></i></button></div><?php endforeach; ?></div></div>
<?php elseif($view==='activity'): ?>
  <div class="page-heading"><div><div class="eyebrow">AUDIT & TRACEABILITY</div><h1>Activity log</h1><p>A chronological view of imports, refreshes, and operational checks.</p></div><button class="btn btn-secondary" onclick="exportTable('activityTable','activity-log.csv')"><i data-lucide="download"></i> Export log</button></div><div class="panel table-panel"><div class="panel-heading"><div><h3>Recent events</h3><p>Demo activity feed · production audit persistence must be enabled</p></div><input class="table-search" placeholder="Search activity..." data-filter="activityTable"></div><div class="table-scroll"><table id="activityTable"><thead><tr><th>Event</th><th>Department</th><th>Actor</th><th>Timestamp</th><th>Result</th></tr></thead><tbody><?php foreach([['Reconciliation completed','Logistics','Operations Admin','Today, 08:42','Success'],['CSV import completed','Sales','Operations Admin','Today, 08:34','Success'],['Duplicate identifiers detected','Logistics','Quality scan','Today, 08:08','Review'],['Source refresh failed','Finance','Scheduled refresh','Today, 07:15','Warning'],['Attendance file imported','Human resources','Operations Admin','Yesterday, 17:42','Success']] as $a): ?><tr><td><b><?= $a[0] ?></b></td><td><?= $a[1] ?></td><td><?= $a[2] ?></td><td><?= $a[3] ?></td><td><span class="pill <?= $a[4]==='Success'?'pill-green':($a[4]==='Review'?'pill-amber':'pill-red') ?>"><?= $a[4] ?></span></td></tr><?php endforeach; ?></tbody></table></div></div>
<?php else: ?>
  <div class="page-heading"><div><div class="eyebrow">WORKSPACE CONFIGURATION</div><h1>Settings</h1><p>Manage workspace preferences, reporting standards, and deployment options.</p></div><button class="btn btn-primary" onclick="showToast('Settings saved in preview mode.')"><i data-lucide="save"></i> Save changes</button></div><div class="settings-grid"><div class="panel settings-nav"><a class="settings-tab selected">General workspace</a><a class="settings-tab">Data governance</a><a class="settings-tab">Reporting defaults</a><a class="settings-tab">Access & roles</a><a class="settings-tab">Integrations</a></div><div class="panel settings-content"><h3>Workspace preferences</h3><p>Set the defaults used throughout your reporting experience.</p><label>Workspace name<input value="Amaya Distribution" /></label><label>Default reporting currency<select><option>BDT — Bangladeshi Taka</option><option>USD — US Dollar</option><option>CNY — Chinese Yuan</option></select></label><label>Default language<select><option>English</option><option>বাংলা</option><option>中文</option></select></label><label>IMEI field name<input value="IMEI / Serial Number" /></label><label>Reconciliation target<div class="inline-input"><input type="number" value="98" min="1" max="100"><span>%</span></div></label><div class="form-note"><i data-lucide="lock-keyhole"></i> Store production credentials in server-side configuration only. Never put database passwords or Google API secrets in frontend code.</div></div></div>
<?php endif; ?>
<footer class="footer"><span>© 2026 Amaya Log Intelligence</span><span><span class="footer-dot"></span> Operational workspace <span class="footer-sep">·</span> <a href="?view=settings">Settings</a></span></footer>
</section></main></div>
<div class="toast" id="toast"><i data-lucide="check-circle-2"></i><span id="toastMessage">Done</span></div>
<script src="assets/app.js"></script>
</body></html>
