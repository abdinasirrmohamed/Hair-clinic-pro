<?php
require_once __DIR__ . '/../includes/auth.php';
require_roles(['Administrator', 'Doctor']);
$page_title = 'Record Follow-Up';
require_once __DIR__ . '/../includes/header.php';
$id = (int) ($_GET['id'] ?? 0);
$patients = $conn->query('SELECT id, full_name FROM patients p WHERE 1=1 ' . doctor_patient_filter('p') . ' ORDER BY full_name')->fetch_all(MYSQLI_ASSOC);
$treatments = $conn->query('SELECT t.id, t.treatment_name, p.full_name FROM treatments t JOIN patients p ON p.id = t.patient_id WHERE 1=1 ' . doctor_patient_filter('p') . ' ORDER BY t.treatment_date DESC')->fetch_all(MYSQLI_ASSOC);
$stmt = $conn->prepare('SELECT * FROM followups WHERE id = ?');
$stmt->bind_param('i', $id);
$followup = fetch_one($stmt);
if (!$followup) {
    flash('danger', 'Follow-up not found.');
    redirect('/followups/view.php');
}
require_patient_assignment((int) $followup['patient_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $treatment_id = $_POST['treatment_id'] !== '' ? (int) $_POST['treatment_id'] : null;
    $patient_id = (int) $_POST['patient_id'];
    require_patient_assignment($patient_id);
    $stmt = $conn->prepare('UPDATE followups SET patient_id = ?, treatment_id = ?, followup_date = ?, result = ?, status = ? WHERE id = ?');
    $stmt->bind_param('iisssi', $patient_id, $treatment_id, $_POST['followup_date'], $_POST['result'], $_POST['status'], $id);
    $stmt->execute();
    flash('success', 'Follow-up result updated successfully.');
    redirect('/followups/view.php');
}
?>
<div class="page-head"><h1 class="h3 mb-0">Record Follow-Up Results</h1></div>
<form class="form-panel" method="post">
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Patient</label><select class="form-select" name="patient_id"><?php foreach ($patients as $p): ?><option value="<?= $p['id'] ?>" <?= (int) $followup['patient_id'] === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['full_name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6"><label class="form-label">Related Treatment</label><select class="form-select" name="treatment_id"><option value="">None</option><?php foreach ($treatments as $t): ?><option value="<?= $t['id'] ?>" <?= (int) ($followup['treatment_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['full_name'] . ' - ' . $t['treatment_name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6"><label class="form-label">Follow-Up Date</label><input class="form-control" type="date" name="followup_date" value="<?= e($followup['followup_date']) ?>" required></div>
        <div class="col-md-6"><label class="form-label">Status</label><select class="form-select" name="status"><?php foreach (['Scheduled','Done','Missed'] as $s): ?><option <?= $followup['status'] === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></div>
        <div class="col-12"><label class="form-label">Result / Notes</label><textarea class="form-control" name="result" rows="4"><?= e($followup['result']) ?></textarea></div>
    </div>
    <div class="mt-4"><button class="btn btn-primary">Update Follow-Up</button> <a class="btn btn-secondary" href="view.php">Cancel</a></div>
</form>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
