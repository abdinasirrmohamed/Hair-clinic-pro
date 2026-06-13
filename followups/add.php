<?php
require_once __DIR__ . '/../includes/auth.php';
require_roles(['Administrator', 'Doctor']);
$page_title = 'Schedule Follow-Up';
require_once __DIR__ . '/../includes/header.php';
$patients = $conn->query('SELECT id, full_name FROM patients p WHERE 1=1 ' . doctor_patient_filter('p') . ' ORDER BY full_name')->fetch_all(MYSQLI_ASSOC);
$treatments = $conn->query('SELECT t.id, t.treatment_name, p.full_name FROM treatments t JOIN patients p ON p.id = t.patient_id WHERE 1=1 ' . doctor_patient_filter('p') . ' ORDER BY t.treatment_date DESC')->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $treatment_id = $_POST['treatment_id'] !== '' ? (int) $_POST['treatment_id'] : null;
    $patient_id = (int) $_POST['patient_id'];
    require_patient_assignment($patient_id);
    $stmt = $conn->prepare('INSERT INTO followups (patient_id, treatment_id, followup_date, result, status) VALUES (?, ?, ?, ?, ?)');
    $stmt->bind_param('iisss', $patient_id, $treatment_id, $_POST['followup_date'], $_POST['result'], $_POST['status']);
    $stmt->execute();
    flash('success', 'Follow-up saved successfully.');
    redirect('/followups/view.php');
}
?>
<div class="page-head"><h1 class="h3 mb-0">Schedule Follow-Up</h1></div>
<form class="form-panel" method="post">
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Patient</label><select class="form-select" name="patient_id" required><option value="">Select patient</option><?php foreach ($patients as $p): ?><option value="<?= $p['id'] ?>"><?= e($p['full_name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6"><label class="form-label">Related Treatment</label><select class="form-select" name="treatment_id"><option value="">None</option><?php foreach ($treatments as $t): ?><option value="<?= $t['id'] ?>"><?= e($t['full_name'] . ' - ' . $t['treatment_name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6"><label class="form-label">Follow-Up Date</label><input class="form-control" type="date" name="followup_date" required></div>
        <div class="col-md-6"><label class="form-label">Status</label><select class="form-select" name="status"><option>Scheduled</option><option>Done</option><option>Missed</option></select></div>
        <div class="col-12"><label class="form-label">Result / Notes</label><textarea class="form-control" name="result" rows="4"></textarea></div>
    </div>
    <div class="mt-4"><button class="btn btn-primary">Save Follow-Up</button> <a class="btn btn-secondary" href="view.php">Cancel</a></div>
</form>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
