<?php
require_once __DIR__ . '/../includes/auth.php';
require_access('payments');
$page_title = 'Payment Management';
require_once __DIR__ . '/../includes/header.php';

$payments = $conn->query(
    'SELECT pay.*, p.full_name, a.reason, r.receipt_number
     FROM payments pay
     JOIN patients p ON p.id = pay.patient_id
     LEFT JOIN appointments a ON a.id = pay.appointment_id
     LEFT JOIN receipts r ON r.payment_id = pay.id
     ORDER BY pay.created_at DESC'
)->fetch_all(MYSQLI_ASSOC);
$total_paid = count_table($conn, "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payment_status = 'Paid'");
$total_outstanding = count_table($conn, "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payment_status = 'Outstanding'");
?>
<div class="patient-head">
    <div>
        <h1>Payment Management</h1>
        <p>Record patient payments, monitor balances, and generate professional receipts.</p>
    </div>
    <a class="add-patient-btn" href="add.php"><i class="bi bi-credit-card"></i>Record Payment</a>
</div>

<div class="patient-metrics">
    <article class="patient-metric"><div><p>Total Paid</p><strong>$<?= number_format($total_paid, 2) ?></strong></div><span class="metric-icon mint"><i class="bi bi-cash-coin"></i></span></article>
    <article class="patient-metric"><div><p>Outstanding</p><strong class="red-text">$<?= number_format($total_outstanding, 2) ?></strong></div><span class="metric-icon pale-red"><i class="bi bi-exclamation-lg"></i></span></article>
    <article class="patient-metric"><div><p>Transactions</p><strong><?= count($payments) ?></strong></div><span class="metric-icon blue"><i class="bi bi-receipt"></i></span></article>
    <article class="patient-metric"><div><p>Methods</p><strong>4</strong></div><span class="metric-icon blue"><i class="bi bi-wallet2"></i></span></article>
</div>

<section class="patient-management-card">
    <div class="patient-list-grid patient-list-head" style="grid-template-columns: 1.3fr 1.1fr .8fr .9fr .9fr .9fr;">
        <span>Patient</span><span>Appointment</span><span>Amount</span><span>Method</span><span>Status</span><span>Receipt</span>
    </div>
    <?php foreach ($payments as $payment): ?>
        <div class="patient-list-grid patient-list-row" style="grid-template-columns: 1.3fr 1.1fr .8fr .9fr .9fr .9fr;">
            <span><strong><?= e($payment['full_name']) ?></strong><small class="d-block text-muted"><?= e(date('M j, Y', strtotime($payment['created_at']))) ?></small></span>
            <span><?= e($payment['reason'] ?: 'General Payment') ?></span>
            <span>$<?= number_format((float) $payment['amount'], 2) ?></span>
            <span><?= e($payment['payment_method']) ?></span>
            <span><em class="status-pill <?= $payment['payment_status'] === 'Paid' ? 'active' : 'inactive' ?>"><?= e($payment['payment_status']) ?></em></span>
            <span><a class="btn btn-sm btn-outline-primary" href="receipt.php?id=<?= $payment['id'] ?>">Receipt</a></span>
        </div>
    <?php endforeach; ?>
    <?php if (!$payments): ?><div class="empty-state">No payments recorded yet.</div><?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
