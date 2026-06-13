<?php
$current_path = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Access Denied</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/css/style.css" rel="stylesheet">
</head>
<body class="dashboard-shell">
    <?php render_role_sidebar($current_path); ?>
    <section class="clinic-main">
        <main class="clinic-content">
            <section class="form-panel text-center" style="max-width: 640px; margin: 80px auto;">
                <span class="metric-icon pale-red mx-auto mb-3"><i class="bi bi-shield-lock"></i></span>
                <h1>Access Denied</h1>
                <p class="text-muted mb-4">Your <?= e(current_role()) ?> account does not have permission to open this module.</p>
                <a class="btn btn-primary" href="<?= BASE_URL ?>/dashboard.php">Back to Dashboard</a>
            </section>
        </main>
    </section>
</body>
</html>
