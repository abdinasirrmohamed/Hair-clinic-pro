<?php
require_once __DIR__ . '/../includes/auth.php';
require_roles(['Administrator', 'Doctor']);
$page_title = 'Add Treatment';
require_once __DIR__ . '/../includes/header.php';
$patients = $conn->query('SELECT id, full_name FROM patients p WHERE 1=1 ' . doctor_patient_filter('p') . ' ORDER BY full_name')->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $conn->prepare('INSERT INTO treatments (patient_id, treatment_name, treatment_date, progress, cost, notes) VALUES (?, ?, ?, ?, ?, ?)');
    $patient_id = (int) $_POST['patient_id'];
    require_patient_assignment($patient_id);
    $cost = (float) $_POST['cost'];
    $stmt->bind_param('isssds', $patient_id, $_POST['treatment_name'], $_POST['treatment_date'], $_POST['progress'], $cost, $_POST['notes']);
    $stmt->execute();
    flash('success', 'Treatment record added successfully.');
    redirect('/treatments/view.php');
}
?>
<div class="page-head"><h1 class="h3 mb-0">Add Treatment Record</h1></div>
<form class="form-panel" method="post">
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Patient</label><select class="form-select" name="patient_id" required><option value="">Select patient</option><?php foreach ($patients as $p): ?><option value="<?= $p['id'] ?>"><?= e($p['full_name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6"><label class="form-label">Treatment Name</label><input class="form-control" name="treatment_name" required></div>
        <div class="col-md-4"><label class="form-label">Treatment Date</label><input class="form-control" type="date" name="treatment_date" required></div>
        <div class="col-md-4"><label class="form-label">Progress</label><select class="form-select" name="progress"><option>Started</option><option>In Progress</option><option>Completed</option></select></div>
        <div class="col-md-4"><label class="form-label">Cost</label><input class="form-control" type="number" min="0" step="0.01" name="cost" value="0"></div>
        <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="4"></textarea></div>
    </div>
    <div class="mt-4"><button class="btn btn-primary">Save Treatment</button> <a class="btn btn-secondary" href="view.php">Cancel</a></div>
</form>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
