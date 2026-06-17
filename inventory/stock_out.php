<?php
require_once __DIR__ . '/../includes/auth.php';
require_access('inventory');
$medicines = $conn->query('SELECT * FROM medicines ORDER BY medicine_name')->fetch_all(MYSQLI_ASSOC);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $medicine_id = (int) ($_POST['medicine_id'] ?? 0);
        $quantity = max(1, (int) ($_POST['quantity'] ?? 1));
        $department = trim($_POST['department'] ?? 'Inventory');
        $purpose = trim($_POST['purpose'] ?? 'Manual Stock Out');
        $date = $_POST['issue_date'] ?: date('Y-m-d');
        $stmt = $conn->prepare('SELECT * FROM medicines WHERE id = ? FOR UPDATE');
        $stmt->bind_param('i', $medicine_id);
        $conn->begin_transaction();
        $medicine = fetch_one($stmt);
        ensure_medicine_can_issue($medicine, $quantity);
        $update = $conn->prepare('UPDATE medicines SET quantity = quantity - ? WHERE id = ?');
        $update->bind_param('ii', $quantity, $medicine_id);
        $update->execute();
        $type = $department === 'Treatment' ? 'Treatment Consumption' : 'Stock Out';
        record_inventory_movement($medicine_id, $type, $quantity, (float) $medicine['unit_price'], ['department' => $department, 'purpose' => $purpose, 'reference_type' => 'Manual Stock Out', 'movement_date' => $date . ' ' . date('H:i:s')]);
        $conn->commit();
        log_activity('Created stock out transaction', 'Inventory', $medicine_id);
        flash('success', 'Stock out completed and quantity reduced.');
    } catch (Throwable $e) {
        $conn->rollback();
        flash('danger', $e->getMessage());
    }
    redirect('/inventory/stock_out.php');
}
$recent = $conn->query("SELECT im.*, m.medicine_name, u.full_name user_name FROM inventory_movements im JOIN medicines m ON m.id = im.medicine_id LEFT JOIN users u ON u.id = im.issued_by WHERE im.movement_type IN ('Stock Out','Pharmacy Sales','Treatment Consumption') ORDER BY im.movement_date DESC LIMIT 15")->fetch_all(MYSQLI_ASSOC);
$page_title = 'Stock Out';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="patient-head"><div><h1>Stock Out Management</h1><p>Record manual stock issues, treatment usage, and other stock deductions.</p></div></div>
<section class="patient-management-card">
    <div class="patient-tabs"><div class="tab-links"><a href="items.php">General Items</a><a href="medicines.php">Medicines</a><a href="purchase.php">Purchase</a><a href="stock_in.php">Stock In</a><a class="active" href="stock_out.php">Stock Out</a><a href="suppliers.php">Suppliers</a><a href="movements.php">Movements</a><a href="reports.php">Reports</a></div></div>
    <div class="row g-4 p-4">
        <div class="col-lg-5"><form class="form-panel m-0" method="post">
            <div class="row g-3">
                <div class="col-12"><label class="form-label">Item Name</label><select class="form-select" name="medicine_id" required><?php foreach ($medicines as $medicine): ?><option value="<?= (int) $medicine['id'] ?>"><?= e($medicine['medicine_name']) ?> - Stock <?= number_format((int) $medicine['quantity']) ?> - Exp <?= e($medicine['expiry_date']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-6"><label class="form-label">Quantity Issued</label><input class="form-control" type="number" min="1" name="quantity" required></div>
                <div class="col-md-6"><label class="form-label">Department</label><select class="form-select" name="department"><option>Inventory</option><option>Pharmacy</option><option>Treatment</option><option>Laboratory</option><option>Cleaning</option></select></div>
                <div class="col-md-6"><label class="form-label">Date</label><input class="form-control" type="date" name="issue_date" value="<?= e(date('Y-m-d')) ?>"></div>
                <div class="col-12"><label class="form-label">Purpose</label><textarea class="form-control" name="purpose" rows="3" required></textarea></div>
            </div>
            <button class="btn btn-primary mt-4"><i class="bi bi-box-arrow-up"></i>Complete Stock Out</button>
        </form></div>
        <div class="col-lg-7"><section class="form-panel m-0 h-100"><h2 class="h5 mb-3">Recent Stock Out</h2><?php foreach ($recent as $row): ?><div class="d-flex justify-content-between border-bottom py-2"><span><strong><?= e($row['medicine_name']) ?></strong><small class="d-block text-muted"><?= e($row['movement_type']) ?> - <?= e($row['department'] ?: '-') ?> - <?= e($row['user_name'] ?: 'System') ?></small></span><span class="text-end"><?= number_format((int) $row['quantity']) ?><small class="d-block text-muted"><?= e(date('M j, h:i A', strtotime($row['movement_date']))) ?></small></span></div><?php endforeach; ?><?php if (!$recent): ?><div class="empty-state">No stock out transactions yet.</div><?php endif; ?></section></div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

