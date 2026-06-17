<?php
require_once __DIR__ . '/../includes/auth.php';
require_access('inventory');
$page_title = 'Inventory Items';
require_once __DIR__ . '/../includes/header.php';

$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');
$where = 'WHERE 1=1';
$types = '';
$params = [];
if ($search !== '') {
    $like = '%' . $search . '%';
    $where .= ' AND (m.medicine_name LIKE ? OR m.generic_name LIKE ? OR m.batch_number LIKE ? OR m.barcode LIKE ? OR m.supplier LIKE ?)';
    $types .= 'sssss';
    array_push($params, $like, $like, $like, $like, $like);
}
if ($category !== '') {
    $where .= ' AND m.category = ?';
    $types .= 's';
    $params[] = $category;
}

$stmt = $conn->prepare("SELECT m.*, s.company_name supplier_company FROM medicines m LEFT JOIN suppliers s ON s.id = m.supplier_id $where ORDER BY m.medicine_name");
if ($types !== '') {
    bind_params($stmt, $types, $params);
}
$medicines = fetch_all($stmt);
$categories = ['Medicines','Surgical Gloves','Syringes','Needles','Bandages','Medical Equipment','Laboratory Supplies','Cleaning Supplies','Other Medical Consumables'];
?>
<div class="patient-head">
    <div><h1>Medicine & Inventory Management</h1><p>Manage medicines, supplies, equipment, stock levels, batches, and expiry data.</p></div>
    <a class="add-patient-btn" href="medicine_form.php"><i class="bi bi-plus-lg"></i>Add New Medicine</a>
</div>
<section class="patient-management-card">
    <div class="patient-tabs"><div class="tab-links"><a href="items.php">General Items</a><a class="active" href="medicines.php">Medicines</a><a href="purchase.php">Purchase</a><a href="stock_in.php">Stock In</a><a href="stock_out.php">Stock Out</a><a href="suppliers.php">Suppliers</a><a href="movements.php">Movements</a><a href="reports.php">Reports</a></div></div>
    <form class="appointment-list-toolbar m-4" method="get">
        <label class="appointment-search-box"><i class="bi bi-search"></i><input name="search" value="<?= e($search) ?>" placeholder="Search name, generic, batch, barcode, supplier..."></label>
        <select name="category"><option value="">All Categories</option><?php foreach ($categories as $cat): ?><option value="<?= e($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>><?= e($cat) ?></option><?php endforeach; ?></select>
        <button><i class="bi bi-funnel"></i>Filter</button>
    </form>
    <div class="table-responsive p-4 pt-0">
        <table class="table align-middle">
            <thead><tr><th>Item</th><th>Batch / Barcode</th><th>Category</th><th>Qty</th><th>Reorder</th><th>Expiry</th><th>Value</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($medicines as $medicine): ?>
                    <?php $expired = strtotime($medicine['expiry_date']) < strtotime(date('Y-m-d')); $low = (int) $medicine['quantity'] <= (int) $medicine['reorder_level']; ?>
                    <tr class="<?= $expired ? 'table-danger' : ($low ? 'table-warning' : '') ?>">
                        <td><strong><?= e($medicine['medicine_name']) ?></strong><small class="d-block text-muted"><?= e($medicine['generic_name'] ?: 'No generic name') ?> - <?= e($medicine['supplier_company'] ?: $medicine['supplier']) ?></small></td>
                        <td><?= e($medicine['batch_number'] ?: '-') ?><small class="d-block text-muted"><?= e($medicine['barcode'] ?: 'No barcode') ?></small></td>
                        <td><?= e($medicine['category']) ?></td>
                        <td><em class="status-pill <?= $low ? 'inactive' : 'active' ?>"><?= number_format((int) $medicine['quantity']) ?></em></td>
                        <td><?= number_format((int) $medicine['reorder_level']) ?></td>
                        <td><?= e(date('M j, Y', strtotime($medicine['expiry_date']))) ?></td>
                        <td>$<?= number_format((float) $medicine['quantity'] * (float) $medicine['unit_price'], 2) ?></td>
                        <td class="patient-actions">
                            <a href="medicine_detail.php?id=<?= (int) $medicine['id'] ?>"><i class="bi bi-eye"></i></a>
                            <a href="medicine_form.php?id=<?= (int) $medicine['id'] ?>"><i class="bi bi-pencil-square"></i></a>
                            <a href="medicine_delete.php?id=<?= (int) $medicine['id'] ?>" onclick="return confirm('Delete this inventory item?')"><i class="bi bi-trash"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$medicines): ?><tr><td colspan="8"><div class="empty-state">No inventory items found.</div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

