<?php
require_once __DIR__ . '/../includes/auth.php';
require_access('inventory');
$id = (int) ($_GET['id'] ?? 0);
$medicine = null;
if ($id > 0) {
    $stmt = $conn->prepare('SELECT * FROM medicines WHERE id = ?');
    $stmt->bind_param('i', $id);
    $medicine = fetch_one($stmt);
    if (!$medicine) {
        flash('danger', 'Inventory item not found.');
        redirect('/inventory/medicines.php');
    }
}
$categories = ['Medicines','Surgical Gloves','Syringes','Needles','Bandages','Medical Equipment','Laboratory Supplies','Cleaning Supplies','Other Medical Consumables'];
$suppliers = $conn->query('SELECT * FROM suppliers ORDER BY company_name')->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $medicine_name = trim($_POST['medicine_name'] ?? '');
    $generic_name = trim($_POST['generic_name'] ?? '');
    $category = in_array($_POST['category'] ?? '', $categories, true) ? $_POST['category'] : 'Other Medical Consumables';
    $batch_number = trim($_POST['batch_number'] ?? '');
    $barcode = trim($_POST['barcode'] ?? '');
    $quantity = max(0, (int) ($_POST['quantity'] ?? 0));
    $unit_price = max(0, (float) ($_POST['unit_price'] ?? 0));
    $supplier_id = $_POST['supplier_id'] !== '' ? (int) $_POST['supplier_id'] : null;
    $supplier = trim($_POST['supplier'] ?? '');
    $manufacturing_date = $_POST['manufacturing_date'] ?: null;
    $expiry_date = $_POST['expiry_date'] ?: date('Y-m-d');
    $reorder_level = max(0, (int) ($_POST['reorder_level'] ?? 10));
    $description = trim($_POST['description'] ?? '');

    if ($supplier_id) {
        $stmt = $conn->prepare('SELECT company_name FROM suppliers WHERE id = ?');
        $stmt->bind_param('i', $supplier_id);
        $supplier_row = fetch_one($stmt);
        $supplier = $supplier_row['company_name'] ?? $supplier;
    }

    if ($medicine_name === '') {
        flash('danger', 'Medicine or item name is required.');
        redirect('/inventory/medicine_form.php' . ($id ? '?id=' . $id : ''));
    }

    if ($id > 0) {
        $stmt = $conn->prepare('UPDATE medicines SET medicine_name = ?, generic_name = ?, category = ?, batch_number = ?, barcode = ?, quantity = ?, unit_price = ?, supplier_id = ?, supplier = ?, manufacturing_date = ?, expiry_date = ?, reorder_level = ?, description = ? WHERE id = ?');
        $stmt->bind_param('sssssidisssisi', $medicine_name, $generic_name, $category, $batch_number, $barcode, $quantity, $unit_price, $supplier_id, $supplier, $manufacturing_date, $expiry_date, $reorder_level, $description, $id);
        $stmt->execute();
        log_activity('Updated inventory item', 'Inventory', $id);
        flash('success', 'Inventory item updated.');
    } else {
        $stmt = $conn->prepare('INSERT INTO medicines (medicine_name, generic_name, category, batch_number, barcode, quantity, unit_price, supplier_id, supplier, manufacturing_date, expiry_date, reorder_level, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('sssssidisssis', $medicine_name, $generic_name, $category, $batch_number, $barcode, $quantity, $unit_price, $supplier_id, $supplier, $manufacturing_date, $expiry_date, $reorder_level, $description);
        $stmt->execute();
        $id = (int) $conn->insert_id;
        if ($quantity > 0) {
            record_inventory_movement($id, 'Inventory Adjustment', $quantity, $unit_price, ['purpose' => 'Opening stock', 'reference_type' => 'Medicine', 'reference_id' => $id]);
        }
        log_activity('Created inventory item', 'Inventory', $id);
        flash('success', 'Inventory item created.');
    }
    redirect('/inventory/medicines.php');
}

$page_title = $medicine ? 'Edit Inventory Item' : 'Add Inventory Item';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="patient-head"><div><h1><?= $medicine ? 'Edit Inventory Item' : 'Add New Medicine' ?></h1><p>Record full stock, batch, barcode, supplier, reorder, and expiry details.</p></div></div>
<form class="form-panel" method="post">
    <div class="row g-3">
        <div class="col-md-4"><label class="form-label">Medicine / Item Name</label><input class="form-control" name="medicine_name" value="<?= e($medicine['medicine_name'] ?? '') ?>" required></div>
        <div class="col-md-4"><label class="form-label">Generic Name</label><input class="form-control" name="generic_name" value="<?= e($medicine['generic_name'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="form-label">Category</label><select class="form-select" name="category"><?php foreach ($categories as $cat): ?><option <?= ($medicine['category'] ?? '') === $cat ? 'selected' : '' ?>><?= e($cat) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label">Batch Number</label><input class="form-control" name="batch_number" value="<?= e($medicine['batch_number'] ?? '') ?>"></div>
        <div class="col-md-3"><label class="form-label">Barcode</label><input class="form-control" name="barcode" value="<?= e($medicine['barcode'] ?? '') ?>"></div>
        <div class="col-md-2"><label class="form-label">Quantity</label><input class="form-control" type="number" min="0" name="quantity" value="<?= e($medicine['quantity'] ?? 0) ?>"></div>
        <div class="col-md-2"><label class="form-label">Unit Price</label><input class="form-control" type="number" min="0" step="0.01" name="unit_price" value="<?= e($medicine['unit_price'] ?? 0) ?>"></div>
        <div class="col-md-2"><label class="form-label">Reorder Level</label><input class="form-control" type="number" min="0" name="reorder_level" value="<?= e($medicine['reorder_level'] ?? 10) ?>"></div>
        <div class="col-md-4"><label class="form-label">Supplier</label><select class="form-select" name="supplier_id"><option value="">Manual supplier</option><?php foreach ($suppliers as $supplier): ?><option value="<?= (int) $supplier['id'] ?>" <?= (int) ($medicine['supplier_id'] ?? 0) === (int) $supplier['id'] ? 'selected' : '' ?>><?= e($supplier['company_name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label">Supplier Name</label><input class="form-control" name="supplier" value="<?= e($medicine['supplier'] ?? '') ?>"></div>
        <div class="col-md-2"><label class="form-label">Manufacturing Date</label><input class="form-control" type="date" name="manufacturing_date" value="<?= e($medicine['manufacturing_date'] ?? '') ?>"></div>
        <div class="col-md-2"><label class="form-label">Expiry Date</label><input class="form-control" type="date" name="expiry_date" value="<?= e($medicine['expiry_date'] ?? date('Y-m-d', strtotime('+1 year'))) ?>" required></div>
        <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="4"><?= e($medicine['description'] ?? '') ?></textarea></div>
    </div>
    <div class="mt-4"><button class="btn btn-primary">Save Item</button> <a class="btn btn-secondary" href="medicines.php">Cancel</a></div>
</form>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
