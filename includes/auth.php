<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

function is_logged_in()
{
    return !empty($_SESSION['admin_id']);
}

function current_role()
{
    return $_SESSION['admin_role'] ?? 'Administrator';
}

function role_key($role = null)
{
    $role = $role ?? current_role();
    return strtolower(str_replace(' ', '_', $role));
}

function role_permissions()
{
    return [
        'Administrator' => ['dashboard', 'users', 'doctors', 'patients', 'appointments', 'doctor_appointments', 'payments', 'finance', 'audit_logs', 'treatments', 'followups', 'inventory', 'pharmacy', 'prescriptions', 'reports'],
        'Receptionist' => ['dashboard', 'patients', 'appointments', 'payments', 'reports'],
        'Doctor' => ['dashboard', 'patients', 'doctor_appointments', 'treatments', 'followups', 'prescriptions', 'reports'],
        'Inventory Officer' => ['dashboard', 'inventory'],
        'Pharmacy User' => ['dashboard', 'pharmacy', 'reports'],
    ];
}

function report_permissions()
{
    return [
        'Administrator' => ['users', 'patients', 'appointments', 'treatments', 'followups', 'consultations', 'medical_history', 'inventory', 'stock_in', 'stock_out', 'low_stock', 'expired', 'payments', 'finance', 'expenses', 'profit_loss', 'audit_logs', 'pharmacy', 'pharmacy_sales', 'prescriptions', 'top_medicines', 'inventory_movement', 'doctor_performance', 'activity'],
        'Receptionist' => ['patients', 'appointments', 'payments'],
        'Doctor' => ['treatments', 'followups', 'consultations', 'medical_history'],
        'Inventory Officer' => ['inventory', 'stock_in', 'stock_out', 'low_stock', 'expired', 'inventory_movement'],
        'Pharmacy User' => ['pharmacy', 'pharmacy_sales', 'prescriptions', 'top_medicines', 'low_stock', 'expired', 'inventory_movement'],
    ];
}

function can_access($module)
{
    $permissions = role_permissions();
    return in_array($module, $permissions[current_role()] ?? [], true);
}

function can_view_report($report)
{
    $permissions = report_permissions();
    return in_array($report, $permissions[current_role()] ?? [], true);
}

function doctor_patient_filter($patient_alias = 'p')
{
    if (current_role() !== 'Doctor') {
        return '';
    }

    return ' AND ' . $patient_alias . '.assigned_doctor_id = ' . (int) ($_SESSION['admin_id'] ?? 0);
}

function require_patient_assignment($patient_id)
{
    global $conn;

    if (current_role() !== 'Doctor') {
        return;
    }

    $stmt = $conn->prepare('SELECT COUNT(*) total FROM patients WHERE id = ? AND assigned_doctor_id = ?');
    $admin_id = (int) ($_SESSION['admin_id'] ?? 0);
    $stmt->bind_param('ii', $patient_id, $admin_id);
    $row = fetch_one($stmt);

    if ((int) ($row['total'] ?? 0) === 0) {
        http_response_code(403);
        require __DIR__ . '/../access-denied.php';
        exit;
    }
}

function require_access($module)
{
    require_login();
    if (!can_access($module)) {
        http_response_code(403);
        require __DIR__ . '/../access-denied.php';
        exit;
    }
}

function require_roles(array $roles)
{
    require_login();
    if (!in_array(current_role(), $roles, true)) {
        http_response_code(403);
        require __DIR__ . '/../access-denied.php';
        exit;
    }
}

function role_menu_items()
{
    $items = [
        ['module' => 'dashboard', 'href' => '/dashboard.php', 'icon' => 'bi-grid', 'label' => 'Dashboard'],
        ['module' => 'users', 'href' => '/users/view.php', 'icon' => 'bi-people', 'label' => 'Users'],
        ['module' => 'doctors', 'href' => '/doctors/view.php', 'icon' => 'bi-person-badge', 'label' => 'Doctors'],
        ['module' => 'patients', 'href' => '/patients/view.php', 'icon' => 'bi-person', 'label' => 'Patients'],
        ['module' => 'appointments', 'href' => '/appointments/view.php', 'icon' => 'bi-calendar3', 'label' => 'Appointments'],
        ['module' => 'doctor_appointments', 'href' => '/doctor_appointments/view.php', 'icon' => 'bi-calendar-check', 'label' => 'My Appointments'],
        ['module' => 'payments', 'href' => '/payments/view.php', 'icon' => 'bi-credit-card', 'label' => 'Payments'],
        ['module' => 'finance', 'href' => '/finance/index.php', 'icon' => 'bi-cash-coin', 'label' => 'Finance'],
        ['module' => 'treatments', 'href' => '/treatments/view.php', 'icon' => 'bi-scissors', 'label' => 'Treatments'],
        ['module' => 'followups', 'href' => '/followups/view.php', 'icon' => 'bi-clipboard2-check', 'label' => 'Follow-Ups'],
        ['module' => 'prescriptions', 'href' => '/prescriptions/view.php', 'icon' => 'bi-prescription2', 'label' => 'Prescriptions'],
        ['module' => 'inventory', 'href' => '/inventory/medicines.php', 'icon' => 'bi-archive', 'label' => 'Inventory'],
        ['module' => 'pharmacy', 'href' => '/pharmacy/medicines.php', 'icon' => 'bi-capsule', 'label' => 'Pharmacy'],
        ['module' => 'reports', 'href' => '/reports/index.php', 'icon' => 'bi-graph-up', 'label' => 'Reports'],
        ['module' => 'audit_logs', 'href' => '/audit_logs/index.php', 'icon' => 'bi-shield-lock', 'label' => 'Audit Logs'],
    ];

    return array_values(array_filter($items, fn($item) => can_access($item['module'])));
}

function render_role_sidebar($current_path = '')
{
    echo '<aside class="clinic-sidebar"><div><a class="brand-mark" href="' . BASE_URL . '/dashboard.php"><span>Hair Clinic Pro</span><small>' . e(current_role()) . '</small></a><nav class="side-nav">';
    foreach (role_menu_items() as $item) {
        $active = strpos($current_path, dirname($item['href'])) !== false || ($item['module'] === 'dashboard' && strpos($current_path, 'dashboard.php') !== false) ? 'active' : '';
        echo '<a class="' . e($active) . '" href="' . BASE_URL . e($item['href']) . '"><i class="bi ' . e($item['icon']) . '"></i><span>' . e($item['label']) . '</span></a>';
    }
    echo '</nav></div><div class="side-bottom">';
    echo '<a href="' . BASE_URL . '/logout.php"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a></div></aside>';
}

function require_login()
{
    if (!is_logged_in()) {
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        if (strpos($script, '/pharmacy/') !== false) {
            redirect('/pharmacy-login.php');
        }
        redirect('/login.php');
    }
}

function redirect_if_logged_in()
{
    if (is_logged_in()) {
        if (current_role() === 'Inventory Officer') {
            redirect('/inventory/medicines.php');
        }
        if (current_role() === 'Pharmacy User') {
            redirect('/pharmacy/medicines.php');
        }
        redirect('/dashboard.php');
    }
}
