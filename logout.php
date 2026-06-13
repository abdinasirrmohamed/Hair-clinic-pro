<?php
require_once __DIR__ . '/includes/auth.php';
if (is_logged_in()) {
    log_activity('Logout', 'Authentication', $_SESSION['admin_id'] ?? null);
}
session_unset();
session_destroy();
redirect('/login.php');
