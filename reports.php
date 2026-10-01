<?php
$pageTitle = 'Analytics';
require_once __DIR__ . '/includes/header.php';
$pdo = getDB();

$totalUnits = (int)$pdo->query("SELECT COUNT(*) c FROM units")->fetch()['c'];
$totalLogs = (int)$pdo->query("SELECT COUNT(*) c FROM diagnostic_logs")->fetch()['c'];
$resolvedLogs = (int)$pdo->query("SELECT COUNT(*) c FROM diagnostic_logs WHERE status='resolved'")->fetch()['c'];
$avgResolutionDays = $pdo->query("SELECT AVG(DATEDIFF(resolved_at, date_reported)) a FROM diagnostic_logs WHERE resolved_at IS NOT NULL")->fetch()['a'];
$totalMaint = (int)$pdo->query("SELECT COUNT(*) c FROM maintenance_records")->fetch()['c'];

$extraScripts = ['assets/js/charts.js'];
?>
<div class="topbar">
    <div><h2>Analytics &amp; Statistics</h2><div class="subtitle">Fleet-wide trends to guide preventive maintenance planning</div></div>
</div>
<div class="content">
    <div class="grid grid-4">
        <div class="card stat-card accent-primary"><span class="stat-label">Total Units</span><span class="stat-value"><?= $totalUnits ?></span></div>
        <div class="card stat-card accent-primary"><span class="stat-label">Diagnostic Logs</span><span class="stat-value"><?= $totalLogs ?></span><span class="stat-sub"><?= $resolvedLogs ?> resolved</span></div>
        <div class="card stat-card accent-healthy"><span class="stat-label">Avg. Resolution Time</span><span class="stat-value"><?= $avgResolutionDays ? round($avgResolutionDays,1) : '—' ?></span><span class="stat-sub">days</span></div>
        <div class="card stat-card accent-primary"><span class="stat-label">Maintenance Records</span><span class="stat-value"><?= $totalMaint ?></span></div>
    </div>

    <div class="grid grid-2 mt-16">
        <div class="card">
            <div class="card-header"><h3>Diagnostic Logs — Last 6 Months</h3></div>
            <div class="chart-wrap"><canvas id="trendChart"></canvas></div>
        </div>
        <div class="card">
            <div class="card-header"><h3>Maintenance Compliance</h3></div>
            <div class="chart-wrap"><canvas id="complianceChart"></canvas></div>
        </div>
    </div>

    <div class="grid grid-2 mt-16">
        <div class="card">
            <div class="card-header"><h3>Top Predicted Causes</h3></div>
            <div class="chart-wrap"><canvas id="causesChart"></canvas></div>
        </div>
        <div class="card">
            <div class="card-header"><h3>Symptoms by Category</h3></div>
            <div class="chart-wrap"><canvas id="categoryChart"></canvas></div>
        </div>
    </div>

    <div class="card mt-16">
        <div class="card-header"><h3>Fleet Status Breakdown</h3></div>
        <div class="chart-wrap sm"><canvas id="statusChart"></canvas></div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
