<?php
require_once __DIR__ . '/auth.php';
$page_title = $page_title ?? 'Hair Clinic System';
$current_path = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$module_paths = [
    '/users/' => 'users',
    '/doctors/' => 'doctors',
    '/patients/' => 'patients',
    '/appointments/' => 'appointments',
    '/doctor_appointments/' => 'doctor_appointments',
    '/payments/' => 'payments',
    '/finance/' => 'finance',
    '/audit_logs/' => 'audit_logs',
    '/treatments/' => 'treatments',
    '/followups/' => 'followups',
    '/prescriptions/' => 'prescriptions',
    '/inventory/' => 'inventory',
    '/pharmacy/' => 'pharmacy',
    '/reports/' => 'reports',
];

$current_module = 'dashboard';
foreach ($module_paths as $needle => $module) {
    if (strpos($current_path, $needle) !== false) {
        $current_module = $module;
        break;
    }
}

require_access($current_module);

if (!function_exists('layout_initials')) {
    function layout_initials($name)
    {
        $parts = preg_split('/\s+/', trim($name));
        $first = strtoupper(substr($parts[0] ?? '', 0, 1));
        $last = strtoupper(substr($parts[count($parts) - 1] ?? '', 0, 1));
        return $first . ($last !== $first ? $last : '');
    }
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($page_title) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/css/style.css" rel="stylesheet">
</head>
<body class="dashboard-shell">
    <?php render_role_sidebar($current_path); ?>

    <section class="clinic-main">
        <header class="clinic-topbar patient-topbar">
            <form class="top-search patient-search" action="<?= BASE_URL ?>/patients/view.php" method="get">
                <i class="bi bi-search"></i>
                <input name="search" placeholder="Search patients, records...">
            </form>
            <div class="top-actions admin-profile">
                <button type="button" aria-label="Notifications"><i class="bi bi-bell"></i></button>
                <button type="button" aria-label="Help"><i class="bi bi-question-circle"></i></button>
                <span class="profile-divider"></span>
                <span class="admin-copy"><strong><?= e($_SESSION['admin_name'] ?? 'Admin User') ?></strong><small><?= e(current_role()) ?></small></span>
                <div class="admin-avatar"><?= e(layout_initials($_SESSION['admin_name'] ?? 'Admin User')) ?></div>
            </div>
        </header>

        <main class="clinic-content legacy-content">
            <?php show_flash(); ?>
