<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');
$pdo = getDB();
$type = $_GET['type'] ?? '';

switch ($type) {

    case 'status_distribution': {
        $stmt = $pdo->query("SELECT status, COUNT(*) c FROM units GROUP BY status");
        $rows = $stmt->fetchAll();
        echo json_encode($rows);
        break;
    }

    case 'top_causes': {
        // most frequent top_prediction values from diagnostic_logs
        $stmt = $pdo->query("SELECT top_prediction, COUNT(*) c FROM diagnostic_logs
                              WHERE top_prediction IS NOT NULL AND top_prediction != ''
                              GROUP BY top_prediction ORDER BY c DESC LIMIT 8");
        echo json_encode($stmt->fetchAll());
        break;
    }

    case 'symptom_categories': {
        $stmt = $pdo->query("SELECT s.category, COUNT(*) c
                              FROM diagnostic_log_symptoms dls
                              JOIN symptoms s ON s.id = dls.symptom_id
                              GROUP BY s.category ORDER BY c DESC");
        echo json_encode($stmt->fetchAll());
        break;
    }

    case 'monthly_trend': {
        // number of diagnostic logs per month, last 6 months
        $stmt = $pdo->query("SELECT DATE_FORMAT(date_reported, '%Y-%m') ym, COUNT(*) c
                              FROM diagnostic_logs
                              WHERE date_reported >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                              GROUP BY ym ORDER BY ym ASC");
        echo json_encode($stmt->fetchAll());
        break;
    }

    case 'maintenance_compliance': {
        $stmt = $pdo->query("SELECT id, last_maintenance_date, maintenance_interval_days FROM units WHERE status != 'retired'");
        $units = $stmt->fetchAll();
        $onTime = 0; $overdue = 0;
        foreach ($units as $u) {
            if (empty($u['last_maintenance_date'])) { $overdue++; continue; }
            $days = (new DateTime())->diff(new DateTime($u['last_maintenance_date']))->days;
            if ($days > (int)$u['maintenance_interval_days']) $overdue++; else $onTime++;
        }
        echo json_encode(['on_time' => $onTime, 'overdue' => $overdue]);
        break;
    }

    default:
        echo json_encode(['error' => 'Unknown stat type']);
}
