<?php
require_once __DIR__ . '/../includes/auth.php';
require_access('followups');

$followups = $conn->query(
    'SELECT f.*, p.full_name, p.phone, t.treatment_name
     FROM followups f
     JOIN patients p ON p.id = f.patient_id
     LEFT JOIN treatments t ON t.id = f.treatment_id
     WHERE 1=1 ' . doctor_patient_filter('p') . '
     ORDER BY f.followup_date ASC, f.id DESC'
)->fetch_all(MYSQLI_ASSOC);

$followup_scope = doctor_patient_filter('p');
$total_followups = count_table($conn, "SELECT COUNT(*) FROM followups f JOIN patients p ON p.id = f.patient_id WHERE 1=1 $followup_scope");
$scheduled_followups = count_table($conn, "SELECT COUNT(*) FROM followups f JOIN patients p ON p.id = f.patient_id WHERE f.status = 'Scheduled' $followup_scope");
$completed_followups = count_table($conn, "SELECT COUNT(*) FROM followups f JOIN patients p ON p.id = f.patient_id WHERE f.status = 'Done' $followup_scope");
$missed_followups = count_table($conn, "SELECT COUNT(*) FROM followups f JOIN patients p ON p.id = f.patient_id WHERE f.status = 'Missed' $followup_scope");

function followup_initials($name)
{
    $parts = preg_split('/\s+/', trim($name));
    $first = strtoupper(substr($parts[0] ?? '', 0, 1));
    $last = strtoupper(substr($parts[count($parts) - 1] ?? '', 0, 1));
    return $first . ($last !== $first ? $last : '');
}

function followup_tone($followup)
{
    if ($followup['status'] === 'Done') {
        return 'done';
    }
    if ($followup['status'] === 'Missed' || strtotime($followup['followup_date']) < strtotime(date('Y-m-d'))) {
        return 'missed';
    }
    return 'scheduled';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Follow-Up Management</title>
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
        <header class="clinic-topbar followup-topbar">
            <form class="top-search followup-search" action="<?= BASE_URL ?>/patients/view.php" method="get">
                <i class="bi bi-search"></i>
                <input name="search" placeholder="Search patients or follow-ups...">
            </form>
            <div class="top-actions admin-profile">
                <button class="dark-toggle" id="darkToggle"  title="Toggle dark mode" type="button" style="border:0;background:transparent;color:var(--text-muted);font-size:1.35rem;cursor:pointer;">
                    <i class="bi bi-moon-fill" id="darkIcon"></i>
                </button>
                <button type="button" aria-label="Notifications"><i class="bi bi-bell"></i></button>
                <button type="button" aria-label="Help"><i class="bi bi-question-circle"></i></button>
                <span class="profile-divider"></span>
                <span class="admin-copy"><strong><?= e($_SESSION['admin_name'] ?? 'Admin User') ?></strong><small><?= e(current_role()) ?></small></span>
                <div class="admin-avatar"><?= e(followup_initials($_SESSION['admin_name'] ?? 'Admin User')) ?></div>
            </div>
        </header>

        <main class="clinic-content followup-content">
            <div class="patient-head">
                <div>
                    <h1>Follow-Up Management</h1>
                    <p>Track scheduled reviews, patient outcomes, and overdue care tasks.</p>
                </div>
                <a class="add-patient-btn" href="add.php"><i class="bi bi-calendar-plus"></i>Schedule Follow-Up</a>
            </div>

            <?php show_flash(); ?>

            <div class="patient-metrics followup-metrics">
                <article class="patient-metric"><div><p>Total Follow-Ups</p><strong><?= number_format($total_followups) ?></strong></div><span class="metric-icon blue"><i class="bi bi-clipboard2-check"></i></span></article>
                <article class="patient-metric"><div><p>Scheduled</p><strong class="blue-text"><?= number_format($scheduled_followups) ?></strong></div><span class="metric-icon blue"><i class="bi bi-calendar-event"></i></span></article>
                <article class="patient-metric"><div><p>Recorded Results</p><strong class="green-text"><?= number_format($completed_followups) ?></strong></div><span class="metric-icon mint"><i class="bi bi-check2-circle"></i></span></article>
                <article class="patient-metric"><div><p>Missed / Overdue</p><strong class="red-text"><?= number_format($missed_followups) ?></strong></div><span class="metric-icon pale-red"><i class="bi bi-exclamation-lg"></i></span></article>
            </div>

            <div class="followup-layout">
                <section class="followup-board">
                    <div class="followup-board-head">
                        <div>
                            <h2>Follow-Up Queue</h2>
                            <p>Prioritized by next scheduled review date</p>
                        </div>
                        <button type="button"><i class="bi bi-filter-left"></i>Filters</button>
                    </div>

                    <div class="followup-list">
                        <?php foreach ($followups as $followup): ?>
                            <?php $tone = followup_tone($followup); ?>
                            <article class="followup-item <?= $tone ?>">
                                <time>
                                    <strong><?= e(date('d', strtotime($followup['followup_date']))) ?></strong>
                                    <span><?= e(strtoupper(date('M Y', strtotime($followup['followup_date'])))) ?></span>
                                </time>
                                <div class="followup-person">
                                    <span><?= e(followup_initials($followup['full_name'])) ?></span>
                                    <div>
                                        <h3><?= e($followup['full_name']) ?></h3>
                                        <p><?= e($followup['phone']) ?> â€¢ <?= e($followup['treatment_name'] ?? 'General Review') ?></p>
                                    </div>
                                </div>
                                <p class="followup-result"><?= e($followup['result'] ?: 'No result recorded yet.') ?></p>
                                <span class="followup-status"><?= e($tone === 'missed' ? 'Attention' : $followup['status']) ?></span>
                                <div class="followup-actions">
                                    <a href="edit.php?id=<?= $followup['id'] ?>">Record Result</a>
                                    <a href="delete.php?id=<?= $followup['id'] ?>" onclick="return confirm('Delete this follow-up?')">Delete</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                        <?php if (!$followups): ?>
                            <div class="empty-state">No follow-ups found.</div>
                        <?php endif; ?>
                    </div>
                </section>

                <aside class="followup-side">
                    <section class="followup-focus-card">
                        <h2><i class="bi bi-bell"></i>Care Focus</h2>
                        <p><?= number_format($scheduled_followups) ?> scheduled follow-ups need review. Prioritize patients due this week and record outcomes after each visit.</p>
                        <a href="add.php">Create Follow-Up</a>
                    </section>
                    <section class="followup-focus-card light">
                        <h2><i class="bi bi-activity"></i>Outcome Health</h2>
                        <div class="capacity-row">
                            <div><span>Recorded</span><strong><?= $total_followups ? round(($completed_followups / $total_followups) * 100) : 0 ?>%</strong></div>
                            <div class="capacity-track green"><span style="width: <?= $total_followups ? round(($completed_followups / $total_followups) * 100) : 0 ?>%"></span></div>
                        </div>
                        <div class="capacity-row">
                            <div><span>Pending</span><strong><?= $total_followups ? round(($scheduled_followups / $total_followups) * 100) : 0 ?>%</strong></div>
                            <div class="capacity-track"><span style="width: <?= $total_followups ? round(($scheduled_followups / $total_followups) * 100) : 0 ?>%"></span></div>
                        </div>
                    </section>
                </aside>
            </div>
        </main>
    </section>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
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
<script>
if (typeof toggleDark !== 'function') {
    window.toggleDark = function() {
        var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        if (isDark) {
            document.documentElement.removeAttribute('data-theme');
            localStorage.setItem('hcp_theme', 'light');
            var icon = document.getElementById('darkIcon');
            if (icon) icon.className = 'bi bi-moon-fill';
        } else {
            document.documentElement.setAttribute('data-theme', 'dark');
            localStorage.setItem('hcp_theme', 'dark');
            var icon = document.getElementById('darkIcon');
            if (icon) icon.className = 'bi bi-sun-fill';
        }
    };
    (function () {
        if (localStorage.getItem('hcp_theme') === 'dark') {
            var icon = document.getElementById('darkIcon');
            if (icon) icon.className = 'bi bi-sun-fill';
        }
    })();
}
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




