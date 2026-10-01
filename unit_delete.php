<?php
require_once __DIR__ . '/includes/auth.php';
requireAdmin();
require_once __DIR__ . '/includes/functions.php';

$id = $_GET['id'] ?? null;
if ($id) {
    getDB()->prepare("DELETE FROM units WHERE id = ?")->execute([$id]);
}
header('Location: units.php');
exit;
