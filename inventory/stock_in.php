<?php
require_once __DIR__ . '/../includes/auth.php';
require_access('inventory');
$is_purchase_page = basename($_SERVER['SCRIPT_NAME'] ?? '') === 'purchase.php';
$stock_in_url = $is_purchase_page ? '/inventory/purchase.php' : '/inventory/stock_in.php';
$medicines = $conn->query('SELECT * FROM medicines ORDER BY medicine_name')->fetch_all(MYSQLI_ASSOC);
$suppliers = $conn->query('SELECT * FROM suppliers ORDER BY company_name')->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $medicine_id = (int) ($_POST['medicine_id'] ?? 0);
        $supplier_id = (int) ($_POST['supplier_id'] ?? 0);
        $quantity = max(1, (int) ($_POST['quantity'] ?? 1));
        $unit_cost = max(0, (float) ($_POST['unit_cost'] ?? 0));
        $batch = trim($_POST['batch_number'] ?? '');
        $expiry = $_POST['expiry_date'] ?: null;
        $purchase_date = $_POST['purchase_date'] ?: date('Y-m-d');
        $invoice = upload_inventory_file('invoice');
        $transaction = 'STKIN-' . date('Ymd') . '-' . random_int(1000, 9999);

        $conn->begin_transaction();
        $stmt = $conn->prepare('UPDATE medicines SET quantity = quantity + ?, unit_price = ?, supplier_id = NULLIF(?, 0), batch_number = COALESCE(NULLIF(?, ""), batch_number), expiry_date = COALESCE(?, expiry_date), supplier = COALESCE((SELECT company_name FROM suppliers WHERE id = NULLIF(?, 0)), supplier) WHERE id = ?');
        $stmt->bind_param('idissii', $quantity, $unit_cost, $supplier_id, $batch, $expiry, $supplier_id, $medicine_id);
        $stmt->execute();
        record_inventory_movement($medicine_id, 'Stock In', $quantity, $unit_cost, ['transaction_number' => $transaction, 'supplier_id' => $supplier_id ?: null, 'purpose' => 'Purchased stock', 'reference_type' => 'Stock In', 'invoice_path' => $invoice, 'movement_date' => $purchase_date . ' ' . date('H:i:s')]);
        $conn->commit();
        log_activity('Created stock in transaction', 'Inventory', $transaction);
        flash('success', 'Purchase completed. Inventory quantity and pharmacy medicine stock were updated.');
    } catch (Throwable $e) {
        if ($conn->errno === 0) {
            $conn->rollback();
        }
        flash('danger', $e->getMessage());
    }
    redirect($stock_in_url);
}

$recent = $conn->query("SELECT im.*, m.medicine_name, s.company_name FROM inventory_movements im JOIN medicines m ON m.id = im.medicine_id LEFT JOIN suppliers s ON s.id = im.supplier_id WHERE im.movement_type = 'Stock In' ORDER BY im.movement_date DESC LIMIT 15")->fetch_all(MYSQLI_ASSOC);
$page_title = $is_purchase_page ? 'Purchase Stock' : 'Stock In';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="patient-head"><div><h1><?= $is_purchase_page ? 'Purchase / Delivered Stock' : 'Stock In Management' ?></h1><p>Record purchased or delivered medicine stock and automatically update pharmacy medicine quantity.</p></div></div>
<section class="patient-management-card">
    <div class="patient-tabs"><div class="tab-links"><a href="items.php">General Items</a><a href="medicines.php">Medicines</a><a class="<?= $is_purchase_page ? 'active' : '' ?>" href="purchase.php">Purchase</a><a class="<?= !$is_purchase_page ? 'active' : '' ?>" href="stock_in.php">Stock In</a><a href="stock_out.php">Stock Out</a><a href="suppliers.php">Suppliers</a><a href="movements.php">Movements</a><a href="reports.php">Reports</a></div></div>
    <div class="row g-4 p-4">
        <div class="col-lg-5"><form class="form-panel m-0" method="post" enctype="multipart/form-data">
            <div class="row g-3">
                <div class="col-12"><label class="form-label">Supplier</label><select class="form-select" name="supplier_id"><option value="">Select supplier</option><?php foreach ($suppliers as $supplier): ?><option value="<?= (int) $supplier['id'] ?>"><?= e($supplier['company_name']) ?></option><?php endforeach; ?></select></div>
                <div class="col-12"><label class="form-label">Item Name</label><select class="form-select" name="medicine_id" required><?php foreach ($medicines as $medicine): ?><option value="<?= (int) $medicine['id'] ?>"><?= e($medicine['medicine_name']) ?> - Stock <?= number_format((int) $medicine['quantity']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-6"><label class="form-label">Quantity Added</label><input class="form-control" type="number" min="1" name="quantity" required></div>
                <div class="col-md-6"><label class="form-label">Purchase Price</label><input class="form-control" type="number" min="0" step="0.01" name="unit_cost" required></div>
                <div class="col-md-6"><label class="form-label">Batch Number</label><input class="form-control" name="batch_number"></div>
                <div class="col-md-6"><label class="form-label">Expiry Date</label><input class="form-control" type="date" name="expiry_date"></div>
                <div class="col-md-6"><label class="form-label">Purchase Date</label><input class="form-control" type="date" name="purchase_date" value="<?= e(date('Y-m-d')) ?>"></div>
                <div class="col-md-6"><label class="form-label">Purchase Invoice</label><input class="form-control" type="file" name="invoice" accept="application/pdf,image/*"></div>
            </div>
            <button class="btn btn-primary mt-4"><i class="bi bi-box-arrow-in-down"></i><?= $is_purchase_page ? 'Complete Purchase' : 'Complete Stock In' ?></button>
        </form></div>
        <div class="col-lg-7"><section class="form-panel m-0 h-100"><h2 class="h5 mb-3">Recent Stock In</h2><?php foreach ($recent as $row): ?><div class="d-flex justify-content-between border-bottom py-2"><span><strong><?= e($row['medicine_name']) ?></strong><small class="d-block text-muted"><?= e($row['transaction_number']) ?> - <?= e($row['company_name'] ?: 'No supplier') ?></small></span><span class="text-end"><?= number_format((int) $row['quantity']) ?><small class="d-block text-muted">$<?= number_format((float) $row['total_cost'], 2) ?></small></span></div><?php endforeach; ?><?php if (!$recent): ?><div class="empty-state">No stock in transactions yet.</div><?php endif; ?></section></div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

