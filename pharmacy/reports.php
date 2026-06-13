<?php
require_once __DIR__ . '/../includes/auth.php';
require_access('pharmacy');
$page_title = 'Pharmacy Reports';
require_once __DIR__ . '/../includes/header.php';

$daily_revenue = count_table($conn, 'SELECT COALESCE(SUM(total_amount), 0) FROM pharmacy_invoices WHERE DATE(created_at) = CURDATE()');
$monthly_revenue = count_table($conn, 'SELECT COALESCE(SUM(total_amount), 0) FROM pharmacy_invoices WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())');
$low_stock = $conn->query('SELECT * FROM medicines WHERE quantity <= 10 ORDER BY quantity ASC')->fetch_all(MYSQLI_ASSOC);
$expired = $conn->query('SELECT * FROM medicines WHERE expiry_date < CURDATE() ORDER BY expiry_date ASC')->fetch_all(MYSQLI_ASSOC);
$sales = $conn->query('SELECT i.*, p.full_name FROM pharmacy_invoices i JOIN patients p ON p.id = i.patient_id ORDER BY i.created_at DESC LIMIT 15')->fetch_all(MYSQLI_ASSOC);
?>
<div class="patient-head">
    <div><h1>Pharmacy Reports</h1><p>Sales, revenue, low stock, and expired medicine monitoring.</p></div>
    <a class="add-patient-btn" href="sale.php"><i class="bi bi-plus-lg"></i>New Sale</a>
</div>
<div class="patient-metrics">
    <article class="patient-metric"><div><p>Daily Revenue</p><strong>$<?= number_format($daily_revenue, 2) ?></strong></div><span class="metric-icon mint"><i class="bi bi-cash"></i></span></article>
    <article class="patient-metric"><div><p>Monthly Revenue</p><strong>$<?= number_format($monthly_revenue, 2) ?></strong></div><span class="metric-icon blue"><i class="bi bi-graph-up"></i></span></article>
    <article class="patient-metric"><div><p>Low Stock</p><strong><?= count($low_stock) ?></strong></div><span class="metric-icon pale-red"><i class="bi bi-exclamation-lg"></i></span></article>
    <article class="patient-metric"><div><p>Expired</p><strong><?= count($expired) ?></strong></div><span class="metric-icon red"><i class="bi bi-calendar-x"></i></span></article>
</div>
<section class="patient-management-card">
    <div class="patient-tabs"><div class="tab-links"><a href="medicines.php">Medicines</a><a href="sale.php">New Sale</a><a class="active" href="reports.php">Reports</a></div></div>
    <div class="patient-list-grid patient-list-head" style="grid-template-columns: 1fr 1.3fr 1fr 1fr 1fr;">
        <span>Invoice</span><span>Patient</span><span>Total</span><span>Method</span><span>Status</span>
    </div>
    <?php foreach ($sales as $sale): ?>
        <div class="patient-list-grid patient-list-row" style="grid-template-columns: 1fr 1.3fr 1fr 1fr 1fr;">
            <span><?= e($sale['invoice_number']) ?></span><span><?= e($sale['full_name']) ?></span><span>$<?= number_format((float) $sale['total_amount'], 2) ?></span><span><?= e($sale['payment_method']) ?></span><span><em class="status-pill <?= $sale['payment_status'] === 'Paid' ? 'active' : 'inactive' ?>"><?= e($sale['payment_status']) ?></em></span>
        </div>
    <?php endforeach; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
