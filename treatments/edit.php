<?php
require_once __DIR__ . '/../includes/auth.php';
require_roles(['Administrator', 'Doctor']);
$page_title = 'Update Treatment';
require_once __DIR__ . '/../includes/header.php';
$id = (int) ($_GET['id'] ?? 0);
$patients = $conn->query('SELECT id, full_name FROM patients p WHERE 1=1 ' . doctor_patient_filter('p') . ' ORDER BY full_name')->fetch_all(MYSQLI_ASSOC);
$stmt = $conn->prepare('SELECT * FROM treatments WHERE id = ?');
$stmt->bind_param('i', $id);
$treatment = fetch_one($stmt);
if (!$treatment) {
    flash('danger', 'Treatment record not found.');
    redirect('/treatments/view.php');
}
require_patient_assignment((int) $treatment['patient_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $conn->prepare('UPDATE treatments SET patient_id = ?, treatment_name = ?, treatment_date = ?, progress = ?, cost = ?, notes = ? WHERE id = ?');
    $patient_id = (int) $_POST['patient_id'];
    require_patient_assignment($patient_id);
    $cost = (float) $_POST['cost'];
    $stmt->bind_param('isssdsi', $patient_id, $_POST['treatment_name'], $_POST['treatment_date'], $_POST['progress'], $cost, $_POST['notes'], $id);
    $stmt->execute();
    flash('success', 'Treatment progress updated successfully.');
    redirect('/treatments/view.php');
}
?>
<div class="page-head"><h1 class="h3 mb-0">Update Treatment Progress</h1></div>
<form class="form-panel" method="post">
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Patient</label><select class="form-select" name="patient_id"><?php foreach ($patients as $p): ?><option value="<?= $p['id'] ?>" <?= (int) $treatment['patient_id'] === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['full_name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6"><label class="form-label">Treatment Name</label><input class="form-control" name="treatment_name" value="<?= e($treatment['treatment_name']) ?>" required></div>
        <div class="col-md-4"><label class="form-label">Treatment Date</label><input class="form-control" type="date" name="treatment_date" value="<?= e($treatment['treatment_date']) ?>" required></div>
        <div class="col-md-4"><label class="form-label">Progress</label><select class="form-select" name="progress"><?php foreach (['Started','In Progress','Completed'] as $p): ?><option <?= $treatment['progress'] === $p ? 'selected' : '' ?>><?= $p ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label">Cost</label><input class="form-control" type="number" min="0" step="0.01" name="cost" value="<?= e($treatment['cost']) ?>"></div>
        <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="4"><?= e($treatment['notes']) ?></textarea></div>
    </div>
    <div class="mt-4"><button class="btn btn-primary">Update Treatment</button> <a class="btn btn-secondary" href="view.php">Cancel</a></div>
</form>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
