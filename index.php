<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';

$pdo = getDB();

// --- Fleet counts ---
$counts = ['healthy'=>0,'at_risk'=>0,'critical'=>0,'under_repair'=>0,'retired'=>0];
foreach ($pdo->query("SELECT status, COUNT(*) c FROM units GROUP BY status") as $row) {
    $counts[$row['status']] = (int)$row['c'];
}
$totalUnits = array_sum($counts);

// --- Open diagnostic logs count ---
$openLogs = (int)$pdo->query("SELECT COUNT(*) c FROM diagnostic_logs WHERE status != 'resolved'")->fetch()['c'];

// --- Units overdue for maintenance ---
$units = $pdo->query("SELECT * FROM units WHERE status != 'retired'")->fetchAll();
$logsByUnit = [];
foreach ($pdo->query("SELECT * FROM diagnostic_logs WHERE status != 'resolved'") as $l) {
    $logsByUnit[$l['unit_id']][] = $l;
}
$riskList = [];
foreach ($units as $u) {
    $risk = computeUnitRisk($u, $logsByUnit[$u['id']] ?? []);
    $riskList[] = array_merge($u, ['risk' => $risk]);
}
usort($riskList, fn($a,$b) => $b['risk']['score'] <=> $a['risk']['score']);
$topRisk = array_slice($riskList, 0, 6);
$overdueCount = count(array_filter($riskList, fn($r) => $r['risk']['days_overdue'] > 0));

// --- Recent diagnostic logs ---
$recentLogs = $pdo->query("SELECT dl.*, u.asset_tag, u.unit_name FROM diagnostic_logs dl
                            JOIN units u ON u.id = dl.unit_id
                            ORDER BY dl.date_reported DESC LIMIT 6")->fetchAll();

$extraScripts = ['assets/js/charts.js'];
?>
<div class="topbar">
    <div>
        <h2>Fleet Dashboard</h2>
        <div class="subtitle">Overview of <?= $totalUnits ?> monitored units — preventive maintenance &amp; diagnostics</div>
    </div>
    <a href="diagnose.php" class="btn btn-primary">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M4 12h4l2-7 4 14 2-7h4"/></svg>
        Run Diagnosis
    </a>
</div>
<div class="content">

    <div class="grid grid-4">
        <div class="card stat-card accent-primary">
            <span class="stat-label">Total Units</span>
            <span class="stat-value"><?= $totalUnits ?></span>
            <span class="stat-sub"><?= $counts['under_repair'] ?> under repair</span>
            <svg class="vital-bg" viewBox="0 0 120 46" fill="none"><path d="M0 23H30L36 6L46 40L54 23H120" stroke="#0E7C86" stroke-width="3"/></svg>
        </div>
        <div class="card stat-card accent-healthy">
            <span class="stat-label">Healthy</span>
            <span class="stat-value"><?= $counts['healthy'] ?></span>
            <span class="stat-sub"><?= $totalUnits ? round($counts['healthy']/$totalUnits*100) : 0 ?>% of fleet</span>
        </div>
        <div class="card stat-card accent-risk">
            <span class="stat-label">At Risk</span>
            <span class="stat-value"><?= $counts['at_risk'] ?></span>
            <span class="stat-sub"><?= $overdueCount ?> overdue for maintenance</span>
        </div>
        <div class="card stat-card accent-critical">
            <span class="stat-label">Critical / Open Issues</span>
            <span class="stat-value"><?= $counts['critical'] ?></span>
            <span class="stat-sub"><?= $openLogs ?> unresolved diagnostic logs</span>
        </div>
    </div>

    <div class="grid grid-2 mt-16">
        <div class="card">
            <div class="card-header">
                <h3>Fleet Status Distribution</h3>
            </div>
            <div class="chart-wrap sm"><canvas id="statusChart"></canvas></div>
        </div>
        <div class="card">
            <div class="card-header">
                <h3>Most Predicted Issues</h3>
                <a href="reports.php" class="muted-link">Full report →</a>
            </div>
            <div class="chart-wrap sm"><canvas id="causesChart"></canvas></div>
        </div>
    </div>

    <div class="grid grid-2 mt-16">
        <div class="card">
            <div class="card-header">
                <h3>Units Needing Attention</h3>
                <a href="units.php" class="muted-link">View all →</a>
            </div>
            <?php if (empty($topRisk) || $topRisk[0]['risk']['score'] === 0): ?>
                <div class="empty-state">All monitored units are currently within healthy parameters.</div>
            <?php else: ?>
            <table>
                <thead><tr><th>Unit</th><th>Status</th><th>Risk</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($topRisk as $r): if ($r['risk']['score'] <= 0) continue; ?>
                    <tr>
                        <td><span class="asset-tag"><?= e($r['asset_tag']) ?></span><br><?= e($r['unit_name']) ?></td>
                        <td><span class="badge <?= statusBadgeClass($r['status']) ?>"><?= e(str_replace('_',' ',$r['status'])) ?></span></td>
                        <td style="width:120px;">
                            <div class="risk-bar-track"><div class="risk-bar-fill" style="width:<?= $r['risk']['score'] ?>%;background:var(--<?= $r['risk']['level']==='critical'?'critical':($r['risk']['level']==='high'?'high':($r['risk']['level']==='medium'?'at-risk':'healthy')) ?>);"></div></div>
                        </td>
                        <td><a href="unit_view.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline">View</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-header">
                <h3>Recent Diagnostic Logs</h3>
                <a href="logs.php" class="muted-link">View all →</a>
            </div>
            <?php if (empty($recentLogs)): ?>
                <div class="empty-state">No diagnostic logs yet. Run a diagnosis to get started.</div>
            <?php else: ?>
            <table>
                <thead><tr><th>Unit</th><th>Prediction</th><th>Severity</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($recentLogs as $log): ?>
                    <tr>
                        <td><span class="asset-tag"><?= e($log['asset_tag']) ?></span></td>
                        <td><?= e($log['top_prediction']) ?></td>
                        <td><span class="badge <?= severityBadgeClass($log['predicted_severity']) ?>"><?= e($log['predicted_severity']) ?></span></td>
                        <td><span class="badge badge-repair"><?= e(str_replace('_',' ',$log['status'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
