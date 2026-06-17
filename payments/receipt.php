<?php
require_once __DIR__ . '/../includes/auth.php';
require_access('payments');

$id = (int) ($_GET['id'] ?? 0);
$stmt = $conn->prepare(
    'SELECT pay.*, p.full_name, p.phone, p.email, a.reason, a.appointment_date, a.appointment_time, r.receipt_number, r.clinic_name, r.clinic_phone, r.clinic_address, r.issued_at
     FROM payments pay
     JOIN patients p ON p.id = pay.patient_id
     LEFT JOIN appointments a ON a.id = pay.appointment_id
     LEFT JOIN receipts r ON r.payment_id = pay.id
     WHERE pay.id = ?'
);
$stmt->bind_param('i', $id);
$receipt = fetch_one($stmt);
if (!$receipt) {
    flash('danger', 'Receipt not found.');
    redirect('/payments/view.php');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($receipt['receipt_number']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/css/style.css" rel="stylesheet">
    <script>if(localStorage.getItem('hcp_theme')==='dark'){document.documentElement.setAttribute('data-theme','dark');}</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="<?= BASE_URL ?>/css/dark-role.css" rel="stylesheet">
</head>
<body class="bg-light">
    <main class="container py-5">
        <section class="form-panel mx-auto" style="max-width: 760px;">
            <div class="d-flex justify-content-between align-items-start border-bottom pb-3 mb-4">
                <div>
                    <h1 class="h3 mb-1"><?= e($receipt['clinic_name']) ?></h1>
                    <p class="mb-0 text-muted"><?= e($receipt['clinic_address']) ?> | <?= e($receipt['clinic_phone']) ?></p>
                </div>
                <div class="text-end"><strong><?= e($receipt['receipt_number']) ?></strong><br><small><?= e(date('M j, Y h:i A', strtotime($receipt['issued_at']))) ?></small></div>
            </div>
            <h2 class="h5">Patient Information</h2>
            <p><strong><?= e($receipt['full_name']) ?></strong><br><?= e($receipt['phone']) ?> <?= $receipt['email'] ? '| ' . e($receipt['email']) : '' ?></p>
            <h2 class="h5 mt-4">Appointment Information</h2>
            <p><?= e($receipt['reason'] ?: 'General payment') ?><br><?= $receipt['appointment_date'] ? e(date('M j, Y', strtotime($receipt['appointment_date'])) . ' ' . substr($receipt['appointment_time'], 0, 5)) : 'No appointment linked' ?></p>
            <h2 class="h5 mt-4">Payment Details</h2>
            <table class="table">
                <tr><th>Amount</th><td>$<?= number_format((float) $receipt['amount'], 2) ?></td></tr>
                <tr><th>Method</th><td><?= e($receipt['payment_method']) ?></td></tr>
                <tr><th>Status</th><td><?= e($receipt['payment_status']) ?></td></tr>
                <tr><th>Reference</th><td><?= e($receipt['reference_number'] ?: 'N/A') ?></td></tr>
            </table>
            <div class="d-flex gap-2 no-print">
                <button class="btn btn-primary" onclick="window.print()">Print / Save PDF</button>
                <a class="btn btn-outline-secondary" href="view.php">Back</a>
            </div>
        </section>
    </main>
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



