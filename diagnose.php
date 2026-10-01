<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();
require_once __DIR__ . '/includes/functions.php';
$pdo = getDB();

$units = $pdo->query("SELECT id, asset_tag, unit_name FROM units WHERE status != 'retired' ORDER BY asset_tag")->fetchAll();
$symptoms = $pdo->query("SELECT * FROM symptoms ORDER BY category, label")->fetchAll();
$grouped = [];
foreach ($symptoms as $s) { $grouped[$s['category']][] = $s; }

$results = null;
$selectedUnit = $_GET['unit_id'] ?? '';
$selectedSymptoms = [];
$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedUnit = $_POST['unit_id'];
    $selectedSymptoms = array_map('intval', $_POST['symptoms'] ?? []);
    $notes = trim($_POST['notes'] ?? '');

    if (!empty($selectedSymptoms)) {
        $results = runDiagnosis($selectedSymptoms);
    }

    if (isset($_POST['save_log']) && $results) {
        $top = $results[0];
        $user = currentUser();
        $stmt = $pdo->prepare("INSERT INTO diagnostic_logs (unit_id, reported_by, top_prediction, predicted_severity, notes)
                               VALUES (?,?,?,?,?)");
        $stmt->execute([$selectedUnit, $user['id'], $top['cause'], $top['severity'], $notes]);
        $logId = $pdo->lastInsertId();
        $ins = $pdo->prepare("INSERT INTO diagnostic_log_symptoms (log_id, symptom_id) VALUES (?,?)");
        foreach ($selectedSymptoms as $sid) $ins->execute([$logId, $sid]);

        // bump unit status if severity is high/critical
        if (in_array($top['severity'], ['high','critical'])) {
            $newStatus = $top['severity'] === 'critical' ? 'critical' : 'at_risk';
            $pdo->prepare("UPDATE units SET status=? WHERE id=? AND status NOT IN ('under_repair','retired')")
                ->execute([$newStatus, $selectedUnit]);
        }
        $saved = true;
    }
}

$categoryLabels = [
    'performance'=>'Performance','hardware'=>'Hardware','power'=>'Power',
    'display'=>'Display','network'=>'Network','storage'=>'Storage','software'=>'Software','noise'=>'Noise'
];

$pageTitle = 'Run Diagnosis';
require_once __DIR__ . '/includes/header.php';
?>
<div class="topbar">
    <div><h2>Run Diagnosis</h2><div class="subtitle">Select a unit and observed symptoms to predict likely issues</div></div>
</div>
<div class="content">
    <?php if ($saved): ?>
        <div class="alert alert-success">Diagnostic log saved. The unit's status and the fleet dashboard have been updated.</div>
    <?php endif; ?>

    <div class="grid" style="grid-template-columns: 1.3fr 1fr; align-items:start;">
        <div class="card">
            <form method="POST">
                <div class="form-row">
                    <label>PC Unit</label>
                    <select name="unit_id" required>
                        <option value="">— Select a unit —</option>
                        <?php foreach ($units as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $selectedUnit==$u['id']?'selected':'' ?>><?= e($u['asset_tag']) ?> — <?= e($u['unit_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <label class="mt-16">Observed Symptoms (select all that apply)</label>
                <?php foreach ($grouped as $cat => $items): ?>
                    <div class="sidebar-section-label" style="margin-left:0;color:var(--text-muted);"><?= $categoryLabels[$cat] ?? ucfirst($cat) ?></div>
                    <div class="checkbox-grid mt-16" style="margin-top:6px;margin-bottom:14px;">
                        <?php foreach ($items as $s): ?>
                        <div class="checkbox-item">
                            <input type="checkbox" id="s<?= $s['id'] ?>" name="symptoms[]" value="<?= $s['id'] ?>"
                                <?= in_array($s['id'], $selectedSymptoms) ? 'checked' : '' ?>>
                            <label for="s<?= $s['id'] ?>"><?= e($s['label']) ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>

                <div class="form-row">
                    <label>Additional Notes (optional)</label>
                    <textarea name="notes" rows="2" placeholder="Who reported it, when it started, etc."></textarea>
                </div>

                <div style="display:flex;gap:10px;">
                    <button type="submit" name="preview" value="1" class="btn btn-outline">Preview Prediction</button>
                    <button type="submit" name="save_log" value="1" class="btn btn-primary">Save Diagnostic Log</button>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-header"><h3>Predicted Causes</h3></div>
            <?php if ($results === null): ?>
                <div class="empty-state">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 12h4l2-7 4 14 2-7h4"/></svg>
                    <div>Select a unit and symptoms, then click Preview Prediction.</div>
                </div>
            <?php elseif (empty($results)): ?>
                <div class="empty-state">No matching rules found for the selected symptoms.</div>
            <?php else: ?>
                <?php foreach ($results as $i => $r): ?>
                <div class="prediction-item">
                    <div>
                        <span class="rank">#<?= $i+1 ?></span>
                        <h4><?= e($r['cause']) ?></h4>
                        <p><?= e($r['action']) ?></p>
                        <span class="badge <?= severityBadgeClass($r['severity']) ?> mt-16" style="margin-top:8px;display:inline-flex;"><?= e($r['severity']) ?></span>
                    </div>
                    <span class="confidence-pill"><?= $r['confidence'] ?>%</span>
                </div>
                <?php endforeach; ?>
                <p class="text-muted" style="font-size:12px;">Confidence is relative to the top-scoring cause based on matched symptom rules, not an absolute probability.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
