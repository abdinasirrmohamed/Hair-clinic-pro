<?php
require_once __DIR__ . '/../includes/auth.php';
require_access('pharmacy');
$page_title = 'Pharmacy Dashboard';
require_once __DIR__ . '/../includes/header.php';

function pharmacy_money($conn, $sql)
{
    return (float) $conn->query($sql)->fetch_row()[0];
}

$total_medicines = count_table($conn, 'SELECT COUNT(*) FROM medicines');
$sales_today = count_table($conn, 'SELECT COUNT(*) FROM pharmacy_sales WHERE DATE(created_at) = CURDATE()');
$revenue_today = pharmacy_money($conn, 'SELECT COALESCE(SUM(total_amount), 0) FROM pharmacy_sales WHERE DATE(created_at) = CURDATE()');
$pending_prescriptions = count_table($conn, "SELECT COUNT(*) FROM prescriptions WHERE status = 'Pending'");
$dispensed_prescriptions = count_table($conn, "SELECT COUNT(*) FROM prescriptions WHERE status IN ('Dispensed','Completed')");
$low_stock = count_table($conn, 'SELECT COUNT(*) FROM medicines WHERE quantity <= 10');
$expired = count_table($conn, 'SELECT COUNT(*) FROM medicines WHERE expiry_date < CURDATE()');
$recent_sales = $conn->query('SELECT s.*, p.full_name patient_name FROM pharmacy_sales s LEFT JOIN patients p ON p.id = s.patient_id ORDER BY s.created_at DESC LIMIT 8')->fetch_all(MYSQLI_ASSOC);
$pending_rx = $conn->query("SELECT rx.*, p.full_name patient_name, d.full_name doctor_name FROM prescriptions rx JOIN patients p ON p.id = rx.patient_id JOIN doctors d ON d.id = rx.doctor_id WHERE rx.status = 'Pending' ORDER BY rx.created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
?>
<div class="patient-head">
    <div><h1>Pharmacy Dashboard</h1><p>POS activity, prescription queue, stock alerts, and revenue overview.</p></div>
    <a class="add-patient-btn" href="sale.php"><i class="bi bi-cart-plus"></i>New Sale</a>
</div>

<div class="patient-metrics">
    <article class="patient-metric"><div><p>Total Medicines</p><strong><?= number_format($total_medicines) ?></strong></div><span class="metric-icon blue"><i class="bi bi-capsule"></i></span></article>
    <article class="patient-metric"><div><p>Total Sales Today</p><strong><?= number_format($sales_today) ?></strong></div><span class="metric-icon mint"><i class="bi bi-receipt"></i></span></article>
    <article class="patient-metric"><div><p>Today's Revenue</p><strong>$<?= number_format($revenue_today, 2) ?></strong></div><span class="metric-icon blue"><i class="bi bi-cash-stack"></i></span></article>
    <article class="patient-metric"><div><p>Pending Prescriptions</p><strong><?= number_format($pending_prescriptions) ?></strong></div><span class="metric-icon pale-red"><i class="bi bi-prescription2"></i></span></article>
    <article class="patient-metric"><div><p>Dispensed Prescriptions</p><strong><?= number_format($dispensed_prescriptions) ?></strong></div><span class="metric-icon mint"><i class="bi bi-check2-circle"></i></span></article>
    <article class="patient-metric"><div><p>Low Stock Medicines</p><strong><?= number_format($low_stock) ?></strong></div><span class="metric-icon red"><i class="bi bi-exclamation-triangle"></i></span></article>
    <article class="patient-metric"><div><p>Expired Medicines</p><strong><?= number_format($expired) ?></strong></div><span class="metric-icon red"><i class="bi bi-calendar-x"></i></span></article>
</div>

<section class="patient-management-card mb-4">
    <div class="patient-tabs"><div class="tab-links"><a href="medicines.php">Medicines</a><a href="sale.php">New Sale</a><a href="prescriptions.php">Prescriptions</a><a href="sales_history.php">Sales History</a><a href="reports.php">Reports</a></div></div>
    <div class="patient-list-grid patient-list-head" style="grid-template-columns: 1fr 1.3fr .8fr .8fr .9fr .8fr;">
        <span>Sale Number</span><span>Customer</span><span>Items</span><span>Total</span><span>Payment</span><span>Status</span>
    </div>
    <?php foreach ($recent_sales as $sale): ?>
        <div class="patient-list-grid patient-list-row" style="grid-template-columns: 1fr 1.3fr .8fr .8fr .9fr .8fr;">
            <span><strong><?= e($sale['sale_number']) ?></strong><small class="d-block text-muted"><?= e(date('M j, h:i A', strtotime($sale['created_at']))) ?></small></span>
            <span><?= e($sale['customer_name'] ?: ($sale['patient_name'] ?: 'Walk-in Customer')) ?></span>
            <span><?= number_format((int) $sale['medicine_count']) ?></span>
            <span>$<?= number_format((float) $sale['total_amount'], 2) ?></span>
            <span><?= e($sale['payment_method']) ?></span>
            <span><em class="status-pill active"><?= e($sale['status']) ?></em></span>
        </div>
    <?php endforeach; ?>
    <?php if (!$recent_sales): ?><div class="empty-state">No pharmacy sales recorded yet.</div><?php endif; ?>
</section>

<section class="patient-management-card">
    <div class="panel-title p-4 pb-3"><h2>Pending Prescriptions</h2><a href="prescriptions.php">View All</a></div>
    <?php foreach ($pending_rx as $rx): ?>
        <div class="patient-list-grid patient-list-row" style="grid-template-columns: 1fr 1.4fr 1.4fr .9fr;">
            <span><strong><?= e($rx['prescription_number']) ?></strong></span>
            <span><?= e($rx['patient_name']) ?></span>
            <span><?= e($rx['doctor_name']) ?></span>
            <span><a class="btn btn-sm btn-primary" href="dispense.php?id=<?= (int) $rx['id'] ?>">Dispense</a></span>
        </div>
    <?php endforeach; ?>
    <?php if (!$pending_rx): ?><div class="empty-state">No pending prescriptions.</div><?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
