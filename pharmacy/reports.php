<?php
require_once __DIR__ . '/../includes/auth.php';
require_access('pharmacy');
$page_title = 'Pharmacy Reports';
require_once __DIR__ . '/../includes/header.php';

function report_money($conn, $sql)
{
    return (float) $conn->query($sql)->fetch_row()[0];
}

$daily_revenue = report_money($conn, 'SELECT COALESCE(SUM(total_amount), 0) FROM pharmacy_sales WHERE DATE(created_at) = CURDATE()');
$weekly_revenue = report_money($conn, 'SELECT COALESCE(SUM(total_amount), 0) FROM pharmacy_sales WHERE YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)');
$monthly_revenue = report_money($conn, 'SELECT COALESCE(SUM(total_amount), 0) FROM pharmacy_sales WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())');
$prescription_sales = count_table($conn, 'SELECT COUNT(*) FROM pharmacy_sales WHERE prescription_id IS NOT NULL');
$low_stock = $conn->query('SELECT * FROM medicines WHERE quantity <= 10 ORDER BY quantity ASC')->fetch_all(MYSQLI_ASSOC);
$expired = $conn->query('SELECT * FROM medicines WHERE expiry_date < CURDATE() ORDER BY expiry_date ASC')->fetch_all(MYSQLI_ASSOC);
$sales = $conn->query('SELECT s.*, p.full_name patient_name FROM pharmacy_sales s LEFT JOIN patients p ON p.id = s.patient_id ORDER BY s.created_at DESC LIMIT 15')->fetch_all(MYSQLI_ASSOC);
$top_medicines = $conn->query('SELECT m.medicine_name, SUM(sm.quantity) total_qty, SUM(sm.subtotal) revenue
    FROM pharmacy_sale_medicines sm
    JOIN medicines m ON m.id = sm.medicine_id
    GROUP BY sm.medicine_id, m.medicine_name
    ORDER BY total_qty DESC
    LIMIT 8')->fetch_all(MYSQLI_ASSOC);
?>
<div class="patient-head">
    <div><h1>Pharmacy Reports</h1><p>Sales, revenue, prescription, low stock, and inventory movement reports.</p></div>
    <button class="add-patient-btn" onclick="window.print()"><i class="bi bi-printer"></i>Print Report</button>
</div>
<div class="patient-metrics">
    <article class="patient-metric"><div><p>Daily Sales</p><strong>$<?= number_format($daily_revenue, 2) ?></strong></div><span class="metric-icon mint"><i class="bi bi-cash"></i></span></article>
    <article class="patient-metric"><div><p>Weekly Sales</p><strong>$<?= number_format($weekly_revenue, 2) ?></strong></div><span class="metric-icon blue"><i class="bi bi-calendar-week"></i></span></article>
    <article class="patient-metric"><div><p>Monthly Revenue</p><strong>$<?= number_format($monthly_revenue, 2) ?></strong></div><span class="metric-icon blue"><i class="bi bi-graph-up"></i></span></article>
    <article class="patient-metric"><div><p>Prescription Sales</p><strong><?= number_format($prescription_sales) ?></strong></div><span class="metric-icon mint"><i class="bi bi-prescription2"></i></span></article>
    <article class="patient-metric"><div><p>Low Stock</p><strong><?= count($low_stock) ?></strong></div><span class="metric-icon pale-red"><i class="bi bi-exclamation-lg"></i></span></article>
    <article class="patient-metric"><div><p>Expired</p><strong><?= count($expired) ?></strong></div><span class="metric-icon red"><i class="bi bi-calendar-x"></i></span></article>
</div>
<section class="patient-management-card mb-4">
    <div class="patient-tabs"><div class="tab-links"><a href="dashboard.php">Dashboard</a><a href="medicines.php">Medicines</a><a href="sale.php">New Sale</a><a href="prescriptions.php">Prescriptions</a><a class="active" href="reports.php">Reports</a></div></div>
    <div class="patient-list-grid patient-list-head" style="grid-template-columns: 1fr 1.2fr .7fr .7fr 1fr .8fr;">
        <span>Sale Number</span><span>Customer</span><span>Items</span><span>Total</span><span>Method</span><span>Status</span>
    </div>
    <?php foreach ($sales as $sale): ?>
        <div class="patient-list-grid patient-list-row" style="grid-template-columns: 1fr 1.2fr .7fr .7fr 1fr .8fr;">
            <span><a href="receipt.php?id=<?= (int) $sale['id'] ?>"><?= e($sale['sale_number']) ?></a></span>
            <span><?= e($sale['customer_name'] ?: ($sale['patient_name'] ?: 'Walk-in Customer')) ?></span>
            <span><?= number_format((int) $sale['medicine_count']) ?></span>
            <span>$<?= number_format((float) $sale['total_amount'], 2) ?></span>
            <span><?= e($sale['payment_method']) ?></span>
            <span><em class="status-pill active"><?= e($sale['status']) ?></em></span>
        </div>
    <?php endforeach; ?>
    <?php if (!$sales): ?><div class="empty-state">No sales recorded yet.</div><?php endif; ?>
</section>

<div class="row g-4">
    <div class="col-lg-6">
        <section class="patient-management-card h-100">
            <div class="panel-title p-4 pb-3"><h2>Top Selling Medicines</h2></div>
            <?php foreach ($top_medicines as $medicine): ?>
                <div class="patient-list-grid patient-list-row" style="grid-template-columns: 1.4fr .7fr .8fr;">
                    <span><?= e($medicine['medicine_name']) ?></span>
                    <span><?= number_format((int) $medicine['total_qty']) ?> sold</span>
                    <span>$<?= number_format((float) $medicine['revenue'], 2) ?></span>
                </div>
            <?php endforeach; ?>
            <?php if (!$top_medicines): ?><div class="empty-state">No medicine movement yet.</div><?php endif; ?>
        </section>
    </div>
    <div class="col-lg-6">
        <section class="patient-management-card h-100">
            <div class="panel-title p-4 pb-3"><h2>Low Stock Report</h2></div>
            <?php foreach ($low_stock as $medicine): ?>
                <div class="patient-list-grid patient-list-row" style="grid-template-columns: 1.3fr .7fr 1fr;">
                    <span><?= e($medicine['medicine_name']) ?></span>
                    <span><em class="status-pill inactive"><?= (int) $medicine['quantity'] ?> left</em></span>
                    <span><?= e($medicine['supplier']) ?></span>
                </div>
            <?php endforeach; ?>
            <?php if (!$low_stock): ?><div class="empty-state">No low stock medicines.</div><?php endif; ?>
        </section>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
