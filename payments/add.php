<?php
require_once __DIR__ . '/../includes/auth.php';
require_access('payments');
$page_title = 'Record Payment';
require_once __DIR__ . '/../includes/header.php';

$patients = $conn->query('SELECT id, full_name FROM patients ORDER BY full_name')->fetch_all(MYSQLI_ASSOC);
$appointments = $conn->query('SELECT a.id, a.reason, p.full_name FROM appointments a JOIN patients p ON p.id = a.patient_id ORDER BY a.appointment_date DESC')->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = (int) $_POST['patient_id'];
    $appointment_id = $_POST['appointment_id'] !== '' ? (int) $_POST['appointment_id'] : null;
    $amount = (float) $_POST['amount'];
    $method = $_POST['payment_method'];
    $status = $_POST['payment_status'];
    $reference = trim($_POST['reference_number'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $created_by = (int) ($_SESSION['admin_id'] ?? 0);

    $stmt = $conn->prepare('INSERT INTO payments (patient_id, appointment_id, amount, payment_method, payment_status, reference_number, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('iidssssi', $patient_id, $appointment_id, $amount, $method, $status, $reference, $notes, $created_by);
    $stmt->execute();
    $payment_id = $conn->insert_id;
    $receipt_number = 'RCP-' . date('Ymd') . '-' . str_pad((string) $payment_id, 5, '0', STR_PAD_LEFT);
    $stmt = $conn->prepare('INSERT INTO receipts (payment_id, receipt_number) VALUES (?, ?)');
    $stmt->bind_param('is', $payment_id, $receipt_number);
    $stmt->execute();
    log_activity('Recorded patient payment', 'Payments', $payment_id);
    flash('success', 'Payment recorded and receipt generated.');
    redirect('/payments/receipt.php?id=' . $payment_id);
}
?>
<div class="page-head"><h1 class="h3 mb-0">Record Payment</h1></div>
<form class="form-panel" method="post">
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Patient</label><select class="form-select" name="patient_id" required><?php foreach ($patients as $patient): ?><option value="<?= $patient['id'] ?>"><?= e($patient['full_name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6"><label class="form-label">Appointment</label><select class="form-select" name="appointment_id"><option value="">General payment</option><?php foreach ($appointments as $appointment): ?><option value="<?= $appointment['id'] ?>"><?= e($appointment['full_name']) ?> - <?= e($appointment['reason']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label">Amount</label><input class="form-control" type="number" step="0.01" min="0" name="amount" required></div>
        <div class="col-md-4"><label class="form-label">Method</label><select class="form-select" name="payment_method"><?php foreach (['Cash','EVC Plus','Sahal','Bank Transfer'] as $method): ?><option><?= e($method) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label">Status</label><select class="form-select" name="payment_status"><option>Paid</option><option>Partial</option><option>Outstanding</option></select></div>
        <div class="col-md-6"><label class="form-label">Reference Number</label><input class="form-control" name="reference_number"></div>
        <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="3"></textarea></div>
    </div>
    <div class="mt-4"><button class="btn btn-primary">Save Payment</button> <a class="btn btn-secondary" href="view.php">Cancel</a></div>
</form>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
