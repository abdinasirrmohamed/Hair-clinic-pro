<?php
require_once __DIR__ . '/../includes/auth.php';
require_access('inventory');

$id = (int) ($_GET['id'] ?? 0);
$item = null;

if ($id > 0) {
    $stmt = $conn->prepare('SELECT * FROM inventory_items WHERE id = ?');
    $stmt->bind_param('i', $id);
    $item = fetch_one($stmt);
    if (!$item) {
        flash('danger', 'Item not found.');
        redirect('/inventory/items.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_name = trim($_POST['item_name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $stock_level = max(0, (int) ($_POST['stock_level'] ?? 0));
    $unit_price = max(0, (float) ($_POST['unit_price'] ?? 0));
    $vendor = trim($_POST['vendor'] ?? '');
    $status = $_POST['status'] ?? 'In Stock';

    if ($item_name === '') {
        flash('danger', 'Item name is required.');
        redirect('/inventory/item_form.php' . ($id ? '?id=' . $id : ''));
    }
    if (!in_array($status, ['In Stock', 'Low Stock', 'Out of Stock'], true)) {
        $status = 'In Stock';
    }

    if ($id > 0) {
        $stmt = $conn->prepare('UPDATE inventory_items SET item_name = ?, category = ?, stock_level = ?, unit_price = ?, vendor = ?, status = ? WHERE id = ?');
        $stmt->bind_param('ssidsi', $item_name, $category, $stock_level, $unit_price, $vendor, $status, $id);
        $stmt->execute();
        log_activity('Updated general inventory item', 'Inventory', $id);
        flash('success', 'Item updated successfully.');
    } else {
        $stmt = $conn->prepare('INSERT INTO inventory_items (item_name, category, stock_level, unit_price, vendor, status) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('ssids', $item_name, $category, $stock_level, $unit_price, $vendor, $status);
        $stmt->execute();
        log_activity('Created general inventory item', 'Inventory', $conn->insert_id);
        flash('success', 'Item created successfully.');
    }
    redirect('/inventory/items.php');
}

$page_title = $item ? 'Edit Item' : 'Add Item';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="patient-head">
    <div>
        <h1><?= $item ? 'Edit General Inventory Item' : 'Add New General Item' ?></h1>
        <p>Record details for non-medical clinic inventory like equipment, supplies, and furniture.</p>
    </div>
</div>

<form class="form-panel" method="post">
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label fw-semibold">Item Name <span class="text-danger">*</span></label>
            <input class="form-control" name="item_name" value="<?= e($item['item_name'] ?? '') ?>" required>
        </div>
        <div class="col-md-6">
            <label class="form-label fw-semibold">Category</label>
            <input class="form-control" name="category" list="catList" value="<?= e($item['category'] ?? '') ?>" placeholder="e.g. Office Supplies">
            <datalist id="catList">
                <option value="Office Supplies">
                <option value="Cleaning Supplies">
                <option value="General Equipment">
                <option value="IT Equipment">
                <option value="Furniture">
            </datalist>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">Stock Level</label>
            <input class="form-control" type="number" min="0" name="stock_level" value="<?= e($item['stock_level'] ?? 0) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">Unit Price ($)</label>
            <input class="form-control" type="number" min="0" step="0.01" name="unit_price" value="<?= e($item['unit_price'] ?? 0.00) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">Status</label>
            <select class="form-select" name="status">
                <?php foreach (['In Stock', 'Low Stock', 'Out of Stock'] as $st): ?>
                    <option value="<?= e($st) ?>" <?= ($item['status'] ?? 'In Stock') === $st ? 'selected' : '' ?>><?= e($st) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">Vendor</label>
            <input class="form-control" name="vendor" value="<?= e($item['vendor'] ?? '') ?>" placeholder="Company name">
        </div>
    </div>
    <div class="mt-4">
        <button class="btn btn-primary"><i class="bi bi-save"></i> Save Item</button>
        <a class="btn btn-secondary" href="items.php">Cancel</a>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
