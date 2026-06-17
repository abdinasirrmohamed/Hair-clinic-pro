<?php
require_once __DIR__ . '/includes/auth.php';
require_access('dashboard');

// ── Real-time metrics ────────────────────────────────────────────────
$total_users        = count_table($conn, 'SELECT COUNT(*) FROM users');
$total_doctors      = count_table($conn, 'SELECT COUNT(*) FROM doctors');
$total_patients     = count_table($conn, 'SELECT COUNT(*) FROM patients');
$total_appointments = count_table($conn, 'SELECT COUNT(*) FROM appointments');
$total_treatments   = count_table($conn, 'SELECT COUNT(*) FROM treatments');
$total_inventory_items = count_table($conn, 'SELECT COUNT(*) FROM inventory_items');
$total_medicines    = count_table($conn, 'SELECT COUNT(*) FROM medicines');
$total_payments     = (float) $conn->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE payment_status IN ('Paid','Partial')")->fetch_row()[0];

$revenue_today_total = (float) $conn->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE payment_status IN ('Paid','Partial') AND DATE(created_at)=CURDATE()")->fetch_row()[0]
    + (float) $conn->query("SELECT COALESCE(SUM(total_amount),0) FROM pharmacy_sales WHERE status<>'Returned' AND DATE(created_at)=CURDATE()")->fetch_row()[0];

$revenue_month_total = (float) $conn->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE payment_status IN ('Paid','Partial') AND YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())")->fetch_row()[0]
    + (float) $conn->query("SELECT COALESCE(SUM(total_amount),0) FROM pharmacy_sales WHERE status<>'Returned' AND YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())")->fetch_row()[0]
    + (float) $conn->query("SELECT COALESCE(SUM(cost),0) FROM treatments WHERE YEAR(treatment_date)=YEAR(CURDATE()) AND MONTH(treatment_date)=MONTH(CURDATE())")->fetch_row()[0];

$expenses_month_total  = (float) $conn->query("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE YEAR(expense_date)=YEAR(CURDATE()) AND MONTH(expense_date)=MONTH(CURDATE())")->fetch_row()[0];
$net_profit_month_total = $revenue_month_total - $expenses_month_total;

$activity_today_total  = count_table($conn, 'SELECT COUNT(*) FROM audit_logs WHERE DATE(created_at)=CURDATE()');
$pending_appointments  = count_table($conn, "SELECT COUNT(*) FROM appointments WHERE status='Pending'");
$today_appointments    = count_table($conn, 'SELECT COUNT(*) FROM appointments WHERE appointment_date=CURDATE()');
$upcoming_appointments = count_table($conn, "SELECT COUNT(*) FROM appointments WHERE appointment_date>=CURDATE() AND status<>'Cancelled'");
$recently_registered   = count_table($conn, "SELECT COUNT(*) FROM patients WHERE created_at>=DATE_SUB(NOW(), INTERVAL 7 DAY)");

// Real patient growth vs last month
$patients_this_month = count_table($conn, "SELECT COUNT(*) FROM patients WHERE YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE())");
$patients_last_month = count_table($conn, "SELECT COUNT(*) FROM patients WHERE YEAR(created_at)=YEAR(DATE_SUB(CURDATE(),INTERVAL 1 MONTH)) AND MONTH(created_at)=MONTH(DATE_SUB(CURDATE(),INTERVAL 1 MONTH))");
$patient_growth_pct  = $patients_last_month > 0 ? round((($patients_this_month - $patients_last_month) / $patients_last_month) * 100) : ($patients_this_month > 0 ? 100 : 0);
$patient_chip        = ($patient_growth_pct >= 0 ? '+' : '') . $patient_growth_pct . '%';
$patient_chip_tone   = $patient_growth_pct >= 0 ? 'green' : 'red';

// Real appointment status chip
$appt_pending_pct = $total_appointments > 0 ? count_table($conn, "SELECT COUNT(*) FROM appointments WHERE status='Pending'") : 0;
$appt_chip        = $appt_pending_pct > 0 ? "$appt_pending_pct Pending" : 'All Clear';
$appt_chip_tone   = $appt_pending_pct > 0 ? 'red' : 'green';

$doctor_scope               = doctor_patient_filter('p');
$assigned_patients          = count_table($conn, "SELECT COUNT(*) FROM patients p WHERE 1=1 $doctor_scope");
$doctor_today_consultations = count_table($conn, "SELECT COUNT(*) FROM appointments a JOIN patients p ON p.id=a.patient_id WHERE a.appointment_date=CURDATE() $doctor_scope");
$doctor_pending_approvals   = count_table($conn, "SELECT COUNT(*) FROM appointments a JOIN patients p ON p.id=a.patient_id WHERE a.status='Pending' $doctor_scope");
$doctor_approved_appts      = count_table($conn, "SELECT COUNT(*) FROM appointments a JOIN patients p ON p.id=a.patient_id WHERE a.status='Approved' $doctor_scope");
$doctor_completed_appts     = count_table($conn, "SELECT COUNT(*) FROM appointments a JOIN patients p ON p.id=a.patient_id WHERE a.status='Completed' $doctor_scope");
$doctor_completed_treatments= count_table($conn, "SELECT COUNT(*) FROM treatments t JOIN patients p ON p.id=t.patient_id WHERE t.progress='Completed' $doctor_scope");
$pending_followups          = count_table($conn, "SELECT COUNT(*) FROM followups f JOIN patients p ON p.id=f.patient_id WHERE f.status='Scheduled' $doctor_scope");

$inventory_available_stock  = count_table($conn, 'SELECT COALESCE(SUM(quantity),0) FROM medicines');
$inventory_low_stock_items  = count_table($conn, 'SELECT COUNT(*) FROM medicines WHERE quantity<=reorder_level');
$expired_items              = count_table($conn, 'SELECT COUNT(*) FROM medicines WHERE expiry_date<CURDATE()');
$inventory_stock_in_today   = count_table($conn, "SELECT COALESCE(SUM(quantity),0) FROM inventory_movements WHERE movement_type='Stock In' AND DATE(movement_date)=CURDATE()");
$inventory_stock_out_today  = count_table($conn, "SELECT COALESCE(SUM(quantity),0) FROM inventory_movements WHERE movement_type IN ('Stock Out','Pharmacy Sales','Treatment Consumption') AND DATE(movement_date)=CURDATE()");
$inventory_value_total      = (float) $conn->query('SELECT COALESCE(SUM(quantity*unit_price),0) FROM medicines')->fetch_row()[0];

$pharmacy_sales_today       = count_table($conn, 'SELECT COUNT(*) FROM pharmacy_sales WHERE DATE(created_at)=CURDATE()');
$pharmacy_revenue_today     = (float) $conn->query('SELECT COALESCE(SUM(total_amount),0) FROM pharmacy_sales WHERE DATE(created_at)=CURDATE()')->fetch_row()[0];
$pending_prescriptions_count= count_table($conn, "SELECT COUNT(*) FROM prescriptions WHERE status='Pending'");
$dispensed_prescriptions_count = count_table($conn, "SELECT COUNT(*) FROM prescriptions WHERE status IN ('Dispensed','Completed')");
$low_stock_medicines        = count_table($conn, 'SELECT COUNT(*) FROM medicines WHERE quantity<=reorder_level');
$expired_medicines          = count_table($conn, 'SELECT COUNT(*) FROM medicines WHERE expiry_date<CURDATE()');

$system_activity = $total_patients + $total_appointments + $total_treatments + $total_inventory_items;

// Real low-stock item for inventory alert
$critical_stock = $conn->query(
    'SELECT medicine_name, quantity, reorder_level FROM medicines WHERE quantity<=reorder_level ORDER BY quantity ASC LIMIT 1'
)->fetch_assoc();

// ── Role-based metric definitions ────────────────────────────────────
$role = current_role();
$dashboard_profiles = [
    'Administrator' => [
        'title'    => 'Administrator Dashboard',
        'subtitle' => 'Full system overview, activity, and management controls.',
        'metrics'  => [
            ['label' => 'Total Users',            'value' => $total_users,            'icon' => 'bi-people',          'tone' => 'blue', 'chip' => 'All Roles',    'chipTone' => 'neutral'],
            ['label' => 'Total Doctors',           'value' => $total_doctors,          'icon' => 'bi-person-badge',    'tone' => 'mint', 'chip' => 'Clinical Team', 'chipTone' => 'neutral'],
            ['label' => 'Total Patients',          'value' => $total_patients,         'icon' => 'bi-person-vcard',    'tone' => 'blue', 'chip' => $patient_chip,   'chipTone' => $patient_chip_tone],
            ['label' => 'Total Appointments',      'value' => $total_appointments,     'icon' => 'bi-calendar3',       'tone' => 'blue', 'chip' => $appt_chip,      'chipTone' => $appt_chip_tone],
            ['label' => 'Total Treatments',        'value' => $total_treatments,       'icon' => 'bi-scissors',        'tone' => 'mint', 'chip' => 'Clinical',      'chipTone' => 'neutral'],
            ['label' => 'Inventory Items',         'value' => $total_inventory_items,  'icon' => 'bi-archive',         'tone' => 'blue', 'chip' => 'Stock',         'chipTone' => 'neutral'],
            ['label' => 'Revenue Today',           'value' => $revenue_today_total,    'icon' => 'bi-cash-stack',      'tone' => 'mint', 'chip' => 'Finance',       'chipTone' => 'green'],
            ['label' => 'Monthly Expenses',        'value' => $expenses_month_total,   'icon' => 'bi-receipt-cutoff',  'tone' => 'red',  'chip' => 'Costs',         'chipTone' => 'red'],
            ['label' => 'Net Profit This Month',   'value' => $net_profit_month_total, 'icon' => 'bi-pie-chart',       'tone' => $net_profit_month_total >= 0 ? 'mint' : 'red', 'chip' => 'P&L', 'chipTone' => $net_profit_month_total >= 0 ? 'green' : 'red'],
            ['label' => 'Activities Today',        'value' => $activity_today_total,   'icon' => 'bi-shield-check',    'tone' => 'blue', 'chip' => 'Audit',         'chipTone' => 'neutral'],
            ['label' => 'System Activity Overview','value' => $system_activity,        'icon' => 'bi-activity',        'tone' => 'mint', 'chip' => 'All Modules',   'chipTone' => 'green'],
        ],
    ],
    'Receptionist' => [
        'title'    => 'Receptionist Dashboard',
        'subtitle' => 'Patient intake and appointment scheduling summary.',
        'metrics'  => [
            ['label' => 'Total Patients Registered',    'value' => $total_patients,         'icon' => 'bi-person-plus',    'tone' => 'blue', 'chip' => 'Registry',   'chipTone' => 'green'],
            ['label' => "Today's Appointments",         'value' => $today_appointments,     'icon' => 'bi-calendar-check', 'tone' => 'mint', 'chip' => 'Today',      'chipTone' => 'neutral'],
            ['label' => 'Upcoming Appointments',        'value' => $upcoming_appointments,  'icon' => 'bi-calendar2-week', 'tone' => 'blue', 'chip' => 'Scheduled',  'chipTone' => 'green'],
            ['label' => 'Payments Collected',           'value' => $total_payments,         'icon' => 'bi-credit-card',    'tone' => 'mint', 'chip' => 'Payments',   'chipTone' => 'green'],
            ['label' => 'Recently Registered Patients', 'value' => $recently_registered,    'icon' => 'bi-clock-history',  'tone' => 'blue', 'chip' => '7 Days',     'chipTone' => 'neutral'],
        ],
    ],
    'Doctor' => [
        'title'    => 'Doctor Dashboard',
        'subtitle' => 'Clinical workload, treatment progress, and follow-up queue.',
        'metrics'  => [
            ['label' => 'Assigned Patients',     'value' => $assigned_patients,           'icon' => 'bi-person-lines-fill', 'tone' => 'blue', 'chip' => 'Assigned', 'chipTone' => 'green'],
            ['label' => "Today's Consultations", 'value' => $doctor_today_consultations,  'icon' => 'bi-clipboard-pulse',   'tone' => 'mint', 'chip' => 'Today',    'chipTone' => 'neutral'],
            ['label' => 'Pending Approvals',     'value' => $doctor_pending_approvals,    'icon' => 'bi-hourglass-split',   'tone' => 'red',  'chip' => 'Review',   'chipTone' => 'red'],
            ['label' => 'Approved Appointments', 'value' => $doctor_approved_appts,       'icon' => 'bi-calendar-check',    'tone' => 'blue', 'chip' => 'Approved', 'chipTone' => 'green'],
            ['label' => 'Completed Appointments','value' => $doctor_completed_appts,      'icon' => 'bi-check2-circle',     'tone' => 'mint', 'chip' => 'Done',     'chipTone' => 'green'],
            ['label' => 'Completed Treatments',  'value' => $doctor_completed_treatments, 'icon' => 'bi-check-circle',      'tone' => 'mint', 'chip' => 'Done',     'chipTone' => 'green'],
            ['label' => 'Pending Follow-Ups',    'value' => $pending_followups,           'icon' => 'bi-clipboard2-check',  'tone' => 'red',  'chip' => 'Pending',  'chipTone' => 'red'],
        ],
    ],
    'Inventory Officer' => [
        'title'    => 'Inventory Dashboard',
        'subtitle' => 'Stock levels, low stock alerts, and order monitoring.',
        'metrics'  => [
            ['label' => 'Total Inventory Items', 'value' => $total_inventory_items,   'icon' => 'bi-archive',              'tone' => 'blue', 'chip' => 'Items',   'chipTone' => 'neutral'],
            ['label' => 'Available Stock',       'value' => $inventory_available_stock,'icon' => 'bi-box-seam',            'tone' => 'mint', 'chip' => 'Units',   'chipTone' => 'green'],
            ['label' => 'Low Stock Items',       'value' => $inventory_low_stock_items,'icon' => 'bi-exclamation-triangle', 'tone' => 'red',  'chip' => $inventory_low_stock_items > 0 ? 'Alert' : 'OK', 'chipTone' => $inventory_low_stock_items > 0 ? 'red' : 'green'],
            ['label' => 'Expired Items',         'value' => $expired_items,           'icon' => 'bi-calendar2-x',          'tone' => 'red',  'chip' => $expired_items > 0 ? 'Expired' : 'Clear', 'chipTone' => $expired_items > 0 ? 'red' : 'green'],
            ['label' => 'Stock In Today',        'value' => $inventory_stock_in_today, 'icon' => 'bi-box-arrow-in-down',   'tone' => 'mint', 'chip' => 'Today',   'chipTone' => 'green'],
            ['label' => 'Stock Out Today',       'value' => $inventory_stock_out_today,'icon' => 'bi-box-arrow-up',        'tone' => 'blue', 'chip' => 'Today',   'chipTone' => 'neutral'],
            ['label' => 'Inventory Value',       'value' => $inventory_value_total,   'icon' => 'bi-cash-coin',            'tone' => 'mint', 'chip' => 'USD',     'chipTone' => 'green'],
            ['label' => 'Medicines',             'value' => $total_medicines,          'icon' => 'bi-capsule',              'tone' => 'mint', 'chip' => 'Pharmacy','chipTone' => 'green'],
        ],
    ],
    'Pharmacy User' => [
        'title'    => 'Pharmacy Dashboard',
        'subtitle' => 'Medicine sales, prescription queue, stock alerts, and revenue overview.',
        'metrics'  => [
            ['label' => 'Total Medicines',        'value' => $total_medicines,               'icon' => 'bi-capsule',           'tone' => 'blue', 'chip' => 'Stock',   'chipTone' => 'neutral'],
            ['label' => 'Total Sales Today',      'value' => $pharmacy_sales_today,          'icon' => 'bi-receipt',           'tone' => 'mint', 'chip' => 'POS',     'chipTone' => 'green'],
            ['label' => "Today's Revenue",        'value' => $pharmacy_revenue_today,        'icon' => 'bi-cash-stack',        'tone' => 'blue', 'chip' => 'Paid',    'chipTone' => 'green'],
            ['label' => 'Pending Prescriptions',  'value' => $pending_prescriptions_count,   'icon' => 'bi-prescription2',     'tone' => 'red',  'chip' => 'Queue',   'chipTone' => 'red'],
            ['label' => 'Dispensed Prescriptions','value' => $dispensed_prescriptions_count, 'icon' => 'bi-check2-circle',     'tone' => 'mint', 'chip' => 'Done',    'chipTone' => 'green'],
            ['label' => 'Low Stock Medicines',    'value' => $low_stock_medicines,           'icon' => 'bi-exclamation-triangle','tone' => 'red', 'chip' => $low_stock_medicines > 0 ? 'Alert' : 'OK', 'chipTone' => $low_stock_medicines > 0 ? 'red' : 'green'],
            ['label' => 'Expired Medicines',      'value' => $expired_medicines,             'icon' => 'bi-calendar-x',        'tone' => 'red',  'chip' => $expired_medicines > 0 ? 'Expired' : 'Clear', 'chipTone' => $expired_medicines > 0 ? 'red' : 'green'],
        ],
    ],
];

$profile = $dashboard_profiles[$role] ?? $dashboard_profiles['Administrator'];

// ── Appointment feed ─────────────────────────────────────────────────
$appointment_search = trim($_GET['appointment_search'] ?? '');
$appointment_status = $_GET['appointment_status'] ?? 'All';
$appointment_range  = $_GET['appointment_range']  ?? 'upcoming';
$appointment_where  = "WHERE 1=1 $doctor_scope";
$appointment_types  = '';
$appointment_params = [];

if ($appointment_search !== '') {
    $like = '%' . $appointment_search . '%';
    $appointment_where .= ' AND (p.full_name LIKE ? OR a.reason LIKE ? OR a.status LIKE ? OR a.notes LIKE ?)';
    $appointment_types .= 'ssss';
    array_push($appointment_params, $like, $like, $like, $like);
}
if (in_array($appointment_status, ['Pending', 'Approved', 'Completed', 'Cancelled'], true)) {
    $appointment_where .= ' AND a.status = ?';
    $appointment_types .= 's';
    $appointment_params[] = $appointment_status;
}
if ($appointment_range === 'today')    $appointment_where .= ' AND a.appointment_date=CURDATE()';
elseif ($appointment_range === 'upcoming') $appointment_where .= ' AND a.appointment_date>=CURDATE()';
elseif ($appointment_range === 'past') $appointment_where .= ' AND a.appointment_date<CURDATE()';

$stmt = $conn->prepare(
    "SELECT a.*, p.full_name, p.phone FROM appointments a JOIN patients p ON p.id=a.patient_id $appointment_where ORDER BY a.appointment_date ASC, a.appointment_time ASC LIMIT 12"
);
if ($appointment_types !== '') {
    bind_params($stmt, $appointment_types, $appointment_params);
}
$dashboard_appointments = fetch_all($stmt);
$hub_pending = $hub_completed = $hub_cancelled = 0;
foreach ($dashboard_appointments as $ha) {
    if ($ha['status'] === 'Pending')   $hub_pending++;
    elseif ($ha['status'] === 'Completed') $hub_completed++;
    elseif ($ha['status'] === 'Cancelled') $hub_cancelled++;
}

$recent_patients     = $conn->query("SELECT * FROM patients p WHERE 1=1 $doctor_scope ORDER BY created_at DESC LIMIT 4")->fetch_all(MYSQLI_ASSOC);
$recent_appointments = $conn->query("SELECT a.*, p.full_name FROM appointments a JOIN patients p ON p.id=a.patient_id WHERE 1=1 $doctor_scope ORDER BY a.appointment_date DESC, a.appointment_time DESC LIMIT 4")->fetch_all(MYSQLI_ASSOC);

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $f = strtoupper(substr($parts[0] ?? '', 0, 1));
    $l = strtoupper(substr($parts[count($parts) - 1] ?? '', 0, 1));
    return $f . ($l !== $f ? $l : '');
}

function appointment_tone(string $s): string
{
    return match($s) { 'Completed' => 'green', 'Cancelled' => 'red', default => 'blue' };
}

function appointment_status_class(string $s): string
{
    return match($s) { 'Completed' => 'active', 'Cancelled' => 'inactive', default => 'upcoming' };
}

// ── Use shared header (provides dark mode, role colors, profile) ─────
$page_title = 'Clinic Overview';
require_once __DIR__ . '/includes/header.php';
?>

<div class="overview-head">
    <div>
        <h1><?= e($profile['title']) ?></h1>
        <p>Welcome back, <?= e($_SESSION['admin_name'] ?? 'Admin') ?>. <?= e($profile['subtitle']) ?></p>
    </div>
    <div class="date-pill"><i class="bi bi-calendar3"></i><?= strtoupper(date('F j, Y')) ?></div>
</div>

<div class="metric-grid">
    <?php foreach ($profile['metrics'] as $metric): ?>
        <?php
        $is_money = preg_match('/Revenue|Payments|Expenses|Profit|Value/i', $metric['label']);
        $display  = $is_money ? '$' . number_format((float) $metric['value'], 2) : number_format((int) $metric['value']);
        ?>
        <article class="metric-card" data-metric-label="<?= e($metric['label']) ?>">
            <div class="metric-top">
                <span class="metric-icon <?= e($metric['tone']) ?>"><i class="bi <?= e($metric['icon']) ?>"></i></span>
                <span class="metric-chip <?= e($metric['chipTone']) ?>"><?= e($metric['chip']) ?></span>
            </div>
            <p><?= e($metric['label']) ?></p>
            <strong data-metric-value><?= e($display) ?></strong>
        </article>
    <?php endforeach; ?>
</div>

<?php if (can_access('appointments') || current_role() === 'Doctor'): ?>
<section class="dashboard-appointment-hub">
    <div class="hub-head">
        <div>
            <span>Live Appointment Feed</span>
            <h2>All Appointments</h2>
            <p>Real-time appointment list — updates every 30 seconds.</p>
        </div>
        <?php if (can_access('appointments')): ?>
            <a href="<?= BASE_URL ?>/appointments/add.php"><i class="bi bi-plus-lg"></i>New Appointment</a>
        <?php endif; ?>
    </div>

    <form class="appointment-hub-tools" method="get">
        <label class="hub-search">
            <i class="bi bi-search"></i>
            <input name="appointment_search" value="<?= e($appointment_search) ?>" placeholder="Search patient, reason, status...">
        </label>
        <select name="appointment_status">
            <?php foreach (['All', 'Pending', 'Approved', 'Completed', 'Cancelled'] as $so): ?>
                <option value="<?= e($so) ?>" <?= $appointment_status === $so ? 'selected' : '' ?>><?= e($so) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="appointment_range">
            <?php foreach (['upcoming' => 'Upcoming', 'today' => 'Today', 'past' => 'Past', 'all' => 'All Dates'] as $rv => $rl): ?>
                <option value="<?= e($rv) ?>" <?= $appointment_range === $rv ? 'selected' : '' ?>><?= e($rl) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit"><i class="bi bi-funnel"></i>Filter</button>
    </form>

    <div class="hub-status-strip">
        <span><b><?= count($dashboard_appointments) ?></b> showing</span>
        <span class="pending"><b><?= $hub_pending ?></b> pending</span>
        <span class="completed"><b><?= $hub_completed ?></b> completed</span>
        <span class="cancelled"><b><?= $hub_cancelled ?></b> cancelled</span>
    </div>

    <div class="appointment-hub-list">
        <?php foreach ($dashboard_appointments as $appt): ?>
            <article class="hub-appointment-card <?= e(strtolower($appt['status'])) ?>">
                <time>
                    <strong><?= e(date('h:i', strtotime($appt['appointment_time']))) ?></strong>
                    <span><?= e(date('A', strtotime($appt['appointment_time']))) ?></span>
                </time>
                <div class="hub-appointment-main">
                    <div>
                        <h3><?= e($appt['full_name']) ?></h3>
                        <p><?= e($appt['reason']) ?></p>
                    </div>
                    <em class="status-pill <?= e(appointment_status_class($appt['status'])) ?>"><?= e($appt['status']) ?></em>
                </div>
                <div class="hub-appointment-meta">
                    <span><i class="bi bi-calendar3"></i><?= e(date('M j, Y', strtotime($appt['appointment_date']))) ?></span>
                    <span><i class="bi bi-telephone"></i><?= e($appt['phone']) ?></span>
                </div>
                <?php if (can_access('appointments')): ?>
                    <a class="hub-card-action" href="<?= BASE_URL ?>/appointments/edit.php?id=<?= $appt['id'] ?>">Manage</a>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
        <?php if (!$dashboard_appointments): ?>
            <div class="empty-state">No appointments match the current filters.</div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<?php if (current_role() === 'Pharmacy User'): ?>
<section class="patient-management-card">
    <div class="patient-tabs"><div class="tab-links">
        <a class="active" href="<?= BASE_URL ?>/pharmacy/medicines.php">Medicines</a>
        <a href="<?= BASE_URL ?>/pharmacy/sale.php">New Sale</a>
        <a href="<?= BASE_URL ?>/pharmacy/prescriptions.php">Prescriptions</a>
        <a href="<?= BASE_URL ?>/pharmacy/sales_history.php">Sales History</a>
        <a href="<?= BASE_URL ?>/pharmacy/reports.php">Reports</a>
    </div></div>
    <div class="p-4">
        <div class="row g-3">
            <div class="col-md-4"><a class="btn btn-primary w-100 py-3" href="<?= BASE_URL ?>/pharmacy/sale.php"><i class="bi bi-cart-plus"></i> Create Direct Sale</a></div>
            <div class="col-md-4"><a class="btn btn-outline-primary w-100 py-3" href="<?= BASE_URL ?>/pharmacy/prescriptions.php"><i class="bi bi-prescription2"></i> Process Prescriptions</a></div>
            <div class="col-md-4"><a class="btn btn-outline-secondary w-100 py-3" href="<?= BASE_URL ?>/pharmacy/reports.php"><i class="bi bi-graph-up"></i> Pharmacy Reports</a></div>
        </div>
    </div>
</section>

<?php elseif (current_role() === 'Inventory Officer'): ?>
<section class="patient-management-card">
    <div class="patient-tabs"><div class="tab-links">
        <a class="active" href="<?= BASE_URL ?>/inventory/medicines.php">Items</a>
        <a href="<?= BASE_URL ?>/inventory/purchase.php">Purchase</a>
        <a href="<?= BASE_URL ?>/inventory/stock_in.php">Stock In</a>
        <a href="<?= BASE_URL ?>/inventory/stock_out.php">Stock Out</a>
        <a href="<?= BASE_URL ?>/inventory/suppliers.php">Suppliers</a>
        <a href="<?= BASE_URL ?>/inventory/reports.php">Reports</a>
    </div></div>
    <div class="p-4">
        <div class="row g-3">
            <div class="col-md-3"><a class="btn btn-primary w-100 py-3" href="<?= BASE_URL ?>/inventory/medicine_form.php"><i class="bi bi-plus-lg"></i> Add Medicine</a></div>
            <div class="col-md-3"><a class="btn btn-outline-primary w-100 py-3" href="<?= BASE_URL ?>/inventory/purchase.php"><i class="bi bi-box-arrow-in-down"></i> Create Purchase</a></div>
            <div class="col-md-3"><a class="btn btn-outline-primary w-100 py-3" href="<?= BASE_URL ?>/inventory/stock_out.php"><i class="bi bi-box-arrow-up"></i> Stock Out</a></div>
            <div class="col-md-3"><a class="btn btn-outline-secondary w-100 py-3" href="<?= BASE_URL ?>/inventory/reports.php"><i class="bi bi-file-earmark-bar-graph"></i> Reports</a></div>
        </div>
    </div>
</section>

<div class="row g-4 mt-1">
    <div class="col-lg-6">
        <section class="form-panel h-100">
            <h2 class="h5 mb-3">Low Stock Warnings</h2>
            <?php $dashboard_low_stock = $conn->query('SELECT medicine_name, quantity, reorder_level FROM medicines WHERE quantity<=reorder_level ORDER BY quantity ASC LIMIT 8')->fetch_all(MYSQLI_ASSOC); ?>
            <?php foreach ($dashboard_low_stock as $item): ?>
                <div class="d-flex justify-content-between border-bottom py-2">
                    <span><strong><?= e($item['medicine_name']) ?></strong><small class="d-block text-muted">Reorder level: <?= (int) $item['reorder_level'] ?></small></span>
                    <em class="status-pill inactive"><?= (int) $item['quantity'] ?> left</em>
                </div>
            <?php endforeach; ?>
            <?php if (!$dashboard_low_stock): ?><div class="empty-state">All stock levels are healthy.</div><?php endif; ?>
        </section>
    </div>
    <div class="col-lg-6">
        <section class="form-panel h-100">
            <h2 class="h5 mb-3">Expiry Alerts (next 30 days)</h2>
            <?php $dashboard_expiry = $conn->query('SELECT medicine_name, batch_number, expiry_date FROM medicines WHERE expiry_date<=DATE_ADD(CURDATE(),INTERVAL 30 DAY) ORDER BY expiry_date ASC LIMIT 8')->fetch_all(MYSQLI_ASSOC); ?>
            <?php foreach ($dashboard_expiry as $item): ?>
                <div class="d-flex justify-content-between border-bottom py-2">
                    <span><strong><?= e($item['medicine_name']) ?></strong><small class="d-block text-muted"><?= e($item['batch_number'] ?: 'No batch') ?></small></span>
                    <em class="status-pill inactive"><?= e(date('M j, Y', strtotime($item['expiry_date']))) ?></em>
                </div>
            <?php endforeach; ?>
            <?php if (!$dashboard_expiry): ?><div class="empty-state">No expiry alerts in the next 30 days.</div><?php endif; ?>
        </section>
    </div>
</div>

<?php else: ?>
<div class="dashboard-grid">
    <section class="recent-panel">
        <div class="panel-title">
            <h2>Recent Patients</h2>
            <?php if (can_access('patients')): ?><a href="<?= BASE_URL ?>/patients/view.php">View All</a><?php endif; ?>
        </div>
        <div class="patient-table">
            <div class="table-head"><span>Patient Name</span><span>Date Registered</span><span>Status</span><span>Actions</span></div>
            <?php foreach ($recent_patients as $patient): ?>
                <div class="patient-row">
                    <div class="patient-name"><span class="initials"><?= e(initials($patient['full_name'])) ?></span><strong><?= e($patient['full_name']) ?></strong></div>
                    <span><?= e(date('M j, Y', strtotime($patient['created_at']))) ?></span>
                    <span class="status-pill active">Active</span>
                    <a class="row-action" href="<?= BASE_URL ?>/patients/edit.php?id=<?= $patient['id'] ?>"><i class="bi bi-three-dots-vertical"></i></a>
                </div>
            <?php endforeach; ?>
            <?php if (!$recent_patients): ?><div class="empty-state">No recent patients yet.</div><?php endif; ?>
        </div>
    </section>

    <aside class="appointments-panel">
        <div class="panel-title"><h2>Recent Appointments</h2><i class="bi bi-sliders"></i></div>
        <div class="appointment-list">
            <?php foreach ($recent_appointments as $appt): ?>
                <a class="appointment-card <?= appointment_tone($appt['status']) ?>" href="<?= BASE_URL ?>/appointments/edit.php?id=<?= $appt['id'] ?>">
                    <span class="time">
                        <strong><?= e(date('h', strtotime($appt['appointment_time']))) ?></strong>
                        <small><?= e(date('A', strtotime($appt['appointment_time']))) ?></small>
                    </span>
                    <span class="appointment-info">
                        <strong><?= e($appt['full_name']) ?></strong>
                        <small><?= e($appt['reason']) ?></small>
                        <em><?= e(strtoupper($appt['status'])) ?></em>
                    </span>
                </a>
            <?php endforeach; ?>
            <?php if (!$recent_appointments): ?><div class="empty-state">No appointments yet.</div><?php endif; ?>
        </div>
        <?php if (can_access('appointments')): ?><a class="calendar-button" href="<?= BASE_URL ?>/appointments/view.php">Calendar View</a><?php endif; ?>
    </aside>

    <?php if (can_access('inventory') && $critical_stock): ?>
    <section class="inventory-alert">
        <h2><i class="bi bi-exclamation-triangle-fill me-2"></i>Low Stock Alert</h2>
        <p>
            <strong><?= e($critical_stock['medicine_name']) ?></strong> is critically low —
            only <strong><?= (int) $critical_stock['quantity'] ?></strong> units remaining
            (reorder level: <?= (int) $critical_stock['reorder_level'] ?>).
            <?php if ($inventory_low_stock_items > 1): ?>
                Plus <?= $inventory_low_stock_items - 1 ?> other item<?= $inventory_low_stock_items > 2 ? 's' : '' ?> need restocking.
            <?php endif; ?>
        </p>
        <a href="<?= BASE_URL ?>/inventory/purchase.php">Order Now</a>
    </section>
    <?php elseif (can_access('inventory')): ?>
    <section class="inventory-alert" style="background:#005c55;">
        <h2><i class="bi bi-check-circle-fill me-2"></i>Stock Levels OK</h2>
        <p>All medicines are above their reorder levels. No immediate restocking needed.</p>
        <a href="<?= BASE_URL ?>/inventory/medicines.php">View Inventory</a>
    </section>
    <?php endif; ?>
</div>
<?php endif; ?>

<script>
async function refreshDashboardMetrics() {
    try {
        const res = await fetch('<?= BASE_URL ?>/api/dashboard_metrics.php', { headers: { 'Accept': 'application/json' } });
        if (!res.ok) return;
        const data = await res.json();
        document.querySelectorAll('[data-metric-label]').forEach(card => {
            const label = card.dataset.metricLabel;
            if (data.metrics && Object.prototype.hasOwnProperty.call(data.metrics, label)) {
                card.querySelector('[data-metric-value]').textContent = data.metrics[label];
            }
        });
    } catch (e) { /* silent */ }
}
setInterval(refreshDashboardMetrics, 30000);
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
