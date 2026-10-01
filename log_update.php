<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $logId = $_POST['log_id'];
    $status = $_POST['status'];
    $unitId = $_POST['unit_id'] ?? null;

    $pdo = getDB();
    $resolvedAt = $status === 'resolved' ? date('Y-m-d H:i:s') : null;
    $pdo->prepare("UPDATE diagnostic_logs SET status=?, resolved_at=? WHERE id=?")
        ->execute([$status, $resolvedAt, $logId]);

    header('Location: ' . ($unitId ? 'unit_view.php?id=' . urlencode($unitId) : 'logs.php'));
    exit;
}
header('Location: logs.php');
exit;
