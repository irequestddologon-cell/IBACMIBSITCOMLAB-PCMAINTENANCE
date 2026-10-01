<?php
$pageTitle = 'Maintenance';
require_once __DIR__ . '/includes/header.php';
$pdo = getDB();

$records = $pdo->query("SELECT m.*, u.asset_tag, u.unit_name, us.full_name FROM maintenance_records m
                         JOIN units u ON u.id = m.unit_id
                         LEFT JOIN users us ON us.id = m.performed_by
                         ORDER BY m.maintenance_date DESC")->fetchAll();

// Units overdue, for the "needs scheduling" panel
$units = $pdo->query("SELECT * FROM units WHERE status != 'retired'")->fetchAll();
$overdue = [];
foreach ($units as $u) {
    $risk = computeUnitRisk($u, []);
    if ($risk['days_overdue'] > 0) $overdue[] = array_merge($u, ['days_overdue' => $risk['days_overdue']]);
}
usort($overdue, fn($a,$b) => $b['days_overdue'] <=> $a['days_overdue']);
?>
<div class="topbar">
    <div><h2>Preventive Maintenance</h2><div class="subtitle">Scheduled and completed maintenance across the fleet</div></div>
</div>
<div class="content">
    <div class="card" style="margin-bottom:18px;">
        <div class="card-header"><h3>Units Due / Overdue for Maintenance</h3></div>
        <?php if (empty($overdue)): ?>
            <div class="empty-state">No units are currently overdue. Nice work!</div>
        <?php else: ?>
        <table>
            <thead><tr><th>Unit</th><th>Last Maintenance</th><th>Days Overdue</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($overdue as $u): ?>
            <tr>
                <td><span class="asset-tag"><?= e($u['asset_tag']) ?></span> <?= e($u['unit_name']) ?></td>
                <td><?= formatDate($u['last_maintenance_date']) ?></td>
                <td><span class="badge badge-critical"><?= $u['days_overdue'] ?> days</span></td>
                <td><a href="unit_view.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline">Log Maintenance</a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-header"><h3>Maintenance History</h3></div>
        <?php if (empty($records)): ?>
            <div class="empty-state">No maintenance records logged yet.</div>
        <?php else: ?>
        <table>
            <thead><tr><th>Date</th><th>Unit</th><th>Type</th><th>Performed By</th><th>Notes</th></tr></thead>
            <tbody>
            <?php foreach ($records as $r): ?>
            <tr>
                <td><?= formatDate($r['maintenance_date']) ?></td>
                <td><span class="asset-tag"><?= e($r['asset_tag']) ?></span> <?= e($r['unit_name']) ?></td>
                <td><?= ucfirst(str_replace('_',' ',$r['maintenance_type'])) ?></td>
                <td><?= e($r['full_name'] ?? '—') ?></td>
                <td class="text-muted"><?= e($r['notes']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
