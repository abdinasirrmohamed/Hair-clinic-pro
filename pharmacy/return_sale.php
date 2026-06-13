<?php
require_once __DIR__ . '/../includes/auth.php';
require_access('pharmacy');

$id = (int) ($_GET['id'] ?? 0);
$stmt = $conn->prepare('SELECT * FROM pharmacy_sales WHERE id = ?');
$stmt->bind_param('i', $id);
$sale = fetch_one($stmt);
if (!$sale) {
    redirect('/pharmacy/sales_history.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reason = trim($_POST['return_reason'] ?? '');
    $transaction_started = false;
    try {
        if ($sale['status'] !== 'Paid') {
            throw new Exception('Only paid sales can be returned.');
        }
        $conn->begin_transaction();
        $transaction_started = true;
        $stmt = $conn->prepare('SELECT medicine_id, quantity FROM pharmacy_sale_medicines WHERE sale_id = ?');
        $stmt->bind_param('i', $id);
        $items = fetch_all($stmt);

        foreach ($items as $item) {
            $stmt = $conn->prepare('UPDATE medicines SET quantity = quantity + ? WHERE id = ?');
            $qty = (int) $item['quantity'];
            $medicine_id = (int) $item['medicine_id'];
            $stmt->bind_param('ii', $qty, $medicine_id);
            $stmt->execute();
            record_inventory_movement($medicine_id, 'Inventory Adjustment', $qty, 0, [
                'department' => 'Pharmacy',
                'purpose' => 'Returned sale stock restored',
                'reference_type' => 'Pharmacy Return',
                'reference_id' => $id,
            ]);
        }

        $stmt = $conn->prepare("UPDATE pharmacy_sales SET status = 'Returned', returned_at = NOW(), return_reason = ? WHERE id = ?");
        $stmt->bind_param('si', $reason, $id);
        $stmt->execute();
        $conn->commit();
        log_activity('Returned pharmacy sale', 'Pharmacy', $id);
        flash('success', 'Sale returned and medicine stock restored.');
        redirect('/pharmacy/sales_history.php');
    } catch (Throwable $e) {
        if ($transaction_started) {
            $conn->rollback();
        }
        flash('danger', $e->getMessage());
        redirect('/pharmacy/return_sale.php?id=' . $id);
    }
}

$page_title = 'Return Pharmacy Sale';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="patient-head">
    <div><h1>Return Sale</h1><p><?= e($sale['sale_number']) ?> will be marked returned and stock will be restored.</p></div>
    <a class="add-patient-btn" href="sales_history.php"><i class="bi bi-arrow-left"></i>Sales History</a>
</div>
<form class="form-panel" method="post">
    <div class="alert alert-warning">Confirming this return will add the sold medicines back into inventory.</div>
    <div class="mb-3">
        <label class="form-label">Return Reason</label>
        <textarea class="form-control" name="return_reason" rows="4" placeholder="Reason for return"></textarea>
    </div>
    <button class="btn btn-danger" type="submit"><i class="bi bi-arrow-counterclockwise"></i> Confirm Return</button>
    <a class="btn btn-secondary" href="sales_history.php">Cancel</a>
</form>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
