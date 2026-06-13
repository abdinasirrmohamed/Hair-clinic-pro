<?php
require_once __DIR__ . '/includes/auth.php';
redirect_if_logged_in();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare('SELECT id, username, password, full_name, role FROM admins WHERE username = ? LIMIT 1');
    $stmt->bind_param('s', $username);
    $admin = fetch_one($stmt);

    if ($admin && password_verify($password, $admin['password'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_name'] = $admin['full_name'];
        $_SESSION['admin_role'] = $admin['role'];
        redirect('/dashboard.php');
    }

    $error = 'Invalid username or password.';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/css/style.css" rel="stylesheet">
</head>
<body class="min-vh-100 d-flex align-items-center justify-content-center">
    <div class="form-panel" style="width: min(420px, 92vw);">
        <h1 class="h3 mb-1">Admin Login</h1>
        <p class="text-muted mb-4">Hair Clinic Management System</p>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="post">
            <div class="mb-3">
                <label class="form-label">Username</label>
                <input class="form-control" name="username" required autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input class="form-control" type="password" name="password" required>
            </div>
            <button class="btn btn-primary w-100" type="submit">Login</button>
            <p class="text-muted small mt-3 mb-0">Default: admin / admin123</p>
            <p class="text-muted small mb-0">Sample roles: receptionist, doctor, inventory / admin123</p>
        </form>
    </div>
</body>
</html>
