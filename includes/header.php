<?php
require_once __DIR__ . '/auth.php';
$page_title = $page_title ?? 'Hair Clinic System';
$current_path = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$module_paths = [
    '/users/'               => 'users',
    '/doctors/'             => 'doctors',
    '/patients/'            => 'patients',
    '/appointments/'        => 'appointments',
    '/doctor_appointments/' => 'doctor_appointments',
    '/payments/'            => 'payments',
    '/finance/'             => 'finance',
    '/audit_logs/'          => 'audit_logs',
    '/treatments/'          => 'treatments',
    '/followups/'           => 'followups',
    '/prescriptions/'       => 'prescriptions',
    '/inventory/'           => 'inventory',
    '/pharmacy/'            => 'pharmacy',
    '/reports/'             => 'reports',
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
        $last  = strtoupper(substr($parts[count($parts) - 1] ?? '', 0, 1));
        return $first . ($last !== $first ? $last : '');
    }
}

// Load avatar for logged-in user
$_header_avatar = '';
if (!empty($_SESSION['admin_id'])) {
    $uid = (int) $_SESSION['admin_id'];
    $row = $conn->query("SELECT avatar_path FROM users WHERE id = $uid LIMIT 1")->fetch_assoc();
    $_header_avatar = $row['avatar_path'] ?? '';
}

$_role_class = 'role-' . strtolower(str_replace(' ', '_', current_role()));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($page_title) ?></title>
    <!-- Apply dark theme before paint to avoid flash -->
    <script>if(localStorage.getItem('hcp_theme')==='dark'){document.documentElement.setAttribute('data-theme','dark');}</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/css/style.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/css/dark-role.css" rel="stylesheet">
</head>
<body class="dashboard-shell <?= e($_role_class) ?>">
    <?php render_role_sidebar($current_path); ?>

    <section class="clinic-main">
        <header class="clinic-topbar patient-topbar">
            <form class="top-search patient-search" action="<?= BASE_URL ?>/patients/view.php" method="get">
                <i class="bi bi-search"></i>
                <input name="search" placeholder="Search patients, records...">
            </form>
            <div class="top-actions admin-profile">
                <button class="dark-toggle" id="darkToggle"  title="Toggle dark mode" type="button">
                    <i class="bi bi-moon-fill" id="darkIcon"></i>
                </button>
                <button type="button" aria-label="Notifications"><i class="bi bi-bell"></i></button>
                <span class="profile-divider"></span>
                <span class="admin-copy"><strong><?= e($_SESSION['admin_name'] ?? 'Admin User') ?></strong><small><?= e(current_role()) ?></small></span>
                <a class="admin-avatar-link" href="<?= BASE_URL ?>/users/profile.php" title="My Profile">
                    <?php if ($_header_avatar && file_exists(__DIR__ . '/../' . ltrim($_header_avatar, '/'))): ?>
                        <div class="admin-avatar has-photo">
                            <img src="<?= BASE_URL . '/' . e(ltrim($_header_avatar, '/')) ?>" alt="Avatar">
                        </div>
                    <?php else: ?>
                        <div class="admin-avatar"><?= e(layout_initials($_SESSION['admin_name'] ?? 'Admin User')) ?></div>
                    <?php endif; ?>
                </a>
            </div>
        </header>

        <main class="clinic-content legacy-content">
            <?php show_flash(); ?>
<script>
function toggleDark() {
    var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    if (isDark) {
        document.documentElement.removeAttribute('data-theme');
        localStorage.setItem('hcp_theme', 'light');
        document.getElementById('darkIcon').className = 'bi bi-moon-fill';
    } else {
        document.documentElement.setAttribute('data-theme', 'dark');
        localStorage.setItem('hcp_theme', 'dark');
        document.getElementById('darkIcon').className = 'bi bi-sun-fill';
    }
}
// Set correct icon on load without waiting for DOMContentLoaded
(function () {
    if (localStorage.getItem('hcp_theme') === 'dark') {
        var icon = document.getElementById('darkIcon');
        if (icon) icon.className = 'bi bi-sun-fill';
    }
})();
</script>


