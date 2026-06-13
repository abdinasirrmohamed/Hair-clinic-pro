<?php
require_once __DIR__ . '/../includes/auth.php';
require_access('pharmacy');
$page_title = 'Pharmacy Sales History';
require_once __DIR__ . '/../includes/header.php';

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? 'All';
$where = 'WHERE 1=1';
$types = '';
$params = [];

if ($search !== '') {
    $like = '%' . $search . '%';
    $where .= ' AND (s.sale_number LIKE ? OR s.customer_name LIKE ? OR p.full_name LIKE ? OR s.payment_method LIKE ?)';
    $types .= 'ssss';
    array_push($params, $like, $like, $like, $like);
}

if (in_array($status, ['Paid', 'Pending', 'Cancelled', 'Returned'], true)) {
    $where .= ' AND s.status = ?';
    $types .= 's';
    $params[] = $status;
}

$stmt = $conn->prepare("SELECT s.*, p.full_name patient_name
    FROM pharmacy_sales s
    LEFT JOIN patients p ON p.id = s.patient_id
    $where
    ORDER BY s.created_at DESC
    LIMIT 100");
if ($types !== '') {
    bind_params($stmt, $types, $params);
}
$sales = fetch_all($stmt);
?>
<div class="patient-head">
    <div><h1>Sales History</h1><p>Track pharmacy POS sales, receipts, prescription sales, and returns.</p></div>
    <a class="add-patient-btn" href="sale.php"><i class="bi bi-cart-plus"></i>New Sale</a>
</div>

<section class="patient-management-card">
    <div class="patient-tabs"><div class="tab-links"><a href="medicines.php">Medicines</a><a href="sale.php">New Sale</a><a href="prescriptions.php">Prescriptions</a><a class="active" href="sales_history.php">Sales History</a><a href="reports.php">Reports</a></div></div>
    <form class="appointment-list-toolbar" method="get">
        <label class="appointment-search-box"><i class="bi bi-search"></i><input name="search" value="<?= e($search) ?>" placeholder="Search sale number, customer, payment..."></label>
        <select name="status">
            <?php foreach (['All', 'Paid', 'Pending', 'Cancelled', 'Returned'] as $option): ?>
                <option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= e($option) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit"><i class="bi bi-funnel"></i> Filter</button>
    </form>
    <div class="patient-list-grid patient-list-head" style="grid-template-columns: 1fr 1.25fr .75fr .8fr .9fr .8fr 1.2fr;">
        <span>Sale</span><span>Customer</span><span>Items</span><span>Total</span><span>Payment</span><span>Status</span><span>Actions</span>
    </div>
    <?php foreach ($sales as $sale): ?>
        <div class="patient-list-grid patient-list-row" style="grid-template-columns: 1fr 1.25fr .75fr .8fr .9fr .8fr 1.2fr;">
            <span><strong><?= e($sale['sale_number']) ?></strong><small class="d-block text-muted"><?= e(date('M j, Y h:i A', strtotime($sale['created_at']))) ?></small></span>
            <span><?= e($sale['customer_name'] ?: ($sale['patient_name'] ?: 'Walk-in Customer')) ?></span>
            <span><?= number_format((int) $sale['medicine_count']) ?></span>
            <span>$<?= number_format((float) $sale['total_amount'], 2) ?></span>
            <span><?= e($sale['payment_method']) ?></span>
            <span><em class="status-pill <?= $sale['status'] === 'Returned' ? 'inactive' : 'active' ?>"><?= e($sale['status']) ?></em></span>
            <span class="patient-actions">
                <a href="receipt.php?id=<?= (int) $sale['id'] ?>" title="Receipt"><i class="bi bi-receipt"></i></a>
                <?php if ($sale['status'] === 'Paid'): ?>
                    <a href="return_sale.php?id=<?= (int) $sale['id'] ?>" title="Return sale"><i class="bi bi-arrow-counterclockwise"></i></a>
                <?php endif; ?>
            </span>
        </div>
    <?php endforeach; ?>
    <?php if (!$sales): ?><div class="empty-state">No sales found.</div><?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
