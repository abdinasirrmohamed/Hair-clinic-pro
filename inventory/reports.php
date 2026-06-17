<?php
require_once __DIR__ . '/../includes/auth.php';
require_access('inventory');
$report = $_GET['report'] ?? 'inventory';
$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : date('Y-m-01');
$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : date('Y-m-t');
$export = $_GET['export'] ?? '';

$reports = [
    'inventory' => 'Inventory Report',
    'current_stock' => 'Current Stock Report',
    'low_stock' => 'Low Stock Report',
    'expired' => 'Expired Medicines Report',
    'stock_in' => 'Stock In Report',
    'stock_out' => 'Stock Out Report',
    'suppliers' => 'Supplier Report',
    'movements' => 'Inventory Movement Report',
    'valuation' => 'Inventory Valuation Report',
];
if (!isset($reports[$report])) {
    $report = 'inventory';
}

if ($report === 'suppliers') {
    $rows = $conn->query('SELECT s.company_name item_name, s.contact_person generic_name, s.phone category, s.email batch_number, COUNT(m.id) quantity, 0 unit_price, CURDATE() expiry_date, 0 reorder_level FROM suppliers s LEFT JOIN medicines m ON m.supplier_id = s.id GROUP BY s.id ORDER BY s.company_name')->fetch_all(MYSQLI_ASSOC);
} elseif (in_array($report, ['stock_in', 'stock_out', 'movements'], true)) {
    $movement_filter = $report === 'stock_in' ? "AND im.movement_type = 'Stock In'" : ($report === 'stock_out' ? "AND im.movement_type IN ('Stock Out','Pharmacy Sales','Treatment Consumption')" : '');
    $stmt = $conn->prepare("SELECT m.medicine_name item_name, im.movement_type generic_name, im.department category, im.transaction_number batch_number, im.quantity, im.unit_cost unit_price, DATE(im.movement_date) expiry_date, 0 reorder_level FROM inventory_movements im JOIN medicines m ON m.id = im.medicine_id WHERE DATE(im.movement_date) BETWEEN ? AND ? $movement_filter ORDER BY im.movement_date DESC");
    $stmt->bind_param('ss', $from, $to);
    $rows = fetch_all($stmt);
} else {
    $where = 'WHERE 1=1';
    if ($report === 'low_stock') {
        $where = 'WHERE quantity <= reorder_level';
    } elseif ($report === 'expired') {
        $where = 'WHERE expiry_date < CURDATE()';
    }
    $rows = $conn->query("SELECT medicine_name item_name, generic_name, category, batch_number, quantity, unit_price, expiry_date, reorder_level FROM medicines $where ORDER BY medicine_name")->fetch_all(MYSQLI_ASSOC);
}

if ($export === 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $report . '-' . $from . '-to-' . $to . '.xls"');
    echo $reports[$report] . "\t$from to $to\n";
    echo "Item\tGeneric/Type\tCategory/Department\tBatch/Transaction\tQuantity\tUnit Value\tExpiry/Date\tReorder\n";
    foreach ($rows as $row) {
        echo implode("\t", [
            $row['item_name'],
            $row['generic_name'],
            $row['category'],
            $row['batch_number'],
            $row['quantity'],
            $row['unit_price'],
            $row['expiry_date'],
            $row['reorder_level'],
        ]) . "\n";
    }
    exit;
}

$total_qty = array_sum(array_map(fn($row) => (int) $row['quantity'], $rows));
$total_value = array_sum(array_map(fn($row) => (float) $row['quantity'] * (float) $row['unit_price'], $rows));
$page_title = 'Inventory Reports';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="patient-head">
    <div><h1>Inventory Reports</h1><p>Generate stock, low stock, expired, supplier, movement, and valuation reports.</p></div>
    <div class="d-flex gap-2"><a class="add-patient-btn" href="?<?= e(http_build_query(array_merge($_GET, ['export' => 'excel']))) ?>"><i class="bi bi-file-earmark-spreadsheet"></i>Excel</a><button class="add-patient-btn" onclick="window.print()"><i class="bi bi-printer"></i>PDF / Print</button></div>
</div>
<section class="patient-management-card">
    <div class="patient-tabs"><div class="tab-links"><a href="items.php">General Items</a><a href="medicines.php">Medicines</a><a href="purchase.php">Purchase</a><a href="stock_in.php">Stock In</a><a href="stock_out.php">Stock Out</a><a href="suppliers.php">Suppliers</a><a href="movements.php">Movements</a><a class="active" href="reports.php">Reports</a></div></div>
    <form class="appointment-list-toolbar m-4" method="get">
        <select name="report"><?php foreach ($reports as $key => $label): ?><option value="<?= e($key) ?>" <?= $report === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
        <input type="date" name="from" value="<?= e($from) ?>"><input type="date" name="to" value="<?= e($to) ?>">
        <button><i class="bi bi-file-text"></i>Generate</button>
    </form>
    <div class="patient-metrics px-4 pb-3">
        <article class="patient-metric"><div><p>Rows</p><strong><?= number_format(count($rows)) ?></strong></div><span class="metric-icon blue"><i class="bi bi-list"></i></span></article>
        <article class="patient-metric"><div><p>Total Quantity</p><strong><?= number_format($total_qty) ?></strong></div><span class="metric-icon mint"><i class="bi bi-stack"></i></span></article>
        <article class="patient-metric"><div><p>Inventory Value</p><strong>$<?= number_format($total_value, 2) ?></strong></div><span class="metric-icon blue"><i class="bi bi-cash-coin"></i></span></article>
    </div>
    <div class="table-responsive p-4 pt-0">
        <table class="table align-middle">
            <thead><tr><th>Item</th><th>Generic / Type</th><th>Category / Dept</th><th>Batch / Transaction</th><th>Qty</th><th>Unit Value</th><th>Expiry / Date</th><th>Reorder</th></tr></thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><strong><?= e($row['item_name']) ?></strong></td>
                        <td><?= e($row['generic_name'] ?: '-') ?></td>
                        <td><?= e($row['category'] ?: '-') ?></td>
                        <td><?= e($row['batch_number'] ?: '-') ?></td>
                        <td><?= number_format((int) $row['quantity']) ?></td>
                        <td>$<?= number_format((float) $row['unit_price'], 2) ?></td>
                        <td><?= e($row['expiry_date']) ?></td>
                        <td><?= number_format((int) $row['reorder_level']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?><tr><td colspan="8"><div class="empty-state">No report data found.</div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

