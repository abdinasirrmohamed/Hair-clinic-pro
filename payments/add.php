<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/waafi_payment.php';
require_access('payments');
$page_title = 'Record Payment';
require_once __DIR__ . '/../includes/header.php';

$patients     = $conn->query('SELECT id, full_name FROM patients ORDER BY full_name')->fetch_all(MYSQLI_ASSOC);
$appointments = $conn->query('SELECT a.id, a.reason, p.full_name FROM appointments a JOIN patients p ON p.id = a.patient_id ORDER BY a.appointment_date DESC')->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id     = (int) $_POST['patient_id'];
    $appointment_id = $_POST['appointment_id'] !== '' ? (int) $_POST['appointment_id'] : null;
    $amount         = (float) $_POST['amount'];
    $method         = $_POST['payment_method'];
    $status         = $_POST['payment_status'];
    $reference      = trim($_POST['reference_number'] ?? '');
    $notes          = trim($_POST['notes'] ?? '');
    $account_no     = trim($_POST['account_no'] ?? '');
    $created_by     = (int) ($_SESSION['admin_id'] ?? 0);

    $mobile_methods = ['EVC Plus', 'Sahal'];

    if (in_array($method, $mobile_methods, true)) {
        if ($account_no === '') {
            flash('danger', $method . ' payment requires a valid phone number.');
            redirect('/payments/add.php');
        }

        $invoice_id   = 'PAY-' . date('Ymd') . '-' . rand(1000, 9999);
        $reference_id = 'REF-' . time();

        $waafi  = new WaafiPayment();
        $result = $waafi->charge($amount, $account_no, $reference_id, $invoice_id, 'Clinic Payment');

        if (!$result['success']) {
            flash('danger', $method . ' payment failed: ' . $result['message']);
            redirect('/payments/add.php');
        }

        $reference = $result['transaction_id'];
        $status    = 'Paid';
    }

    $stmt = $conn->prepare('INSERT INTO payments (patient_id, appointment_id, amount, payment_method, payment_status, reference_number, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('iidssssi', $patient_id, $appointment_id, $amount, $method, $status, $reference, $notes, $created_by);
    $stmt->execute();
    $payment_id     = $conn->insert_id;
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
<form class="form-panel" method="post" id="paymentForm">
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Patient</label>
            <select class="form-select" name="patient_id" required>
                <?php foreach ($patients as $patient): ?>
                    <option value="<?= $patient['id'] ?>"><?= e($patient['full_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Appointment</label>
            <select class="form-select" name="appointment_id">
                <option value="">General payment</option>
                <?php foreach ($appointments as $appointment): ?>
                    <option value="<?= $appointment['id'] ?>"><?= e($appointment['full_name']) ?> - <?= e($appointment['reason']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Amount (USD)</label>
            <input class="form-control" type="number" step="0.01" min="0.01" name="amount" id="amount" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">Method</label>
            <select class="form-select" name="payment_method" id="paymentMethod">
                <?php foreach (['Cash', 'EVC Plus', 'Sahal', 'Bank Transfer'] as $m): ?>
                    <option><?= e($m) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Status</label>
            <select class="form-select" name="payment_status" id="paymentStatus">
                <option>Paid</option><option>Partial</option><option>Outstanding</option>
            </select>
        </div>

        <!-- Mobile wallet fields (EVC Plus / Sahal) -->
        <div class="col-md-6" id="mobileWalletFields" style="display:none;">
            <label class="form-label">EVC/Sahal Phone Number</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-phone"></i></span>
                <input class="form-control" type="tel" name="account_no" id="accountNo"
                       placeholder="e.g. 0618827482 or 2526xxxxxxx"
                       pattern="[0-9]{9,15}">
            </div>
            <div class="form-text">Customer will receive a payment prompt on their phone.</div>
        </div>

        <div class="col-md-6" id="referenceWrapper">
            <label class="form-label">Reference Number <span class="text-muted" id="refHint">(optional)</span></label>
            <input class="form-control" name="reference_number" id="referenceNumber">
        </div>

        <div class="col-12">
            <label class="form-label">Notes</label>
            <textarea class="form-control" name="notes" rows="3"></textarea>
        </div>
    </div>

    <!-- Live payment summary for mobile methods -->
    <div id="walletSummary" class="alert alert-info mt-3" style="display:none;">
        <i class="bi bi-phone-fill"></i>
        <strong>Mobile Wallet Payment</strong> — Amount <strong id="summaryAmount">$0.00</strong> will be charged to
        <strong id="summaryPhone">—</strong> via <strong id="summaryMethod">—</strong>.
        The customer must approve the prompt on their phone.
    </div>

    <div class="mt-4 d-flex gap-2 align-items-center">
        <button class="btn btn-primary" type="submit" id="submitBtn">
            <span id="btnLabel"><i class="bi bi-check2-circle"></i> Save Payment</span>
            <span id="btnSpinner" class="spinner-border spinner-border-sm ms-1" style="display:none;"></span>
        </button>
        <a class="btn btn-secondary" href="view.php">Cancel</a>
    </div>
</form>

<script>
const methodSelect  = document.getElementById('paymentMethod');
const mobileFields  = document.getElementById('mobileWalletFields');
const walletSummary = document.getElementById('walletSummary');
const accountNo     = document.getElementById('accountNo');
const amountInput   = document.getElementById('amount');
const statusSelect  = document.getElementById('paymentStatus');
const refHint       = document.getElementById('refHint');
const mobilePayMethods = ['EVC Plus', 'Sahal'];

function toggleMobileFields() {
    const isMobile = mobilePayMethods.includes(methodSelect.value);
    mobileFields.style.display  = isMobile ? '' : 'none';
    accountNo.required          = isMobile;
    if (isMobile) {
        statusSelect.value = 'Paid';
        refHint.textContent = '(auto-filled from gateway)';
        updateSummary();
    } else {
        refHint.textContent = '(optional)';
        walletSummary.style.display = 'none';
    }
}

function updateSummary() {
    const isMobile = mobilePayMethods.includes(methodSelect.value);
    if (!isMobile) return;
    const phone  = accountNo.value.trim();
    const amount = parseFloat(amountInput.value) || 0;
    document.getElementById('summaryAmount').textContent = '$' + amount.toFixed(2);
    document.getElementById('summaryPhone').textContent  = phone || '—';
    document.getElementById('summaryMethod').textContent = methodSelect.value;
    walletSummary.style.display = (phone && amount > 0) ? '' : 'none';
}

methodSelect.addEventListener('change', toggleMobileFields);
accountNo.addEventListener('input', updateSummary);
amountInput.addEventListener('input', updateSummary);

document.getElementById('paymentForm').addEventListener('submit', function () {
    const isMobile = mobilePayMethods.includes(methodSelect.value);
    if (isMobile) {
        document.getElementById('btnLabel').innerHTML = '<i class="bi bi-send"></i> Sending Payment Request…';
        document.getElementById('btnSpinner').style.display = '';
        document.getElementById('submitBtn').disabled = true;
    }
});

toggleMobileFields();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
