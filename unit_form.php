<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();
require_once __DIR__ . '/includes/functions.php';
$pdo = getDB();

$id = $_GET['id'] ?? null;
$unit = ['asset_tag'=>'','unit_name'=>'','location_id'=>'','cpu'=>'','ram'=>'','storage'=>'','os'=>'',
         'purchase_date'=>'','status'=>'healthy','maintenance_interval_days'=>90,'last_maintenance_date'=>''];
$error = '';

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM units WHERE id = ?");
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if ($found) $unit = $found;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'asset_tag' => trim($_POST['asset_tag']),
        'unit_name' => trim($_POST['unit_name']),
        'location_id' => $_POST['location_id'] ?: null,
        'cpu' => trim($_POST['cpu']),
        'ram' => trim($_POST['ram']),
        'storage' => trim($_POST['storage']),
        'os' => trim($_POST['os']),
        'purchase_date' => $_POST['purchase_date'] ?: null,
        'status' => $_POST['status'],
        'maintenance_interval_days' => (int)$_POST['maintenance_interval_days'],
        'last_maintenance_date' => $_POST['last_maintenance_date'] ?: null,
    ];

    if ($data['asset_tag'] === '' || $data['unit_name'] === '') {
        $error = 'Asset tag and unit name are required.';
    } else {
        try {
            if ($id) {
                $sql = "UPDATE units SET asset_tag=?, unit_name=?, location_id=?, cpu=?, ram=?, storage=?, os=?,
                        purchase_date=?, status=?, maintenance_interval_days=?, last_maintenance_date=? WHERE id=?";
                $pdo->prepare($sql)->execute([...array_values($data), $id]);
            } else {
                $cols = implode(',', array_keys($data));
                $ph = implode(',', array_fill(0, count($data), '?'));
                $pdo->prepare("INSERT INTO units ($cols) VALUES ($ph)")->execute(array_values($data));
            }
            header('Location: units.php');
            exit;
        } catch (PDOException $e) {
            $error = str_contains($e->getMessage(), 'Duplicate') ? 'Asset tag already exists.' : 'Save failed: ' . $e->getMessage();
        }
    }
    $unit = $data;
}

$locations = $pdo->query("SELECT * FROM locations ORDER BY name")->fetchAll();
$pageTitle = $id ? 'Edit Unit' : 'Add Unit';
require_once __DIR__ . '/includes/header.php';
?>
<div class="topbar">
    <div><h2><?= $id ? 'Edit Unit' : 'Add New Unit' ?></h2><div class="subtitle">Register or update a PC's inventory details</div></div>
    <a href="units.php" class="btn btn-outline">← Back to Units</a>
</div>
<div class="content">
    <div class="card" style="max-width:720px;">
        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
        <form method="POST">
            <div class="form-grid">
                <div class="form-row">
                    <label>Asset Tag</label>
                    <input type="text" name="asset_tag" value="<?= e($unit['asset_tag']) ?>" placeholder="PC-016" required>
                </div>
                <div class="form-row">
                    <label>Unit Name</label>
                    <input type="text" name="unit_name" value="<?= e($unit['unit_name']) ?>" placeholder="Lab1-PC06" required>
                </div>
            </div>
            <div class="form-grid">
                <div class="form-row">
                    <label>Location</label>
                    <select name="location_id">
                        <option value="">— None —</option>
                        <?php foreach ($locations as $l): ?>
                        <option value="<?= $l['id'] ?>" <?= $unit['location_id']==$l['id']?'selected':'' ?>><?= e($l['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <label>Status</label>
                    <select name="status">
                        <?php foreach (['healthy','at_risk','critical','under_repair','retired'] as $s): ?>
                        <option value="<?= $s ?>" <?= $unit['status']===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-grid">
                <div class="form-row"><label>CPU</label><input type="text" name="cpu" value="<?= e($unit['cpu']) ?>" placeholder="Intel Core i5-10400"></div>
                <div class="form-row"><label>RAM</label><input type="text" name="ram" value="<?= e($unit['ram']) ?>" placeholder="8GB DDR4"></div>
            </div>
            <div class="form-grid">
                <div class="form-row"><label>Storage</label><input type="text" name="storage" value="<?= e($unit['storage']) ?>" placeholder="256GB SSD"></div>
                <div class="form-row"><label>Operating System</label><input type="text" name="os" value="<?= e($unit['os']) ?>" placeholder="Windows 11"></div>
            </div>
            <div class="form-grid">
                <div class="form-row"><label>Purchase Date</label><input type="date" name="purchase_date" value="<?= e($unit['purchase_date']) ?>"></div>
                <div class="form-row"><label>Maintenance Interval (days)</label><input type="number" name="maintenance_interval_days" value="<?= e($unit['maintenance_interval_days']) ?>" min="7"></div>
            </div>
            <div class="form-row">
                <label>Last Maintenance Date</label>
                <input type="date" name="last_maintenance_date" value="<?= e($unit['last_maintenance_date']) ?>">
            </div>
            <button type="submit" class="btn btn-primary"><?= $id ? 'Save Changes' : 'Add Unit' ?></button>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
