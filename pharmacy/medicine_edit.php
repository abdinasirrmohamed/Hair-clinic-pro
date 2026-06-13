<?php
require_once __DIR__ . '/../includes/auth.php';
require_access('pharmacy');
require_roles(['Administrator', 'Inventory Officer']);
$page_title = 'Edit Medicine';
require_once __DIR__ . '/../includes/header.php';

$id = (int) ($_GET['id'] ?? 0);
$stmt = $conn->prepare('SELECT * FROM medicines WHERE id = ?');
$stmt->bind_param('i', $id);
$medicine = fetch_one($stmt);
if (!$medicine) {
    flash('danger', 'Medicine not found.');
    redirect('/pharmacy/medicines.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $conn->prepare('UPDATE medicines SET medicine_name = ?, category = ?, quantity = ?, unit_price = ?, expiry_date = ?, supplier = ? WHERE id = ?');
    $quantity = (int) $_POST['quantity'];
    $price = (float) $_POST['unit_price'];
    $stmt->bind_param('ssidssi', $_POST['medicine_name'], $_POST['category'], $quantity, $price, $_POST['expiry_date'], $_POST['supplier'], $id);
    $stmt->execute();
    log_activity('Updated medicine inventory', 'Pharmacy', $id);
    flash('success', 'Medicine updated successfully.');
    redirect('/pharmacy/medicines.php');
}
?>
<div class="page-head"><h1 class="h3 mb-0">Edit Medicine</h1></div>
<form class="form-panel" method="post">
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Medicine Name</label><input class="form-control" name="medicine_name" value="<?= e($medicine['medicine_name']) ?>" required></div>
        <div class="col-md-6"><label class="form-label">Category</label><input class="form-control" name="category" value="<?= e($medicine['category']) ?>" required></div>
        <div class="col-md-3"><label class="form-label">Quantity</label><input class="form-control" type="number" name="quantity" value="<?= e($medicine['quantity']) ?>" required></div>
        <div class="col-md-3"><label class="form-label">Unit Price</label><input class="form-control" type="number" step="0.01" name="unit_price" value="<?= e($medicine['unit_price']) ?>" required></div>
        <div class="col-md-3"><label class="form-label">Expiry Date</label><input class="form-control" type="date" name="expiry_date" value="<?= e($medicine['expiry_date']) ?>" required></div>
        <div class="col-md-3"><label class="form-label">Supplier</label><input class="form-control" name="supplier" value="<?= e($medicine['supplier']) ?>" required></div>
    </div>
    <div class="mt-4"><button class="btn btn-primary">Update Medicine</button> <a class="btn btn-secondary" href="medicines.php">Cancel</a></div>
</form>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
