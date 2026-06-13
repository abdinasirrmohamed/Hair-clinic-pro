<?php
require_once __DIR__ . '/../includes/auth.php';
require_roles(['Administrator', 'Receptionist']);
$page_title = 'Edit Patient';
require_once __DIR__ . '/../includes/header.php';

$id = (int) ($_GET['id'] ?? 0);
$stmt = $conn->prepare('SELECT * FROM patients WHERE id = ?');
$stmt->bind_param('i', $id);
$patient = fetch_one($stmt);
if (!$patient) {
    flash('danger', 'Patient not found.');
    redirect('/patients/view.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $conn->prepare('UPDATE patients SET full_name = ?, phone = ?, email = ?, gender = ?, date_of_birth = ?, address = ?, medical_notes = ? WHERE id = ?');
    $dob = $_POST['date_of_birth'] ?: null;
    $stmt->bind_param('sssssssi', $_POST['full_name'], $_POST['phone'], $_POST['email'], $_POST['gender'], $dob, $_POST['address'], $_POST['medical_notes'], $id);
    $stmt->execute();
    flash('success', 'Patient updated successfully.');
    redirect('/patients/view.php');
}
?>
<div class="page-head"><h1 class="h3 mb-0">Edit Patient</h1></div>
<form class="form-panel" method="post">
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Full Name</label><input class="form-control" name="full_name" value="<?= e($patient['full_name']) ?>" required></div>
        <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" name="phone" value="<?= e($patient['phone']) ?>" required></div>
        <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="<?= e($patient['email']) ?>"></div>
        <div class="col-md-3"><label class="form-label">Gender</label><select class="form-select" name="gender"><?php foreach (['Male','Female','Other'] as $g): ?><option <?= $patient['gender'] === $g ? 'selected' : '' ?>><?= $g ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label">Date of Birth</label><input class="form-control" type="date" name="date_of_birth" value="<?= e($patient['date_of_birth']) ?>"></div>
        <div class="col-12"><label class="form-label">Address</label><input class="form-control" name="address" value="<?= e($patient['address']) ?>"></div>
        <div class="col-12"><label class="form-label">Medical Notes</label><textarea class="form-control" name="medical_notes" rows="4"><?= e($patient['medical_notes']) ?></textarea></div>
    </div>
    <div class="mt-4"><button class="btn btn-primary">Update Patient</button> <a class="btn btn-secondary" href="view.php">Cancel</a></div>
</form>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
