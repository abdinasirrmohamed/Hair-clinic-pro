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
    <script>if(localStorage.getItem('hcp_theme')==='dark'){document.documentElement.setAttribute('data-theme','dark');}</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="<?= BASE_URL ?>/css/dark-role.css" rel="stylesheet">
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
(function () {
    if (localStorage.getItem('hcp_theme') === 'dark') {
        var icon = document.getElementById('darkIcon');
        if (icon) icon.className = 'bi bi-sun-fill';
    }
})();
</script><script>
document.addEventListener("DOMContentLoaded", function() {
    if (typeof IntersectionObserver !== 'undefined') {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry, index) => {
                if (entry.isIntersecting) {
                    setTimeout(() => {
                        entry.target.classList.add('animate-reveal');
                    }, index * 80);
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.05, rootMargin: '0px 0px -20px 0px' });
        
        const elementsToAnimate = document.querySelectorAll('.stat-card, .table-wrap, .form-panel, .metric-card, .recent-panel, .appointments-panel, .dashboard-appointment-hub, .hub-appointment-card, .appointment-card, .patient-row');
        elementsToAnimate.forEach(el => observer.observe(el));
    }
});
</script>
<script id="bulletproof-dark-toggle">
document.addEventListener('DOMContentLoaded', function() {
    var btn = document.getElementById('darkToggle');
    if (btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            var icon = document.getElementById('darkIcon');
            if (isDark) {
                document.documentElement.removeAttribute('data-theme');
                localStorage.setItem('hcp_theme', 'light');
                if (icon) icon.className = 'bi bi-moon-fill';
            } else {
                document.documentElement.setAttribute('data-theme', 'dark');
                localStorage.setItem('hcp_theme', 'dark');
                if (icon) icon.className = 'bi bi-sun-fill';
            }
        });
    }
});
</script>
</body>
</html>



