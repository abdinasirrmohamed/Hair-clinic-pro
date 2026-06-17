<?php
require_once __DIR__ . '/../includes/auth.php';
require_access('inventory');
$type = $_GET['type'] ?? '';
$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : date('Y-m-01');
$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : date('Y-m-t');
$search = trim($_GET['search'] ?? '');
$where = 'WHERE DATE(im.movement_date) BETWEEN ? AND ?';
$types = 'ss';
$params = [$from, $to];
if (in_array($type, ['Stock In','Stock Out','Pharmacy Sales','Treatment Consumption','Inventory Adjustment'], true)) {
    $where .= ' AND im.movement_type = ?';
    $types .= 's';
    $params[] = $type;
}
if ($search !== '') {
    $where .= ' AND (m.medicine_name LIKE ? OR im.transaction_number LIKE ? OR im.department LIKE ? OR im.purpose LIKE ?)';
    $like = '%' . $search . '%';
    $types .= 'ssss';
    array_push($params, $like, $like, $like, $like);
}
$stmt = $conn->prepare("SELECT im.*, m.medicine_name, u.full_name user_name, s.company_name supplier_name FROM inventory_movements im JOIN medicines m ON m.id = im.medicine_id LEFT JOIN users u ON u.id = im.issued_by LEFT JOIN suppliers s ON s.id = im.supplier_id $where ORDER BY im.movement_date DESC LIMIT 500");
bind_params($stmt, $types, $params);
$movements = fetch_all($stmt);
$page_title = 'Inventory Movement History';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="patient-head"><div><h1>Inventory Movement History</h1><p>Track all stock in, stock out, pharmacy sales, treatment consumption, and adjustments.</p></div></div>
<section class="patient-management-card">
    <div class="patient-tabs"><div class="tab-links"><a href="items.php">General Items</a><a href="medicines.php">Medicines</a><a href="purchase.php">Purchase</a><a href="stock_in.php">Stock In</a><a href="stock_out.php">Stock Out</a><a href="suppliers.php">Suppliers</a><a class="active" href="movements.php">Movements</a><a href="reports.php">Reports</a></div></div>
    <form class="appointment-list-toolbar m-4" method="get">
        <label class="appointment-search-box"><i class="bi bi-search"></i><input name="search" value="<?= e($search) ?>" placeholder="Search item, transaction, department, purpose..."></label>
        <select name="type"><option value="">All Types</option><?php foreach (['Stock In','Stock Out','Pharmacy Sales','Treatment Consumption','Inventory Adjustment'] as $movement_type): ?><option value="<?= e($movement_type) ?>" <?= $type === $movement_type ? 'selected' : '' ?>><?= e($movement_type) ?></option><?php endforeach; ?></select>
        <input type="date" name="from" value="<?= e($from) ?>"><input type="date" name="to" value="<?= e($to) ?>">
        <button><i class="bi bi-funnel"></i>Filter</button>
    </form>
    <div class="table-responsive p-4 pt-0">
        <table class="table align-middle">
            <thead><tr><th>Date & Time</th><th>Transaction</th><th>Item</th><th>Type</th><th>Quantity</th><th>User</th><th>Department / Purpose</th></tr></thead>
            <tbody>
                <?php foreach ($movements as $movement): ?>
                    <tr>
                        <td><?= e(date('Y-m-d h:i A', strtotime($movement['movement_date']))) ?></td>
                        <td><?= e($movement['transaction_number']) ?></td>
                        <td><strong><?= e($movement['medicine_name']) ?></strong><small class="d-block text-muted"><?= e($movement['supplier_name'] ?: '') ?></small></td>
                        <td><span class="status-pill <?= $movement['movement_type'] === 'Stock In' ? 'active' : 'inactive' ?>"><?= e($movement['movement_type']) ?></span></td>
                        <td><?= number_format((int) $movement['quantity']) ?></td>
                        <td><?= e($movement['user_name'] ?: 'System') ?></td>
                        <td><?= e($movement['department'] ?: '-') ?><small class="d-block text-muted"><?= e($movement['purpose'] ?: '-') ?></small></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$movements): ?><tr><td colspan="7"><div class="empty-state">No inventory movements found.</div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

