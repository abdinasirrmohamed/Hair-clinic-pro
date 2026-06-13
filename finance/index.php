<?php
require_once __DIR__ . '/../includes/auth.php';
require_access('finance');

$categories = ['Staff Salaries', 'Medical Supplies', 'Medicine Purchases', 'Rent', 'Electricity', 'Water', 'Internet', 'Equipment Maintenance', 'Other Expenses'];
$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : date('Y-m-01');
$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : date('Y-m-t');
$category = in_array($_GET['category'] ?? '', $categories, true) ? $_GET['category'] : '';
$export = $_GET['export'] ?? '';

function money_value($conn, $sql, $types = '', $params = [])
{
    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        bind_params($stmt, $types, $params);
    }
    $row = fetch_one($stmt);
    return (float) ($row['total'] ?? 0);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'add';

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $conn->prepare('DELETE FROM expenses WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        log_activity('Deleted expense', 'Finance', $id);
        flash('success', 'Expense deleted.');
        redirect('/finance/index.php');
    }

    $id = (int) ($_POST['id'] ?? 0);
    $expense_category = in_array($_POST['category'] ?? '', $categories, true) ? $_POST['category'] : 'Other Expenses';
    $expense_date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['expense_date'] ?? '') ? $_POST['expense_date'] : date('Y-m-d');
    $amount = max(0, (float) ($_POST['amount'] ?? 0));
    $vendor = trim($_POST['vendor'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $receipt_path = $_POST['current_receipt'] ?? null;

    if (!empty($_FILES['receipt']['name']) && is_uploaded_file($_FILES['receipt']['tmp_name'])) {
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $mime = mime_content_type($_FILES['receipt']['tmp_name']);
        if (!isset($allowed[$mime])) {
            flash('danger', 'Receipt must be a JPG, PNG, or WEBP image.');
            redirect('/finance/index.php');
        }
        $upload_dir = __DIR__ . '/../uploads/expense_receipts';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0775, true);
        }
        $filename = 'receipt-' . date('YmdHis') . '-' . random_int(1000, 9999) . '.' . $allowed[$mime];
        move_uploaded_file($_FILES['receipt']['tmp_name'], $upload_dir . '/' . $filename);
        $receipt_path = 'uploads/expense_receipts/' . $filename;
    }

    if ($amount <= 0) {
        flash('danger', 'Expense amount must be greater than zero.');
        redirect('/finance/index.php');
    }

    if ($action === 'edit' && $id > 0) {
        $stmt = $conn->prepare('UPDATE expenses SET category = ?, expense_date = ?, amount = ?, vendor = ?, description = ?, receipt_path = ? WHERE id = ?');
        $stmt->bind_param('ssdsssi', $expense_category, $expense_date, $amount, $vendor, $description, $receipt_path, $id);
        $stmt->execute();
        log_activity('Updated expense', 'Finance', $id);
        flash('success', 'Expense updated.');
    } else {
        $created_by = (int) ($_SESSION['admin_id'] ?? 0);
        $stmt = $conn->prepare('INSERT INTO expenses (category, expense_date, amount, vendor, description, receipt_path, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('ssdsssi', $expense_category, $expense_date, $amount, $vendor, $description, $receipt_path, $created_by);
        $stmt->execute();
        log_activity('Added expense', 'Finance', $conn->insert_id);
        flash('success', 'Expense saved.');
    }

    redirect('/finance/index.php?from=' . urlencode($from) . '&to=' . urlencode($to));
}

$params = [$from, $to];
$types = 'ss';
$expense_where = 'WHERE expense_date BETWEEN ? AND ?';
if ($category !== '') {
    $expense_where .= ' AND category = ?';
    $types .= 's';
    $params[] = $category;
}

$stmt = $conn->prepare("SELECT e.*, u.full_name created_by_name FROM expenses e LEFT JOIN users u ON u.id = e.created_by $expense_where ORDER BY e.expense_date DESC, e.id DESC");
bind_params($stmt, $types, $params);
$expenses = fetch_all($stmt);

$total_expenses = money_value($conn, "SELECT COALESCE(SUM(amount), 0) total FROM expenses $expense_where", $types, $params);
$patient_revenue = money_value($conn, "SELECT COALESCE(SUM(amount), 0) total FROM payments WHERE payment_status IN ('Paid','Partial') AND DATE(created_at) BETWEEN ? AND ?", 'ss', [$from, $to]);
$pharmacy_revenue = money_value($conn, "SELECT COALESCE(SUM(total_amount), 0) total FROM pharmacy_sales WHERE status <> 'Returned' AND DATE(created_at) BETWEEN ? AND ?", 'ss', [$from, $to]);
$treatment_revenue = money_value($conn, 'SELECT COALESCE(SUM(cost), 0) total FROM treatments WHERE treatment_date BETWEEN ? AND ?', 'ss', [$from, $to]);
$total_revenue = $patient_revenue + $pharmacy_revenue + $treatment_revenue;
$net_profit = $total_revenue - $total_expenses;
$revenue_today = money_value($conn, "SELECT COALESCE(SUM(amount), 0) total FROM payments WHERE payment_status IN ('Paid','Partial') AND DATE(created_at) = CURDATE()")
    + money_value($conn, "SELECT COALESCE(SUM(total_amount), 0) total FROM pharmacy_sales WHERE status <> 'Returned' AND DATE(created_at) = CURDATE()");
$revenue_month = money_value($conn, "SELECT COALESCE(SUM(amount), 0) total FROM payments WHERE payment_status IN ('Paid','Partial') AND YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())")
    + money_value($conn, "SELECT COALESCE(SUM(total_amount), 0) total FROM pharmacy_sales WHERE status <> 'Returned' AND YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())")
    + money_value($conn, 'SELECT COALESCE(SUM(cost), 0) total FROM treatments WHERE YEAR(treatment_date) = YEAR(CURDATE()) AND MONTH(treatment_date) = MONTH(CURDATE())');
$expenses_month = money_value($conn, 'SELECT COALESCE(SUM(amount), 0) total FROM expenses WHERE YEAR(expense_date) = YEAR(CURDATE()) AND MONTH(expense_date) = MONTH(CURDATE())');

$trend_rows = [];
for ($i = 5; $i >= 0; $i--) {
    $month_start = date('Y-m-01', strtotime("-$i months"));
    $month_end = date('Y-m-t', strtotime($month_start));
    $label = date('M', strtotime($month_start));
    $rev = money_value($conn, "SELECT COALESCE(SUM(amount), 0) total FROM payments WHERE payment_status IN ('Paid','Partial') AND DATE(created_at) BETWEEN ? AND ?", 'ss', [$month_start, $month_end])
        + money_value($conn, "SELECT COALESCE(SUM(total_amount), 0) total FROM pharmacy_sales WHERE status <> 'Returned' AND DATE(created_at) BETWEEN ? AND ?", 'ss', [$month_start, $month_end])
        + money_value($conn, 'SELECT COALESCE(SUM(cost), 0) total FROM treatments WHERE treatment_date BETWEEN ? AND ?', 'ss', [$month_start, $month_end]);
    $exp = money_value($conn, 'SELECT COALESCE(SUM(amount), 0) total FROM expenses WHERE expense_date BETWEEN ? AND ?', 'ss', [$month_start, $month_end]);
    $trend_rows[] = ['label' => $label, 'revenue' => $rev, 'expenses' => $exp, 'profit' => $rev - $exp];
}

if ($export === 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="financial-summary-' . $from . '-to-' . $to . '.xls"');
    echo "Financial Summary\t$from to $to\n";
    echo "Total Revenue\t$total_revenue\nTotal Expenses\t$total_expenses\nNet Profit\t$net_profit\n\n";
    echo "Date\tCategory\tAmount\tVendor\tDescription\tCreated By\n";
    foreach ($expenses as $expense) {
        echo $expense['expense_date'] . "\t" . $expense['category'] . "\t" . $expense['amount'] . "\t" . $expense['vendor'] . "\t" . str_replace(["\r", "\n", "\t"], ' ', $expense['description']) . "\t" . $expense['created_by_name'] . "\n";
    }
    exit;
}

$page_title = 'Finance Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="patient-head">
    <div>
        <h1>Expense & Revenue Management</h1>
        <p>Track clinic revenue, expenses, and profitability across patient care and pharmacy operations.</p>
    </div>
    <div class="d-flex gap-2">
        <a class="add-patient-btn" href="?from=<?= e($from) ?>&to=<?= e($to) ?>&export=excel"><i class="bi bi-file-earmark-spreadsheet"></i>Export Excel</a>
        <button class="add-patient-btn" type="button" onclick="window.print()"><i class="bi bi-filetype-pdf"></i>Export PDF</button>
    </div>
</div>

<form class="form-panel mb-4" method="get">
    <div class="row g-3 align-items-end">
        <div class="col-md-3"><label class="form-label">From</label><input class="form-control" type="date" name="from" value="<?= e($from) ?>"></div>
        <div class="col-md-3"><label class="form-label">To</label><input class="form-control" type="date" name="to" value="<?= e($to) ?>"></div>
        <div class="col-md-4"><label class="form-label">Expense Category</label><select class="form-select" name="category"><option value="">All Categories</option><?php foreach ($categories as $cat): ?><option value="<?= e($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>><?= e($cat) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-funnel"></i>Filter</button></div>
    </div>
</form>

<div class="patient-metrics mb-4">
    <article class="patient-metric"><div><p>Total Revenue Today</p><strong>$<?= number_format($revenue_today, 2) ?></strong></div><span class="metric-icon mint"><i class="bi bi-cash-stack"></i></span></article>
    <article class="patient-metric"><div><p>Total Revenue This Month</p><strong>$<?= number_format($revenue_month, 2) ?></strong></div><span class="metric-icon blue"><i class="bi bi-graph-up-arrow"></i></span></article>
    <article class="patient-metric"><div><p>Total Expenses This Month</p><strong>$<?= number_format($expenses_month, 2) ?></strong></div><span class="metric-icon red"><i class="bi bi-receipt-cutoff"></i></span></article>
    <article class="patient-metric"><div><p>Net Profit</p><strong>$<?= number_format($net_profit, 2) ?></strong></div><span class="metric-icon <?= $net_profit >= 0 ? 'mint' : 'red' ?>"><i class="bi bi-pie-chart"></i></span></article>
    <article class="patient-metric"><div><p>Pharmacy Revenue</p><strong>$<?= number_format($pharmacy_revenue, 2) ?></strong></div><span class="metric-icon blue"><i class="bi bi-capsule"></i></span></article>
    <article class="patient-metric"><div><p>Treatment Revenue</p><strong>$<?= number_format($treatment_revenue, 2) ?></strong></div><span class="metric-icon mint"><i class="bi bi-scissors"></i></span></article>
</div>

<div class="row g-4">
    <div class="col-xl-5">
        <section class="form-panel h-100">
            <h2 class="h5 mb-3">Add Expense</h2>
            <form method="post" enctype="multipart/form-data" class="row g-3">
                <input type="hidden" name="action" value="add">
                <div class="col-md-6"><label class="form-label">Category</label><select class="form-select" name="category"><?php foreach ($categories as $cat): ?><option><?= e($cat) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-6"><label class="form-label">Date</label><input class="form-control" type="date" name="expense_date" value="<?= e(date('Y-m-d')) ?>"></div>
                <div class="col-md-6"><label class="form-label">Amount</label><input class="form-control" type="number" min="0" step="0.01" name="amount" required></div>
                <div class="col-md-6"><label class="form-label">Vendor</label><input class="form-control" name="vendor"></div>
                <div class="col-12"><label class="form-label">Receipt Image</label><input class="form-control" type="file" name="receipt" accept="image/png,image/jpeg,image/webp"></div>
                <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="3"></textarea></div>
                <div class="col-12"><button class="btn btn-primary"><i class="bi bi-plus-lg"></i>Add Expense</button></div>
            </form>
        </section>
    </div>
    <div class="col-xl-7">
        <section class="form-panel h-100">
            <h2 class="h5 mb-3">Revenue, Expense, Profit Trend</h2>
            <canvas id="financeChart" height="150"></canvas>
            <div class="row g-3 mt-3">
                <div class="col-md-4"><div class="border rounded p-3"><span class="text-muted">Selected Revenue</span><strong class="d-block">$<?= number_format($total_revenue, 2) ?></strong></div></div>
                <div class="col-md-4"><div class="border rounded p-3"><span class="text-muted">Selected Expenses</span><strong class="d-block">$<?= number_format($total_expenses, 2) ?></strong></div></div>
                <div class="col-md-4"><div class="border rounded p-3"><span class="text-muted">Profit & Loss</span><strong class="d-block">$<?= number_format($net_profit, 2) ?></strong></div></div>
            </div>
        </section>
    </div>
</div>

<section class="patient-management-card mt-4">
    <div class="patient-tabs"><div class="tab-links"><a class="active" href="#">Expense History</a><a href="<?= BASE_URL ?>/reports/index.php?type=monthly">Reports</a></div></div>
    <div class="table-responsive p-4">
        <table class="table align-middle">
            <thead><tr><th>Date</th><th>Category</th><th>Amount</th><th>Vendor</th><th>Receipt</th><th>Action</th></tr></thead>
            <tbody>
                <?php foreach ($expenses as $expense): ?>
                    <tr>
                        <td><?= e(date('M j, Y', strtotime($expense['expense_date']))) ?></td>
                        <td><strong><?= e($expense['category']) ?></strong><small class="d-block text-muted"><?= e($expense['description']) ?></small></td>
                        <td>$<?= number_format((float) $expense['amount'], 2) ?></td>
                        <td><?= e($expense['vendor'] ?: 'N/A') ?></td>
                        <td><?php if ($expense['receipt_path']): ?><a href="<?= BASE_URL ?>/<?= e($expense['receipt_path']) ?>" target="_blank">View</a><?php else: ?><span class="text-muted">None</span><?php endif; ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#editExpense<?= (int) $expense['id'] ?>">Edit</button>
                            <form method="post" class="d-inline" onsubmit="return confirm('Delete this expense?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $expense['id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form>
                        </td>
                    </tr>
                    <tr class="collapse" id="editExpense<?= (int) $expense['id'] ?>">
                        <td colspan="6">
                            <form method="post" enctype="multipart/form-data" class="row g-2">
                                <input type="hidden" name="action" value="edit"><input type="hidden" name="id" value="<?= (int) $expense['id'] ?>"><input type="hidden" name="current_receipt" value="<?= e($expense['receipt_path']) ?>">
                                <div class="col-md-3"><select class="form-select" name="category"><?php foreach ($categories as $cat): ?><option <?= $expense['category'] === $cat ? 'selected' : '' ?>><?= e($cat) ?></option><?php endforeach; ?></select></div>
                                <div class="col-md-2"><input class="form-control" type="date" name="expense_date" value="<?= e($expense['expense_date']) ?>"></div>
                                <div class="col-md-2"><input class="form-control" type="number" step="0.01" name="amount" value="<?= e($expense['amount']) ?>"></div>
                                <div class="col-md-2"><input class="form-control" name="vendor" value="<?= e($expense['vendor']) ?>"></div>
                                <div class="col-md-3"><input class="form-control" type="file" name="receipt" accept="image/png,image/jpeg,image/webp"></div>
                                <div class="col-md-10"><input class="form-control" name="description" value="<?= e($expense['description']) ?>"></div>
                                <div class="col-md-2"><button class="btn btn-primary w-100">Save</button></div>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$expenses): ?><tr><td colspan="6"><div class="empty-state">No expenses found for this period.</div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const trend = <?= json_encode($trend_rows) ?>;
new Chart(document.getElementById('financeChart'), {
    type: 'line',
    data: {
        labels: trend.map(row => row.label),
        datasets: [
            { label: 'Revenue', data: trend.map(row => row.revenue), borderColor: '#0d6efd', backgroundColor: 'rgba(13,110,253,.08)', tension: .35 },
            { label: 'Expenses', data: trend.map(row => row.expenses), borderColor: '#dc3545', backgroundColor: 'rgba(220,53,69,.08)', tension: .35 },
            { label: 'Profit', data: trend.map(row => row.profit), borderColor: '#198754', backgroundColor: 'rgba(25,135,84,.08)', tension: .35 }
        ]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true } } }
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
