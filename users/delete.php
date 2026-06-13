<?php
require_once __DIR__ . '/../includes/auth.php';
require_access('users');

$id = (int) ($_GET['id'] ?? 0);
if ($id === (int) ($_SESSION['admin_id'] ?? 0)) {
    flash('danger', 'You cannot delete your own account.');
    redirect('/users/view.php');
}

$stmt = $conn->prepare('DELETE FROM users WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
log_activity('Deleted user account', 'Users', $id);

flash('success', 'User deleted successfully.');
redirect('/users/view.php');
