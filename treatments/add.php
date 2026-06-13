<?php
require_once __DIR__ . '/../includes/auth.php';
require_roles(['Administrator', 'Doctor']);
$page_title = 'Add Treatment';
require_once __DIR__ . '/../includes/header.php';
$patients = $conn->query('SELECT id, full_name FROM patients p WHERE 1=1 ' . doctor_patient_filter('p') . ' ORDER BY full_name')->fetch_all(MYSQLI_ASSOC);
$medicines = $conn->query('SELECT id, medicine_name, quantity, unit_price, expiry_date FROM medicines ORDER BY medicine_name')->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->begin_transaction();
        $stmt = $conn->prepare('INSERT INTO treatments (patient_id, treatment_name, treatment_date, progress, cost, notes) VALUES (?, ?, ?, ?, ?, ?)');
        $patient_id = (int) $_POST['patient_id'];
        require_patient_assignment($patient_id);
        $cost = (float) $_POST['cost'];
        $stmt->bind_param('isssds', $patient_id, $_POST['treatment_name'], $_POST['treatment_date'], $_POST['progress'], $cost, $_POST['notes']);
        $stmt->execute();
        $treatment_id = (int) $conn->insert_id;

        $usage_medicine_id = (int) ($_POST['usage_medicine_id'] ?? 0);
        $usage_quantity = max(0, (int) ($_POST['usage_quantity'] ?? 0));
        if ($usage_medicine_id > 0 && $usage_quantity > 0) {
            $stock = $conn->prepare('SELECT * FROM medicines WHERE id = ? FOR UPDATE');
            $stock->bind_param('i', $usage_medicine_id);
            $medicine = fetch_one($stock);
            ensure_medicine_can_issue($medicine, $usage_quantity);
            $update = $conn->prepare('UPDATE medicines SET quantity = quantity - ? WHERE id = ?');
            $update->bind_param('ii', $usage_quantity, $usage_medicine_id);
            $update->execute();
            record_inventory_movement($usage_medicine_id, 'Treatment Consumption', $usage_quantity, (float) $medicine['unit_price'], [
                'department' => 'Treatment',
                'purpose' => 'Medicine or supply used during treatment',
                'reference_type' => 'Treatment',
                'reference_id' => $treatment_id,
            ]);
        }

        $conn->commit();
        log_activity('Created treatment record', 'Treatments', $treatment_id);
        flash('success', 'Treatment record added successfully.');
    } catch (Throwable $e) {
        $conn->rollback();
        flash('danger', $e->getMessage());
    }
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
        <div class="col-md-8"><label class="form-label">Treatment Stock Usage</label><select class="form-select" name="usage_medicine_id"><option value="">No inventory item used</option><?php foreach ($medicines as $medicine): ?><option value="<?= (int) $medicine['id'] ?>"><?= e($medicine['medicine_name']) ?> - Stock <?= number_format((int) $medicine['quantity']) ?> - Exp <?= e($medicine['expiry_date']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label">Usage Quantity</label><input class="form-control" type="number" min="0" name="usage_quantity" value="0"></div>
        <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="4"></textarea></div>
    </div>
    <div class="mt-4"><button class="btn btn-primary">Save Treatment</button> <a class="btn btn-secondary" href="view.php">Cancel</a></div>
</form>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
