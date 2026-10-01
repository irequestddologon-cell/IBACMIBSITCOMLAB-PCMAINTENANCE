<?php
require_once __DIR__ . '/auth.php';
requireLogin();
require_once __DIR__ . '/functions.php';
$user = currentUser();
$current = basename($_SERVER['PHP_SELF']);
function navActive($file, $current) { return $file === $current ? 'active' : ''; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? e($pageTitle) . ' — PCMS' : 'PCMS' ?></title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="pulse-wrap">
                <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="20" cy="20" r="19" stroke="#0E7C86" stroke-width="1.5" opacity="0.5"/>
                    <path d="M4 20H12L15 10L20 30L23 20H36" stroke="#3FD1C4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <div>
                <h1>PCMS</h1>
                <span>Fleet Diagnostics</span>
            </div>
        </div>
        <nav class="sidebar-nav">
            <div class="sidebar-section-label">Overview</div>
            <a href="index.php" class="<?= navActive('index.php', $current) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>
                Dashboard
            </a>
            <a href="reports.php" class="<?= navActive('reports.php', $current) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 20V10M12 20V4M20 20v-7"/></svg>
                Analytics
            </a>
            <div class="sidebar-section-label">Fleet</div>
            <a href="units.php" class="<?= navActive('units.php', $current) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="12" rx="1.5"/><path d="M8 20h8M12 16v4"/></svg>
                PC Units
            </a>
            <a href="diagnose.php" class="<?= navActive('diagnose.php', $current) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 12h4l2-7 4 14 2-7h4"/></svg>
                Run Diagnosis
            </a>
            <a href="logs.php" class="<?= navActive('logs.php', $current) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 4h9l3 3v13H6z"/><path d="M9 10h6M9 14h6M9 18h3"/></svg>
                Diagnostic Logs
            </a>
            <a href="maintenance.php" class="<?= navActive('maintenance.php', $current) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a4 4 0 0 1-5.4 5.4L4 17l3 3 5.3-5.3a4 4 0 0 1 5.4-5.4L21 6l-3-3z"/></svg>
                Maintenance
            </a>
            <?php if ($user['role'] === 'admin'): ?>
            <div class="sidebar-section-label">Administration</div>
            <a href="users.php" class="<?= navActive('users.php', $current) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.5-6 8-6s8 2 8 6"/></svg>
                User Accounts
            </a>
            <?php endif; ?>
        </nav>
        <div class="sidebar-footer">
            <span class="user-name"><?= e($user['full_name']) ?></span>
            <span class="user-role"><?= e($user['role']) ?></span>
            <a href="logout.php" class="logout">Log out</a>
        </div>
    </aside>
    <div class="main">
