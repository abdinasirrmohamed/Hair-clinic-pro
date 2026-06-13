<?php
require_once __DIR__ . '/../includes/auth.php';
require_roles(['Administrator', 'Receptionist']);
$page_title = 'Update Appointment';
require_once __DIR__ . '/../includes/header.php';
$id = (int) ($_GET['id'] ?? 0);
$patients = $conn->query('SELECT id, full_name FROM patients ORDER BY full_name')->fetch_all(MYSQLI_ASSOC);
$doctors = $conn->query("SELECT id, full_name, specialization FROM doctors WHERE status = 'Active' ORDER BY full_name")->fetch_all(MYSQLI_ASSOC);
$stmt = $conn->prepare('SELECT * FROM appointments WHERE id = ?');
$stmt->bind_param('i', $id);
$appt = fetch_one($stmt);
if (!$appt) {
    flash('danger', 'Appointment not found.');
    redirect('/appointments/view.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $conn->prepare('UPDATE appointments SET patient_id = ?, doctor_id = ?, appointment_date = ?, appointment_time = ?, reason = ?, status = ?, notes = ?, remarks = ? WHERE id = ?');
    $patient_id = (int) $_POST['patient_id'];
    $doctor_id = (int) $_POST['doctor_id'];
    $stmt->bind_param('iissssssi', $patient_id, $doctor_id, $_POST['appointment_date'], $_POST['appointment_time'], $_POST['reason'], $_POST['status'], $_POST['notes'], $_POST['remarks'], $id);
    $stmt->execute();
    flash('success', 'Appointment updated successfully.');
    redirect('/appointments/view.php');
}
?>
<div class="page-head"><h1 class="h3 mb-0">Update Appointment</h1></div>
<form class="form-panel" method="post">
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Patient</label><select class="form-select" name="patient_id"><?php foreach ($patients as $p): ?><option value="<?= $p['id'] ?>" <?= (int) $appt['patient_id'] === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['full_name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6"><label class="form-label">Doctor</label><select class="form-select" name="doctor_id"><?php foreach ($doctors as $doctor): ?><option value="<?= $doctor['id'] ?>" <?= (int) ($appt['doctor_id'] ?? 0) === (int) $doctor['id'] ? 'selected' : '' ?>><?= e($doctor['full_name']) ?> - <?= e($doctor['specialization']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label">Date</label><input class="form-control" type="date" name="appointment_date" value="<?= e($appt['appointment_date']) ?>" required></div>
        <div class="col-md-3"><label class="form-label">Time</label><input class="form-control" type="time" name="appointment_time" value="<?= e(substr($appt['appointment_time'], 0, 5)) ?>" required></div>
        <div class="col-md-8"><label class="form-label">Reason</label><input class="form-control" name="reason" value="<?= e($appt['reason']) ?>" required></div>
        <div class="col-md-4"><label class="form-label">Status</label><select class="form-select" name="status"><?php foreach (['Pending','Approved','Rejected','Completed','Cancelled'] as $s): ?><option <?= $appt['status'] === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></div>
        <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="4"><?= e($appt['notes']) ?></textarea></div>
        <div class="col-12"><label class="form-label">Doctor Remarks</label><textarea class="form-control" name="remarks" rows="3"><?= e($appt['remarks'] ?? '') ?></textarea></div>
    </div>
    <div class="mt-4"><button class="btn btn-primary">Update Appointment</button> <a class="btn btn-secondary" href="view.php">Cancel</a></div>
</form>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
