<?php
require_once __DIR__ . '/../includes/auth.php';
require_access('treatments');

$stmt = $conn->prepare(
    'SELECT t.*, p.full_name, p.id AS patient_code
     FROM treatments t
     JOIN patients p ON p.id = t.patient_id
     WHERE 1=1 ' . doctor_patient_filter('p') . '
     ORDER BY t.treatment_date DESC, t.id DESC
     LIMIT 1'
);
$treatment = fetch_one($stmt);

$has_treatment = (bool) $treatment;
$patient_name = $treatment['full_name'] ?? 'No Treatment Selected';
$patient_code = $treatment['patient_code'] ?? 0;
$treatment_name = $treatment['treatment_name'] ?? 'FUE Hair Restoration';
$treatment_date = $treatment['treatment_date'] ?? date('Y-m-d');
$progress = $treatment['progress'] ?? 'Started';
$notes = trim($treatment['notes'] ?? '');
$description = $notes !== ''
    ? $notes
    : 'Follicular Unit Extraction focused on the frontal hairline and crown areas to restore density and natural growth patterns. The procedure aims for a total of 2,500 grafts across targeted zones.';

function treatment_initials($name)
{
    $parts = preg_split('/\s+/', trim($name));
    $first = strtoupper(substr($parts[0] ?? '', 0, 1));
    $last = strtoupper(substr($parts[count($parts) - 1] ?? '', 0, 1));
    return $first . ($last !== $first ? $last : '');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Treatment Plan</title>
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
    <?php render_role_sidebar($_SERVER['SCRIPT_NAME'] ?? ''); ?>

    <section class="clinic-main">
        <header class="clinic-topbar treatment-topbar">
            <div class="treatment-top-title">
                <a href="<?= BASE_URL ?>/patients/view.php" aria-label="Back to patients"><i class="bi bi-arrow-left"></i></a>
                <strong>Treatment Plan</strong>
            </div>
            <form class="top-search treatment-search" action="<?= BASE_URL ?>/patients/view.php" method="get">
                <i class="bi bi-search"></i>
                <input name="search" placeholder="Search patient or record...">
            </form>
            <div class="top-actions">
                <button type="button" aria-label="Notifications"><i class="bi bi-bell"></i></button>
                <button type="button" aria-label="Help"><i class="bi bi-question-circle"></i></button>
                <div class="admin-avatar dark-avatar"><?= e(treatment_initials($_SESSION['admin_name'] ?? 'Admin User')) ?></div>
            </div>
        </header>

        <main class="clinic-content treatment-content">
            <div class="treatment-breadcrumb">
                <a href="<?= BASE_URL ?>/patients/view.php">Patients</a>
                <i class="bi bi-chevron-right"></i>
                <span><?= e($patient_name) ?></span>
                <i class="bi bi-chevron-right"></i>
                <strong>Current Treatment</strong>
            </div>

            <div class="treatment-hero">
                <div>
                    <h1><?= e($patient_name) ?></h1>
                    <p>Patient ID: #HC-<?= str_pad((string) $patient_code, 4, '0', STR_PAD_LEFT) ?> â€¢ Last Visit: <?= e(date('M j, Y', strtotime($treatment_date))) ?></p>
                </div>
                <div class="treatment-actions">
                    <button type="button" onclick="window.print()">Download PDF</button>
                    <a href="<?= $has_treatment ? 'edit.php?id=' . (int) $treatment['id'] : 'add.php' ?>"><?= $has_treatment ? 'Edit Plan' : 'Add Plan' ?></a>
                </div>
            </div>

            <?php show_flash(); ?>

            <div class="treatment-plan-grid">
                <section class="active-plan-card">
                    <div class="plan-main">
                        <span class="plan-badge"><?= e($progress === 'Completed' ? 'Completed Plan' : 'Active Plan') ?></span>
                        <h2><?= e($treatment_name) ?></h2>
                        <div>
                            <h3>Description</h3>
                            <p><?= e($description) ?></p>
                        </div>
                    </div>
                    <aside class="plan-meta">
                        <strong>Duration</strong>
                        <span>6 Months</span>
                        <em>Started: <?= e(date('M j, Y', strtotime($treatment_date))) ?></em>
                    </aside>
                    <div class="plan-divider"></div>
                    <div class="plan-footer">
                        <div>
                            <h3>Assigned Specialist</h3>
                            <span class="specialist">
                                <span class="doctor-avatar">JV</span>
                                <span><strong>Dr. Julian Vance</strong><small>Senior Surgeon</small></span>
                            </span>
                        </div>
                        <div>
                            <h3>Next Milestone</h3>
                            <span class="milestone"><i class="bi bi-calendar3"></i><strong>Post-op Review (Week 12)</strong></span>
                            <small>Scheduled: <?= e(date('M j, Y', strtotime($treatment_date . ' +12 weeks'))) ?></small>
                        </div>
                    </div>
                </section>

                <aside class="medication-card">
                    <h2><i class="bi bi-clipboard2-pulse"></i>Medications</h2>
                    <div class="med-list">
                        <article><i class="bi bi-capsule"></i><span><strong>Minoxidil 5%</strong><small>Daily topical application</small></span></article>
                        <article><i class="bi bi-capsule"></i><span><strong>Finasteride 1mg</strong><small>Oral tablet, once daily</small></span></article>
                        <article><i class="bi bi-prescription2"></i><span><strong>Post-op Antibiotics</strong><small>Completed course (Nov 2023)</small></span></article>
                    </div>
                    <a href="<?= $has_treatment ? 'edit.php?id=' . (int) $treatment['id'] : 'add.php' ?>">Add New Prescription <i class="bi bi-plus"></i></a>
                </aside>
            </div>

            <section class="progress-notes-card">
                <div class="section-heading">
                    <h2><i class="bi bi-journal-medical"></i>Progress Notes</h2>
                    <a href="<?= $has_treatment ? 'edit.php?id=' . (int) $treatment['id'] : 'add.php' ?>">Add Note</a>
                </div>
                <div class="note-grid">
                    <article>
                        <div><strong>Week 8 Update</strong><span>Dec 20, 2023</span></div>
                        <h3>"Patient reporting minimal discomfort"</h3>
                        <p>Alexander confirms adherence to daily medication routine. No side effects noted from Finasteride.</p>
                    </article>
                    <article>
                        <div><strong>Week 4 Review</strong><span>Nov 22, 2023</span></div>
                        <h3>"Scalp healing well"</h3>
                        <p>Recipient site redness has subsided significantly. Donor area shows clean healing with no signs of infection.</p>
                    </article>
                    <article>
                        <div><strong>Procedure Day</strong><span><?= e(date('M j, Y', strtotime($treatment_date))) ?></span></div>
                        <h3>"Initial session successful"</h3>
                        <p>FUE session completed in 7 hours. 2,550 grafts harvested. Patient tolerated local anesthesia well.</p>
                    </article>
                </div>
            </section>

            <section class="visual-doc-card">
                <div class="section-heading">
                    <h2><i class="bi bi-camera"></i>Visual Documentation</h2>
                </div>
                <div class="visual-grid">
                    <article class="hair-shot shot-one"><span><?= e(date('M j, Y', strtotime($treatment_date))) ?></span></article>
                    <article class="hair-shot shot-two"><span><?= e(date('M j, Y', strtotime($treatment_date . ' +4 weeks'))) ?></span></article>
                    <article class="hair-shot shot-three"><span><?= e(date('M j, Y', strtotime($treatment_date . ' +8 weeks'))) ?></span></article>
                    <a class="upload-shot" href="<?= $has_treatment ? 'edit.php?id=' . (int) $treatment['id'] : 'add.php' ?>"><i class="bi bi-camera-fill"></i><span>Upload Today's Photo</span></a>
                </div>
            </section>
        </main>
    </section>

    <div class="floating-treatment-actions">
        <button type="button" onclick="window.print()" aria-label="Print"><i class="bi bi-printer"></i></button>
        <a href="<?= BASE_URL ?>/followups/add.php" aria-label="Schedule follow-up"><i class="bi bi-calendar-plus"></i></a>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
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




