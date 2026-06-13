<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect(current_role() === 'Pharmacy User' ? '/pharmacy/medicines.php' : '/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT id, username, password, full_name, role, status FROM users WHERE username = ? AND role = 'Pharmacy User' LIMIT 1");
    $stmt->bind_param('s', $username);
    $user = fetch_one($stmt);

    if ($user && $user['status'] === 'Active' && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $user['id'];
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['admin_name'] = $user['full_name'];
        $_SESSION['admin_role'] = $user['role'];
        redirect('/pharmacy/medicines.php');
    }

    $error = 'Invalid pharmacy username or password.';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pharmacy Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/css/style.css" rel="stylesheet">
</head>
<body class="min-vh-100 d-flex align-items-center justify-content-center" style="background:#f4f7fb;">
    <div class="form-panel" style="width:min(440px, 92vw);">
        <div class="d-flex align-items-center gap-3 mb-3">
            <span class="metric-icon blue"><i class="bi bi-capsule"></i></span>
            <div>
                <h1 class="h3 mb-1">Pharmacy Login</h1>
                <p class="text-muted mb-0">Hair Clinic Pro Pharmacy POS</p>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="mb-3">
                <label class="form-label">Pharmacy Username</label>
                <input class="form-control" name="username" required autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input class="form-control" type="password" name="password" required>
            </div>
            <button class="btn btn-primary w-100" type="submit"><i class="bi bi-box-arrow-in-right"></i> Login to Pharmacy</button>
            <p class="text-muted small mt-3 mb-0">Default pharmacy account: pharmacy / admin123</p>
            <p class="text-muted small mt-2 mb-0"><a href="<?= BASE_URL ?>/login.php">Back to main login</a></p>
        </form>
    </div>
</body>
</html>
