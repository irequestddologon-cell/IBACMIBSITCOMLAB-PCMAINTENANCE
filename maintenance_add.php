<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $unitId = $_POST['unit_id'];
    $type = $_POST['maintenance_type'];
    $date = $_POST['maintenance_date'];
    $notes = trim($_POST['notes']);
    $user = currentUser();

    $pdo = getDB();
    $pdo->prepare("INSERT INTO maintenance_records (unit_id, performed_by, maintenance_type, maintenance_date, notes)
                   VALUES (?,?,?,?,?)")->execute([$unitId, $user['id'], $type, $date, $notes]);

    // Update the unit's last_maintenance_date and, if it was at_risk/critical, restore to healthy
    $stmt = $pdo->prepare("SELECT status FROM units WHERE id=?");
    $stmt->execute([$unitId]);
    $current = $stmt->fetch();
    $newStatus = in_array($current['status'], ['at_risk','critical']) ? 'healthy' : $current['status'];

    $pdo->prepare("UPDATE units SET last_maintenance_date=?, status=? WHERE id=?")
        ->execute([$date, $newStatus, $unitId]);

    header('Location: unit_view.php?id=' . urlencode($unitId));
    exit;
}
header('Location: units.php');
exit;
