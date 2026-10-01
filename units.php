<?php
$pageTitle = 'PC Units';
require_once __DIR__ . '/includes/header.php';
$pdo = getDB();

$units = $pdo->query("SELECT u.*, l.name AS location_name FROM units u
                       LEFT JOIN locations l ON l.id = u.location_id
                       ORDER BY u.asset_tag ASC")->fetchAll();

$logsByUnit = [];
foreach ($pdo->query("SELECT * FROM diagnostic_logs WHERE status != 'resolved'") as $l) {
    $logsByUnit[$l['unit_id']][] = $l;
}
?>
<div class="topbar">
    <div>
        <h2>PC Units</h2>
        <div class="subtitle"><?= count($units) ?> units registered in the system</div>
    </div>
    <a href="unit_form.php" class="btn btn-primary">+ Add Unit</a>
</div>
<div class="content">
    <div class="card">
        <div class="page-actions">
            <input type="text" placeholder="Search by asset tag, name, or location…" data-table-filter="#unitsTable" style="max-width:320px;">
        </div>
        <table id="unitsTable">
            <thead>
                <tr><th>Asset Tag</th><th>Unit</th><th>Location</th><th>Specs</th><th>Status</th><th>Last Maintenance</th><th>Risk</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($units as $u):
                $risk = computeUnitRisk($u, $logsByUnit[$u['id']] ?? []);
                $riskVar = $risk['level']==='critical'?'critical':($risk['level']==='high'?'high':($risk['level']==='medium'?'at-risk':'healthy'));
            ?>
                <tr>
                    <td><span class="asset-tag"><?= e($u['asset_tag']) ?></span></td>
                    <td><?= e($u['unit_name']) ?></td>
                    <td><?= e($u['location_name'] ?? '—') ?></td>
                    <td class="text-muted"><?= e($u['cpu']) ?><br><?= e($u['ram']) ?> · <?= e($u['storage']) ?></td>
                    <td><span class="badge <?= statusBadgeClass($u['status']) ?>"><?= e(str_replace('_',' ',$u['status'])) ?></span></td>
                    <td><?= formatDate($u['last_maintenance_date']) ?></td>
                    <td style="width:100px;">
                        <div class="risk-bar-track"><div class="risk-bar-fill" style="width:<?= $risk['score'] ?>%;background:var(--<?= $riskVar ?>);"></div></div>
                    </td>
                    <td class="table-actions">
                        <a href="unit_view.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline">View</a>
                        <a href="unit_form.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline">Edit</a>
                        <?php if (currentUser()['role'] === 'admin'): ?>
                        <a href="unit_delete.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-danger" data-confirm="Delete unit <?= e($u['asset_tag']) ?>? This also removes its logs and maintenance history.">Delete</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
