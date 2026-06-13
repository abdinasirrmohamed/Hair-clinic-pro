<?php
require_once __DIR__ . '/../includes/auth.php';
require_roles(['Administrator', 'Receptionist']);
$page_title = 'Add Patient';
require_once __DIR__ . '/../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $conn->prepare('INSERT INTO patients (full_name, phone, email, gender, date_of_birth, address, medical_notes) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $dob = $_POST['date_of_birth'] ?: null;
    $stmt->bind_param('sssssss', $_POST['full_name'], $_POST['phone'], $_POST['email'], $_POST['gender'], $dob, $_POST['address'], $_POST['medical_notes']);
    $stmt->execute();
    log_activity('Created patient record', 'Patients', $conn->insert_id);
    flash('success', 'Patient added successfully.');
    redirect('/patients/view.php');
}
?>
<div class="page-head"><h1 class="h3 mb-0">Add Patient</h1></div>
<form class="form-panel" method="post">
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Full Name</label><input class="form-control" name="full_name" required></div>
        <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" name="phone" required></div>
        <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" type="email" name="email"></div>
        <div class="col-md-3"><label class="form-label">Gender</label><select class="form-select" name="gender" required><option>Male</option><option>Female</option><option>Other</option></select></div>
        <div class="col-md-3"><label class="form-label">Date of Birth</label><input class="form-control" type="date" name="date_of_birth"></div>
        <div class="col-12"><label class="form-label">Address</label><input class="form-control" name="address"></div>
        <div class="col-12"><label class="form-label">Medical Notes</label><textarea class="form-control" name="medical_notes" rows="4"></textarea></div>
    </div>
    <div class="mt-4"><button class="btn btn-primary">Save Patient</button> <a class="btn btn-secondary" href="view.php">Cancel</a></div>
</form>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
