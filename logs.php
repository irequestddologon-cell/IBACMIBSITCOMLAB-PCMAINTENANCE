<?php
$pageTitle = 'Diagnostic Logs';
require_once __DIR__ . '/includes/header.php';
$pdo = getDB();

$statusFilter = $_GET['status'] ?? '';
$sql = "SELECT dl.*, u.asset_tag, u.unit_name, us.full_name AS reporter FROM diagnostic_logs dl
        JOIN units u ON u.id = dl.unit_id
        LEFT JOIN users us ON us.id = dl.reported_by";
$params = [];
if ($statusFilter) { $sql .= " WHERE dl.status = ?"; $params[] = $statusFilter; }
$sql .= " ORDER BY dl.date_reported DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();
?>
<div class="topbar">
    <div><h2>Diagnostic Logs</h2><div class="subtitle">History of all predictions run across the fleet</div></div>
    <a href="diagnose.php" class="btn btn-primary">+ New Diagnosis</a>
</div>
<div class="content">
    <div class="card">
        <div class="page-actions">
            <input type="text" placeholder="Search…" data-table-filter="#logsTable" style="max-width:280px;">
            <div style="display:flex;gap:8px;">
                <a href="logs.php" class="btn btn-sm <?= $statusFilter===''?'btn-primary':'btn-outline' ?>">All</a>
                <a href="logs.php?status=open" class="btn btn-sm <?= $statusFilter==='open'?'btn-primary':'btn-outline' ?>">Open</a>
                <a href="logs.php?status=in_progress" class="btn btn-sm <?= $statusFilter==='in_progress'?'btn-primary':'btn-outline' ?>">In Progress</a>
                <a href="logs.php?status=resolved" class="btn btn-sm <?= $statusFilter==='resolved'?'btn-primary':'btn-outline' ?>">Resolved</a>
            </div>
        </div>
        <?php if (empty($logs)): ?>
            <div class="empty-state">No diagnostic logs found.</div>
        <?php else: ?>
        <table id="logsTable">
            <thead><tr><th>Date</th><th>Unit</th><th>Prediction</th><th>Severity</th><th>Reported By</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
            <tr>
                <td><?= formatDate($log['date_reported']) ?></td>
                <td><span class="asset-tag"><?= e($log['asset_tag']) ?></span><br><?= e($log['unit_name']) ?></td>
                <td><?= e($log['top_prediction']) ?><?php if($log['notes']): ?><br><span class="text-muted" style="font-size:12px;"><?= e($log['notes']) ?></span><?php endif; ?></td>
                <td><span class="badge <?= severityBadgeClass($log['predicted_severity']) ?>"><?= e($log['predicted_severity']) ?></span></td>
                <td><?= e($log['reporter'] ?? '—') ?></td>
                <td>
                    <form method="POST" action="log_update.php">
                        <input type="hidden" name="log_id" value="<?= $log['id'] ?>">
                        <select name="status" onchange="this.form.submit()">
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
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
