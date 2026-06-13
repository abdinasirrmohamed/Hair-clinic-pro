<?php
require_once __DIR__ . '/../includes/auth.php';
require_access('pharmacy');
$page_title = 'Create Pharmacy Invoice';
require_once __DIR__ . '/../includes/header.php';

$patients = $conn->query('SELECT id, full_name FROM patients ORDER BY full_name')->fetch_all(MYSQLI_ASSOC);
$medicines = $conn->query('SELECT id, medicine_name, quantity, unit_price FROM medicines ORDER BY medicine_name')->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = (int) $_POST['patient_id'];
    $method = $_POST['payment_method'];
    $status = $_POST['payment_status'];
    $notes = trim($_POST['notes'] ?? '');
    $medicine_ids = $_POST['medicine_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $created_by = (int) ($_SESSION['admin_id'] ?? 0);

    try {
        $conn->begin_transaction();
        $invoice_number = 'PH-' . date('Ymd') . '-' . random_int(1000, 9999);
        $total = 0;
        $stmt = $conn->prepare('INSERT INTO pharmacy_invoices (invoice_number, patient_id, total_amount, payment_method, payment_status, notes, created_by) VALUES (?, ?, 0, ?, ?, ?, ?)');
        $stmt->bind_param('sisssi', $invoice_number, $patient_id, $method, $status, $notes, $created_by);
        $stmt->execute();
        $invoice_id = $conn->insert_id;

        foreach ($medicine_ids as $index => $medicine_id) {
            $medicine_id = (int) $medicine_id;
            $quantity = max(0, (int) ($quantities[$index] ?? 0));
            if ($medicine_id <= 0 || $quantity <= 0) {
                continue;
            }
            $stmt = $conn->prepare('SELECT quantity, unit_price FROM medicines WHERE id = ? FOR UPDATE');
            $stmt->bind_param('i', $medicine_id);
            $medicine = fetch_one($stmt);
            if (!$medicine || (int) $medicine['quantity'] < $quantity) {
                throw new Exception('Insufficient stock for one or more medicines.');
            }
            $unit_price = (float) $medicine['unit_price'];
            $line_total = $unit_price * $quantity;
            $total += $line_total;
            $stmt = $conn->prepare('INSERT INTO pharmacy_invoice_items (invoice_id, medicine_id, quantity, unit_price, line_total) VALUES (?, ?, ?, ?, ?)');
            $stmt->bind_param('iiidd', $invoice_id, $medicine_id, $quantity, $unit_price, $line_total);
            $stmt->execute();
            $stmt = $conn->prepare('UPDATE medicines SET quantity = quantity - ? WHERE id = ?');
            $stmt->bind_param('ii', $quantity, $medicine_id);
            $stmt->execute();
        }

        if ($total <= 0) {
            throw new Exception('Add at least one medicine to the invoice.');
        }
        $stmt = $conn->prepare('UPDATE pharmacy_invoices SET total_amount = ? WHERE id = ?');
        $stmt->bind_param('di', $total, $invoice_id);
        $stmt->execute();
        $stmt = $conn->prepare('INSERT INTO pharmacy_payments (invoice_id, amount, payment_method) VALUES (?, ?, ?)');
        $stmt->bind_param('ids', $invoice_id, $total, $method);
        $stmt->execute();
        $conn->commit();
        flash('success', 'Pharmacy invoice created and stock updated.');
        redirect('/pharmacy/reports.php');
    } catch (Throwable $e) {
        $conn->rollback();
        flash('danger', $e->getMessage());
        redirect('/pharmacy/sale.php');
    }
}
?>
<div class="page-head"><h1 class="h3 mb-0">Create Pharmacy Invoice</h1></div>
<form class="form-panel" method="post">
    <div class="row g-3">
        <div class="col-md-4"><label class="form-label">Patient</label><select class="form-select" name="patient_id" required><?php foreach ($patients as $patient): ?><option value="<?= $patient['id'] ?>"><?= e($patient['full_name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label">Payment Method</label><select class="form-select" name="payment_method"><option>Cash</option><option>Mobile Money</option><option>Bank Transfer</option></select></div>
        <div class="col-md-4"><label class="form-label">Payment Status</label><select class="form-select" name="payment_status"><option>Paid</option><option>Partial</option><option>Outstanding</option></select></div>
        <?php for ($i = 0; $i < 4; $i++): ?>
            <div class="col-md-8"><label class="form-label">Medicine <?= $i + 1 ?></label><select class="form-select" name="medicine_id[]"><option value="">Select medicine</option><?php foreach ($medicines as $medicine): ?><option value="<?= $medicine['id'] ?>"><?= e($medicine['medicine_name']) ?> - Stock <?= (int) $medicine['quantity'] ?> - $<?= number_format((float) $medicine['unit_price'], 2) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-4"><label class="form-label">Quantity</label><input class="form-control" type="number" name="quantity[]" min="0" value="0"></div>
        <?php endfor; ?>
        <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="3"></textarea></div>
    </div>
    <div class="mt-4"><button class="btn btn-primary">Create Invoice</button> <a class="btn btn-secondary" href="medicines.php">Cancel</a></div>
</form>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
