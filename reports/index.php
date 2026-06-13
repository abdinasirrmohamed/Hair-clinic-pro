<?php
require_once __DIR__ . '/../includes/auth.php';
require_access('reports');

$type = $_GET['type'] ?? 'daily';
$today = date('Y-m-d');

if ($type === 'weekly') {
    $start = date('Y-m-d', strtotime('monday this week'));
    $end = date('Y-m-d', strtotime('sunday this week'));
    $title = 'Weekly Report';
} elseif ($type === 'monthly') {
    $start = date('Y-m-01');
    $end = date('Y-m-t');
    $title = 'Monthly Report';
} else {
    $start = $today;
    $end = $today;
    $title = 'Daily Report';
}

$patient_scope = doctor_patient_filter('p');
$stmt = $conn->prepare("SELECT COUNT(*) total FROM patients p WHERE DATE(p.created_at) BETWEEN ? AND ? $patient_scope");
$stmt->bind_param('ss', $start, $end);
$new_patients = (int) fetch_one($stmt)['total'];

$stmt = $conn->prepare("SELECT COUNT(*) total FROM appointments a JOIN patients p ON p.id = a.patient_id WHERE a.appointment_date BETWEEN ? AND ? $patient_scope");
$stmt->bind_param('ss', $start, $end);
$appointments_count = (int) fetch_one($stmt)['total'];

$stmt = $conn->prepare("SELECT COUNT(*) total FROM treatments t JOIN patients p ON p.id = t.patient_id WHERE t.treatment_date BETWEEN ? AND ? $patient_scope");
$stmt->bind_param('ss', $start, $end);
$treatments_count = (int) fetch_one($stmt)['total'];

$stmt = $conn->prepare("SELECT SUM(t.cost) total FROM treatments t JOIN patients p ON p.id = t.patient_id WHERE t.treatment_date BETWEEN ? AND ? $patient_scope");
$stmt->bind_param('ss', $start, $end);
$income = (float) (fetch_one($stmt)['total'] ?? 0);

$stmt = $conn->prepare(
    "SELECT a.*, p.full_name FROM appointments a JOIN patients p ON p.id = a.patient_id WHERE a.appointment_date BETWEEN ? AND ? $patient_scope ORDER BY a.appointment_date, a.appointment_time"
);
$stmt->bind_param('ss', $start, $end);
$appointments = fetch_all($stmt);

$stmt = $conn->prepare(
    "SELECT t.*, p.full_name FROM treatments t JOIN patients p ON p.id = t.patient_id WHERE t.treatment_date BETWEEN ? AND ? $patient_scope ORDER BY t.treatment_date"
);
$stmt->bind_param('ss', $start, $end);
$treatments = fetch_all($stmt);

$stmt = $conn->prepare(
    "SELECT f.*, p.full_name, t.treatment_name FROM followups f JOIN patients p ON p.id = f.patient_id LEFT JOIN treatments t ON t.id = f.treatment_id WHERE f.followup_date BETWEEN ? AND ? $patient_scope ORDER BY f.followup_date"
);
$stmt->bind_param('ss', $start, $end);
$followups = fetch_all($stmt);

$users_count = count_table($conn, 'SELECT COUNT(*) FROM users');
$inventory_count = count_table($conn, 'SELECT COUNT(*) FROM inventory_items');
$payment_count = count_table($conn, 'SELECT COUNT(*) FROM payments');
$payment_total = count_table($conn, 'SELECT COALESCE(SUM(amount), 0) FROM payments');
$pharmacy_sales_count = count_table($conn, 'SELECT COUNT(*) FROM pharmacy_sales');
$doctor_count = count_table($conn, 'SELECT COUNT(*) FROM doctors');
$low_stock_count = count_table($conn, 'SELECT COUNT(*) FROM inventory_items WHERE stock_level < 10');
$stock_in_count = count_table($conn, "SELECT COUNT(*) FROM inventory_orders WHERE order_status IN ('Pending', 'Shipped', 'Delivered')");
$stock_out_count = count_table($conn, 'SELECT COUNT(*) FROM inventory_items WHERE stock_level = 0');
$expired_count = 0;

$completed_appointments = 0;
foreach ($appointments as $appointment) {
    if ($appointment['status'] === 'Completed') {
        $completed_appointments++;
    }
}
$completion_rate = $appointments_count ? round(($completed_appointments / $appointments_count) * 100) : 0;

function report_initials($name)
{
    $parts = preg_split('/\s+/', trim($name));
    $first = strtoupper(substr($parts[0] ?? '', 0, 1));
    $last = strtoupper(substr($parts[count($parts) - 1] ?? '', 0, 1));
    return $first . ($last !== $first ? $last : '');
}

$visible_report_cards = [
    ['key' => 'users', 'label' => 'User Reports', 'value' => $users_count, 'icon' => 'bi-people'],
    ['key' => 'patients', 'label' => 'Patient Reports', 'value' => $new_patients, 'icon' => 'bi-person-plus'],
    ['key' => 'appointments', 'label' => 'Appointment Reports', 'value' => $appointments_count, 'icon' => 'bi-calendar3'],
    ['key' => 'treatments', 'label' => 'Treatment Reports', 'value' => $treatments_count, 'icon' => 'bi-scissors'],
    ['key' => 'followups', 'label' => 'Follow-Up Reports', 'value' => count($followups), 'icon' => 'bi-clipboard2-check'],
    ['key' => 'consultations', 'label' => 'Consultation Reports', 'value' => $appointments_count, 'icon' => 'bi-clipboard-pulse'],
    ['key' => 'medical_history', 'label' => 'Medical History Reports', 'value' => $total_medical_history ?? count_table($conn, 'SELECT COUNT(*) FROM patients WHERE medical_notes IS NOT NULL AND medical_notes <> ""'), 'icon' => 'bi-file-medical'],
    ['key' => 'inventory', 'label' => 'Inventory Reports', 'value' => $inventory_count, 'icon' => 'bi-archive'],
    ['key' => 'payments', 'label' => 'Payment Reports', 'value' => $payment_count, 'icon' => 'bi-credit-card'],
    ['key' => 'pharmacy', 'label' => 'Pharmacy Reports', 'value' => $pharmacy_sales_count, 'icon' => 'bi-capsule'],
    ['key' => 'doctor_performance', 'label' => 'Doctor Performance', 'value' => $doctor_count, 'icon' => 'bi-person-badge'],
    ['key' => 'stock_in', 'label' => 'Stock In Reports', 'value' => $stock_in_count, 'icon' => 'bi-box-arrow-in-down'],
    ['key' => 'stock_out', 'label' => 'Stock Out Reports', 'value' => $stock_out_count, 'icon' => 'bi-box-arrow-up'],
    ['key' => 'low_stock', 'label' => 'Low Stock Reports', 'value' => $low_stock_count, 'icon' => 'bi-exclamation-triangle'],
    ['key' => 'expired', 'label' => 'Expired Items Reports', 'value' => $expired_count, 'icon' => 'bi-calendar2-x'],
    ['key' => 'activity', 'label' => 'System Activity Reports', 'value' => $users_count + $appointments_count + $treatments_count + $inventory_count + $payment_count, 'icon' => 'bi-activity'],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/css/style.css" rel="stylesheet">
</head>
<body class="dashboard-shell">
    <?php render_role_sidebar($_SERVER['SCRIPT_NAME'] ?? ''); ?>

    <section class="clinic-main">
        <header class="clinic-topbar report-topbar">
            <form class="top-search report-search" action="<?= BASE_URL ?>/patients/view.php" method="get">
                <i class="bi bi-search"></i>
                <input name="search" placeholder="Search reports, patients, records...">
            </form>
            <div class="top-actions admin-profile">
                <button type="button" aria-label="Notifications"><i class="bi bi-bell"></i></button>
                <button type="button" aria-label="Help"><i class="bi bi-question-circle"></i></button>
                <span class="profile-divider"></span>
                <span class="admin-copy"><strong><?= e($_SESSION['admin_name'] ?? 'Admin User') ?></strong><small><?= e(current_role()) ?></small></span>
                <div class="admin-avatar"><?= e(report_initials($_SESSION['admin_name'] ?? 'Admin User')) ?></div>
            </div>
        </header>

        <main class="clinic-content report-content">
            <div class="report-head">
                <div>
                    <span>Clinic Analytics</span>
                    <h1><?= e($title) ?></h1>
                    <p><?= e(date('M j, Y', strtotime($start))) ?> to <?= e(date('M j, Y', strtotime($end))) ?></p>
                </div>
                <button type="button" onclick="window.print()"><i class="bi bi-printer"></i>Print Report</button>
            </div>

            <div class="report-tabs">
                <a class="<?= $type === 'daily' ? 'active' : '' ?>" href="daily.php">Daily</a>
                <a class="<?= $type === 'weekly' ? 'active' : '' ?>" href="weekly.php">Weekly</a>
                <a class="<?= $type === 'monthly' ? 'active' : '' ?>" href="monthly.php">Monthly</a>
            </div>

            <div class="patient-metrics report-metrics">
                <?php foreach ($visible_report_cards as $card): ?>
                    <?php if (can_view_report($card['key'])): ?>
                        <article class="patient-metric"><div><p><?= e($card['label']) ?></p><strong><?= number_format((int) $card['value']) ?></strong></div><span class="metric-icon blue"><i class="bi <?= e($card['icon']) ?>"></i></span></article>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <div class="report-grid">
                <?php if (can_view_report('appointments')): ?>
                <section class="report-table-card">
                    <div class="report-section-head">
                        <h2>Appointments</h2>
                        <span><?= number_format($appointments_count) ?> records</span>
                    </div>
                    <div class="report-table">
                        <div class="report-table-head"><span>Patient</span><span>Date / Time</span><span>Reason</span><span>Status</span></div>
                        <?php foreach ($appointments as $a): ?>
                            <div class="report-table-row">
                                <span><?= e($a['full_name']) ?></span>
                                <span><?= e(date('M j, Y', strtotime($a['appointment_date']))) ?><small><?= e(substr($a['appointment_time'], 0, 5)) ?></small></span>
                                <span><?= e($a['reason']) ?></span>
                                <em class="profile-status <?= $a['status'] === 'Completed' ? 'completed' : ($a['status'] === 'Cancelled' ? 'cancelled' : 'upcoming') ?>"><?= e($a['status']) ?></em>
                            </div>
                        <?php endforeach; ?>
                        <?php if (!$appointments): ?><div class="empty-state">No appointments in this period.</div><?php endif; ?>
                    </div>
                </section>
                <?php endif; ?>

                <?php if (can_view_report('appointments') || can_view_report('treatments')): ?>
                <aside class="report-summary-card">
                    <h2>Performance Summary</h2>
                    <div class="report-ring"><span><?= $completion_rate ?>%</span></div>
                    <p>Appointment completion rate for the selected report period.</p>
                    <div class="capacity-row">
                        <div><span>Revenue Target</span><strong>$<?= number_format($income, 0) ?></strong></div>
                        <div class="capacity-track"><span style="width: <?= min(100, (int) ($income / 50)) ?>%"></span></div>
                    </div>
                    <div class="capacity-row">
                        <div><span>Treatment Volume</span><strong><?= number_format($treatments_count) ?></strong></div>
                        <div class="capacity-track green"><span style="width: <?= min(100, $treatments_count * 20) ?>%"></span></div>
                    </div>
                </aside>
                <?php endif; ?>

                <?php if (can_view_report('treatments')): ?>
                <section class="report-table-card treatments-report-card">
                    <div class="report-section-head">
                        <h2>Treatments</h2>
                        <span>$<?= number_format($income, 2) ?> total</span>
                    </div>
                    <div class="report-table">
                        <div class="report-table-head treatment-report-head"><span>Patient</span><span>Treatment</span><span>Date</span><span>Progress</span><span>Cost</span></div>
                        <?php foreach ($treatments as $t): ?>
                            <div class="report-table-row treatment-report-row">
                                <span><?= e($t['full_name']) ?></span>
                                <span><?= e($t['treatment_name']) ?></span>
                                <span><?= e(date('M j, Y', strtotime($t['treatment_date']))) ?></span>
                                <em class="status-pill <?= $t['progress'] === 'Completed' ? 'inactive' : 'active' ?>"><?= e($t['progress']) ?></em>
                                <span>$<?= number_format((float) $t['cost'], 2) ?></span>
                            </div>
                        <?php endforeach; ?>
                        <?php if (!$treatments): ?><div class="empty-state">No treatments in this period.</div><?php endif; ?>
                    </div>
                </section>
                <?php endif; ?>

                <?php if (can_view_report('followups')): ?>
                <section class="report-table-card treatments-report-card">
                    <div class="report-section-head">
                        <h2>Follow-Ups</h2>
                        <span><?= number_format(count($followups)) ?> records</span>
                    </div>
                    <div class="report-table">
                        <div class="report-table-head treatment-report-head"><span>Patient</span><span>Treatment</span><span>Date</span><span>Status</span><span>Result</span></div>
                        <?php foreach ($followups as $f): ?>
                            <div class="report-table-row treatment-report-row">
                                <span><?= e($f['full_name']) ?></span>
                                <span><?= e($f['treatment_name'] ?: 'General Follow-Up') ?></span>
                                <span><?= e(date('M j, Y', strtotime($f['followup_date']))) ?></span>
                                <em class="status-pill <?= $f['status'] === 'Done' ? 'active' : 'inactive' ?>"><?= e($f['status']) ?></em>
                                <span><?= e($f['result'] ?: 'No result recorded') ?></span>
                            </div>
                        <?php endforeach; ?>
                        <?php if (!$followups): ?><div class="empty-state">No follow-ups in this period.</div><?php endif; ?>
                    </div>
                </section>
                <?php endif; ?>

                <?php if (can_view_report('inventory') || can_view_report('stock_in') || can_view_report('stock_out') || can_view_report('low_stock') || can_view_report('expired')): ?>
                <section class="report-table-card treatments-report-card">
                    <div class="report-section-head">
                        <h2>Inventory & Stock</h2>
                        <span><?= number_format($inventory_count) ?> items</span>
                    </div>
                    <div class="report-table">
                        <div class="report-table-head treatment-report-head"><span>Report</span><span>Count</span><span>Status</span><span>Role</span><span>Period</span></div>
                        <?php
                        $inventory_rows = [
                            ['key' => 'inventory', 'label' => 'Inventory Items', 'value' => $inventory_count, 'status' => 'Active'],
                            ['key' => 'stock_in', 'label' => 'Stock In Orders', 'value' => $stock_in_count, 'status' => 'Tracked'],
                            ['key' => 'stock_out', 'label' => 'Stock Out Items', 'value' => $stock_out_count, 'status' => 'Tracked'],
                            ['key' => 'low_stock', 'label' => 'Low Stock Items', 'value' => $low_stock_count, 'status' => 'Alert'],
                            ['key' => 'expired', 'label' => 'Expired Items', 'value' => $expired_count, 'status' => 'Clear'],
                        ];
                        ?>
                        <?php foreach ($inventory_rows as $row): ?>
                            <?php if (can_view_report($row['key'])): ?>
                                <div class="report-table-row treatment-report-row">
                                    <span><?= e($row['label']) ?></span>
                                    <span><?= number_format($row['value']) ?></span>
                                    <em class="status-pill <?= $row['status'] === 'Alert' ? 'inactive' : 'active' ?>"><?= e($row['status']) ?></em>
                                    <span><?= e(current_role()) ?></span>
                                    <span><?= e($title) ?></span>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>
            </div>
        </main>
    </section>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
