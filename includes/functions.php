<?php
require_once __DIR__ . '/../config/database.php';

/**
 * Run the rule-based diagnostic engine for a set of selected symptom IDs.
 * Returns an array of causes sorted by score desc:
 *   [ ['cause' => ..., 'action' => ..., 'severity' => ..., 'score' => ..., 'matched_symptoms' => n], ... ]
 */
function runDiagnosis(array $symptomIds) {
    if (empty($symptomIds)) return [];
    $pdo = getDB();
    $placeholders = implode(',', array_fill(0, count($symptomIds), '?'));
    $stmt = $pdo->prepare("SELECT probable_cause, recommended_action, weight, severity
                           FROM diagnosis_rules WHERE symptom_id IN ($placeholders)");
    $stmt->execute($symptomIds);
    $rows = $stmt->fetchAll();

    $severityRank = ['low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];
    $causes = [];
    foreach ($rows as $r) {
        $key = $r['probable_cause'];
        if (!isset($causes[$key])) {
            $causes[$key] = [
                'cause' => $r['probable_cause'],
                'action' => $r['recommended_action'],
                'severity' => $r['severity'],
                'score' => 0,
                'matched_symptoms' => 0,
            ];
        }
        $causes[$key]['score'] += (int)$r['weight'];
        $causes[$key]['matched_symptoms']++;
        // keep the highest severity seen for this cause
        if ($severityRank[$r['severity']] > $severityRank[$causes[$key]['severity']]) {
            $causes[$key]['severity'] = $r['severity'];
        }
    }

    $result = array_values($causes);
    usort($result, fn($a, $b) => $b['score'] <=> $a['score']);

    // Normalize score into a rough confidence percentage relative to top score
    $maxScore = $result[0]['score'] ?? 1;
    foreach ($result as &$r) {
        $r['confidence'] = $maxScore > 0 ? round(($r['score'] / $maxScore) * 100) : 0;
    }
    return $result;
}

/**
 * Compute a preventive-maintenance risk score (0-100) for a unit based on:
 *  - days overdue vs its maintenance interval
 *  - number of open/unresolved diagnostic logs
 *  - severity of those logs
 * Returns ['score' => int, 'level' => 'low'|'medium'|'high'|'critical', 'days_overdue' => int]
 */
function computeUnitRisk(array $unit, array $openLogs) {
    $score = 0;

    if (!empty($unit['last_maintenance_date'])) {
        $lastDate = new DateTime($unit['last_maintenance_date']);
        $today = new DateTime();
        $daysSince = (int)$today->diff($lastDate)->days;
        $interval = (int)($unit['maintenance_interval_days'] ?: 90);
        $daysOverdue = $daysSince - $interval;
        if ($daysOverdue > 0) {
            $score += min(40, $daysOverdue); // cap contribution at 40
        }
    } else {
        $daysOverdue = 999;
        $score += 40;
    }

    $severityPoints = ['low' => 5, 'medium' => 10, 'high' => 20, 'critical' => 35];
    foreach ($openLogs as $log) {
        $score += $severityPoints[$log['predicted_severity']] ?? 5;
    }

    $score = min(100, $score);

    if ($score >= 70) $level = 'critical';
    elseif ($score >= 40) $level = 'high';
    elseif ($score >= 15) $level = 'medium';
    else $level = 'low';

    return ['score' => $score, 'level' => $level, 'days_overdue' => $daysOverdue ?? 0];
}

function statusBadgeClass($status) {
    return match ($status) {
        'healthy' => 'badge-healthy',
        'at_risk' => 'badge-at-risk',
        'critical' => 'badge-critical',
        'under_repair' => 'badge-repair',
        'retired' => 'badge-retired',
        default => 'badge-healthy',
    };
}

function severityBadgeClass($sev) {
    return match ($sev) {
        'low' => 'badge-healthy',
        'medium' => 'badge-at-risk',
        'high' => 'badge-high',
        'critical' => 'badge-critical',
        default => 'badge-healthy',
    };
}

function formatDate($date) {
    if (!$date) return '—';
    return date('M j, Y', strtotime($date));
}

function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}
