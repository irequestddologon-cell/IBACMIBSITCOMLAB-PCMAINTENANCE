<?php
$pageTitle = 'Unit Details';
require_once __DIR__ . '/includes/header.php';
$pdo = getDB();

$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT u.*, l.name AS location_name FROM units u LEFT JOIN locations l ON l.id=u.location_id WHERE u.id=?");
$stmt->execute([$id]);
$unit = $stmt->fetch();
if (!$unit) { echo '<div class="content"><div class="card">Unit not found.</div></div>'; require __DIR__.'/includes/footer.php'; exit; }

$stmt = $pdo->prepare("SELECT * FROM diagnostic_logs WHERE unit_id=? ORDER BY date_reported DESC");
$stmt->execute([$id]);
$logs = $stmt->fetchAll();
$openLogs = array_filter($logs, fn($l) => $l['status'] !== 'resolved');

$risk = computeUnitRisk($unit, $openLogs);
$riskVar = $risk['level']==='critical'?'critical':($risk['level']==='high'?'high':($risk['level']==='medium'?'at-risk':'healthy'));

$stmt = $pdo->prepare("SELECT m.*, us.full_name FROM maintenance_records m LEFT JOIN users us ON us.id=m.performed_by
                       WHERE unit_id=? ORDER BY maintenance_date DESC");
$stmt->execute([$id]);
$maintRecords = $stmt->fetchAll();
?>
<div class="topbar">
    <div>
        <h2><?= e($unit['unit_name']) ?> <span class="asset-tag" style="font-size:14px;"><?= e($unit['asset_tag']) ?></span></h2>
        <div class="subtitle"><?= e($unit['location_name'] ?? 'No location set') ?></div>
    </div>
    <div style="display:flex;gap:10px;">
        <a href="diagnose.php?unit_id=<?= $unit['id'] ?>" class="btn btn-outline">Run Diagnosis</a>
        <a href="unit_form.php?id=<?= $unit['id'] ?>" class="btn btn-primary">Edit Unit</a>
    </div>
</div>
<div class="content">
    <div class="grid grid-3">
        <div class="card">
            <div class="card-header"><h3>Specifications</h3></div>
            <table>
                <tr><td class="text-muted">Status</td><td><span class="badge <?= statusBadgeClass($unit['status']) ?>"><?= e(str_replace('_',' ',$unit['status'])) ?></span></td></tr>
                <tr><td class="text-muted">CPU</td><td><?= e($unit['cpu']) ?: '—' ?></td></tr>
                <tr><td class="text-muted">RAM</td><td><?= e($unit['ram']) ?: '—' ?></td></tr>
                <tr><td class="text-muted">Storage</td><td><?= e($unit['storage']) ?: '—' ?></td></tr>
                <tr><td class="text-muted">OS</td><td><?= e($unit['os']) ?: '—' ?></td></tr>
                <tr><td class="text-muted">Purchased</td><td><?= formatDate($unit['purchase_date']) ?></td></tr>
            </table>
        </div>
        <div class="card">
            <div class="card-header"><h3>Preventive Maintenance Risk</h3></div>
            <div class="stat-value" style="color:var(--<?= $riskVar ?>);font-size:34px;"><?= $risk['score'] ?><span style="font-size:15px;color:var(--text-muted);">/100</span></div>
            <div class="risk-bar-track mt-16"><div class="risk-bar-fill" style="width:<?= $risk['score'] ?>%;background:var(--<?= $riskVar ?>);"></div></div>
            <p class="text-muted mt-16" style="font-size:12.5px;">
                Last maintenance: <?= formatDate($unit['last_maintenance_date']) ?><br>
                <?= $risk['days_overdue'] > 0 ? $risk['days_overdue'].' days overdue for scheduled maintenance.' : 'Within scheduled maintenance window.' ?><br>
                <?= count($openLogs) ?> unresolved diagnostic log(s) contributing to score.
            </p>
        </div>
        <div class="card">
            <div class="card-header"><h3>Log Preventive Maintenance</h3></div>
            <form method="POST" action="maintenance_add.php">
                <input type="hidden" name="unit_id" value="<?= $unit['id'] ?>">
                <div class="form-row">
                    <label>Type</label>
                    <select name="maintenance_type">
                        <option value="cleaning">Cleaning</option>
                        <option value="inspection">Inspection</option>
                        <option value="repair">Repair</option>
                        <option value="upgrade">Upgrade</option>
                        <option value="os_reimage">OS Reimage</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="form-row"><label>Date</label><input type="date" name="maintenance_date" value="<?= date('Y-m-d') ?>" required></div>
                <div class="form-row"><label>Notes</label><textarea name="notes" rows="2" placeholder="What was done…"></textarea></div>
                <button type="submit" class="btn btn-primary btn-sm">Save Record</button>
            </form>
        </div>
    </div>

    <div class="grid grid-2 mt-16">
        <div class="card">
            <div class="card-header"><h3>Diagnostic History</h3></div>
            <?php if (empty($logs)): ?>
                <div class="empty-state">No diagnostic checks logged for this unit yet.</div>
            <?php else: ?>
            <table>
                <thead><tr><th>Date</th><th>Prediction</th><th>Severity</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?= formatDate($log['date_reported']) ?></td>
                    <td><?= e($log['top_prediction']) ?></td>
                    <td><span class="badge <?= severityBadgeClass($log['predicted_severity']) ?>"><?= e($log['predicted_severity']) ?></span></td>
                    <td>
                        <form method="POST" action="log_update.php" style="display:inline;">
                            <input type="hidden" name="log_id" value="<?= $log['id'] ?>">
                            <input type="hidden" name="unit_id" value="<?= $unit['id'] ?>">
                            <select name="status" onchange="this.form.submit()" class="btn-sm" style="padding:4px 6px;">
                                <?php foreach (['open','in_progress','resolved'] as $s): ?>
                                <option value="<?= $s ?>" <?= $log['status']===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <div class="card">
            <div class="card-header"><h3>Maintenance History</h3></div>
            <?php if (empty($maintRecords)): ?>
                <div class="empty-state">No maintenance records yet.</div>
            <?php else: ?>
            <table>
                <thead><tr><th>Date</th><th>Type</th><th>By</th><th>Notes</th></tr></thead>
                <tbody>
                <?php foreach ($maintRecords as $m): ?>
                <tr>
                    <td><?= formatDate($m['maintenance_date']) ?></td>
                    <td><?= ucfirst(str_replace('_',' ',$m['maintenance_type'])) ?></td>
                    <td><?= e($m['full_name'] ?? '—') ?></td>
                    <td class="text-muted"><?= e($m['notes']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
