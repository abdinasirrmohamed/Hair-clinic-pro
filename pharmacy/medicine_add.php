<?php
require_once __DIR__ . '/../includes/auth.php';
require_access('pharmacy');
require_roles(['Administrator', 'Inventory Officer']);
$page_title = 'Add Medicine';
require_once __DIR__ . '/../includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $conn->prepare('INSERT INTO medicines (medicine_name, category, quantity, unit_price, expiry_date, supplier) VALUES (?, ?, ?, ?, ?, ?)');
    $quantity = (int) $_POST['quantity'];
    $price = (float) $_POST['unit_price'];
    $stmt->bind_param('ssidss', $_POST['medicine_name'], $_POST['category'], $quantity, $price, $_POST['expiry_date'], $_POST['supplier']);
    $stmt->execute();
    log_activity('Added medicine inventory', 'Pharmacy', $conn->insert_id);
    flash('success', 'Medicine added successfully.');
    redirect('/pharmacy/medicines.php');
}
?>
<div class="page-head"><h1 class="h3 mb-0">Add Medicine</h1></div>
<form class="form-panel" method="post">
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Medicine Name</label><input class="form-control" name="medicine_name" required></div>
        <div class="col-md-6"><label class="form-label">Category</label><input class="form-control" name="category" required></div>
        <div class="col-md-3"><label class="form-label">Quantity</label><input class="form-control" type="number" name="quantity" required></div>
        <div class="col-md-3"><label class="form-label">Unit Price</label><input class="form-control" type="number" step="0.01" name="unit_price" required></div>
        <div class="col-md-3"><label class="form-label">Expiry Date</label><input class="form-control" type="date" name="expiry_date" required></div>
        <div class="col-md-3"><label class="form-label">Supplier</label><input class="form-control" name="supplier" required></div>
    </div>
    <div class="mt-4"><button class="btn btn-primary">Save Medicine</button> <a class="btn btn-secondary" href="medicines.php">Cancel</a></div>
</form>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
