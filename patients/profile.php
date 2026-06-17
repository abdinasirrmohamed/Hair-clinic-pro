<?php
require_once __DIR__ . '/../includes/auth.php';
require_access('patients');

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    $fallback = $conn->query('SELECT id FROM patients ORDER BY created_at DESC LIMIT 1')->fetch_assoc();
    $id = (int) ($fallback['id'] ?? 0);
}

$stmt = $conn->prepare('SELECT * FROM patients WHERE id = ?');
$stmt->bind_param('i', $id);
$patient = fetch_one($stmt);

if (!$patient) {
    flash('danger', 'Patient not found. Add a patient first.');
    redirect('/patients/view.php');
}

require_patient_assignment($id);

$stmt = $conn->prepare('SELECT * FROM treatments WHERE patient_id = ? ORDER BY treatment_date DESC, id DESC LIMIT 2');
$stmt->bind_param('i', $id);
$treatments = fetch_all($stmt);

$stmt = $conn->prepare('SELECT * FROM appointments WHERE patient_id = ? ORDER BY appointment_date DESC, appointment_time DESC LIMIT 3');
$stmt->bind_param('i', $id);
$appointments = fetch_all($stmt);

function profile_initials($name)
{
    $parts = preg_split('/\s+/', trim($name));
    $first = strtoupper(substr($parts[0] ?? '', 0, 1));
    $last = strtoupper(substr($parts[count($parts) - 1] ?? '', 0, 1));
    return $first . ($last !== $first ? $last : '');
}

function profile_age($dob)
{
    if (!$dob) {
        return 'Age N/A';
    }

    return date_diff(date_create($dob), date_create('today'))->y . ' Years Old';
}

function profile_progress($progress, $index)
{
    if ($progress === 'Completed') {
        return 100;
    }
    if ($progress === 'In Progress') {
        return $index === 0 ? 65 : 33;
    }
    return 18;
}

function profile_status_class($status)
{
    if ($status === 'Completed') {
        return 'completed';
    }
    if ($status === 'Cancelled') {
        return 'cancelled';
    }
    return 'upcoming';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Patient Profile</title>
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
        <header class="clinic-topbar profile-topbar">
            <form class="top-search profile-search" action="<?= BASE_URL ?>/patients/view.php" method="get">
                <i class="bi bi-search"></i>
                <input name="search" placeholder="Search patients or appointments...">
            </form>
            <div class="top-actions admin-profile">
                <button class="dark-toggle" id="darkToggle"  title="Toggle dark mode" type="button" style="border:0;background:transparent;color:var(--text-muted);font-size:1.35rem;cursor:pointer;">
                    <i class="bi bi-moon-fill" id="darkIcon"></i>
                </button>
                <button type="button" aria-label="Notifications"><i class="bi bi-bell"></i></button>
                <button type="button" aria-label="Help"><i class="bi bi-question-circle"></i></button>
                <span class="profile-divider"></span>
                <span class="admin-copy"><strong><?= e($_SESSION['admin_name'] ?? 'Admin User') ?></strong><small><?= e(current_role()) ?></small></span>
                <div class="admin-avatar"><?= e(profile_initials($_SESSION['admin_name'] ?? 'Admin User')) ?></div>
            </div>
        </header>

        <main class="clinic-content profile-content">
            <?php show_flash(); ?>

            <section class="profile-hero-card">
                <div class="profile-photo">
                    <span><?= e(profile_initials($patient['full_name'])) ?></span>
                    <i class="bi bi-patch-check-fill"></i>
                </div>
                <div class="profile-identity">
                    <div>
                        <h1><?= e($patient['full_name']) ?></h1>
                        <span class="patient-code">Patient ID: #HX-<?= str_pad((string) $patient['id'], 4, '0', STR_PAD_LEFT) ?></span>
                    </div>
                    <div class="profile-facts">
                        <span><i class="bi bi-calendar3"></i><?= e(profile_age($patient['date_of_birth'])) ?></span>
                        <span><i class="bi bi-person"></i><?= e($patient['gender']) ?></span>
                        <span><i class="bi bi-geo-alt"></i><?= e($patient['address'] ?: 'Location N/A') ?></span>
                    </div>
                    <div class="blood-line"><i class="bi bi-droplet-half"></i>O Positive</div>
                </div>
                <div class="profile-actions">
                    <?php if (in_array(current_role(), ['Administrator', 'Receptionist'], true)): ?>
                        <a class="edit-profile-btn" href="edit.php?id=<?= $patient['id'] ?>"><i class="bi bi-pencil"></i><span>Edit<br>Profile</span></a>
                    <?php endif; ?>
                    <?php if (can_access('treatments')): ?>
                        <a class="new-record-btn" href="<?= BASE_URL ?>/treatments/add.php"><i class="bi bi-plus-circle"></i><span>New<br>Record</span></a>
                    <?php endif; ?>
                </div>
            </section>

            <div class="profile-grid">
                <div class="profile-left">
                    <section class="profile-card active-treatment-card">
                        <div class="profile-card-head">
                            <h2>Active Treatment Plans</h2>
                            <i class="bi bi-three-dots-vertical"></i>
                        </div>
                        <div class="profile-treatment-grid">
                            <?php foreach ($treatments as $index => $treatment): ?>
                                <?php $progress = profile_progress($treatment['progress'], $index); ?>
                                <article class="mini-treatment <?= $index % 2 ? 'green' : 'blue' ?>">
                                    <div>
                                        <h3><?= e($treatment['treatment_name']) ?></h3>
                                        <span class="status-pill <?= $treatment['progress'] === 'Completed' ? 'inactive' : 'active' ?>"><?= e($treatment['progress'] === 'Started' ? 'Active' : $treatment['progress']) ?></span>
                                    </div>
                                    <p><?= e($treatment['notes'] ?: ($index % 2 ? 'Post-Op Recovery' : 'Scalp Zone A & B')) ?></p>
                                    <div class="mini-progress-meta"><span><?= $index % 2 ? 'Session 4 of 12' : 'Progress' ?></span><strong><?= $progress ?>%</strong></div>
                                    <div class="mini-progress"><span style="width: <?= $progress ?>%"></span></div>
                                </article>
                            <?php endforeach; ?>
                            <?php if (!$treatments): ?>
                                <article class="mini-treatment blue">
                                    <div><h3>No Active Plan</h3><span class="status-pill inactive">Draft</span></div>
                                    <p>Create a treatment record to begin tracking progress.</p>
                                    <div class="mini-progress-meta"><span>Progress</span><strong>0%</strong></div>
                                    <div class="mini-progress"><span style="width: 0%"></span></div>
                                </article>
                            <?php endif; ?>
                        </div>
                    </section>

                    <section class="profile-card appointment-history-card">
                        <div class="profile-card-head">
                            <h2>Recent Appointments</h2>
                            <a href="<?= BASE_URL ?>/appointments/view.php">View All</a>
                        </div>
                        <div class="profile-appointment-head">
                            <span>Date<br>& Time</span>
                            <span>Treatment<br>Type</span>
                            <span>Practitioner</span>
                            <span>Status</span>
                        </div>
                        <?php foreach ($appointments as $appointment): ?>
                            <div class="profile-appointment-row">
                                <time>
                                    <?= e(date('M', strtotime($appointment['appointment_date']))) ?><br>
                                    <?= e(date('d,', strtotime($appointment['appointment_date']))) ?><br>
                                    <?= e(date('Y', strtotime($appointment['appointment_date']))) ?>
                                    <small><?= e(date('h:i A', strtotime($appointment['appointment_time']))) ?></small>
                                </time>
                                <strong><?= e($appointment['reason']) ?></strong>
                                <span class="practitioner"><span><?= e(profile_initials('Dr. Sarah Jenkins')) ?></span>Dr. Sarah<br>Jenkins</span>
                                <em class="profile-status <?= profile_status_class($appointment['status']) ?>"><?= e($appointment['status'] === 'Pending' ? 'Upcoming' : $appointment['status']) ?></em>
                            </div>
                        <?php endforeach; ?>
                        <?php if (!$appointments): ?>
                            <div class="empty-state">No recent appointments found.</div>
                        <?php endif; ?>
                    </section>
                </div>

                <aside class="profile-right">
                    <section class="profile-side-card">
                        <h2><i class="bi bi-clipboard2-pulse"></i>Medical History</h2>
                        <div class="side-divider"></div>
                        <h3>Allergies</h3>
                        <div class="allergy-tags"><span>Penicillin</span><span>Latex</span></div>
                        <h3>Past Conditions</h3>
                        <ul>
                            <li>Hypertension (Managed)</li>
                            <li>Minor Scalp Eczema (2021)</li>
                        </ul>
                        <h3>Family History</h3>
                        <p><?= e($patient['medical_notes'] ?: 'Paternal male pattern baldness (Grade IV), cardiovascular history on maternal side.') ?></p>
                    </section>

                    <section class="profile-side-card contact-card">
                        <h2><i class="bi bi-file-earmark-person"></i>Contact Info</h2>
                        <div class="side-divider"></div>
                        <div class="contact-line"><i class="bi bi-telephone"></i><span><strong><?= e($patient['phone']) ?></strong><small>Primary Mobile</small></span></div>
                        <div class="contact-line"><i class="bi bi-envelope"></i><span><strong><?= e($patient['email'] ?: 'No email saved') ?></strong><small>Work Email</small></span></div>
                        <div class="contact-line"><i class="bi bi-house-door"></i><span><strong><?= e($patient['address'] ?: 'No address saved') ?></strong><small>Patient Address</small></span></div>
                        <div class="side-divider"></div>
                        <h3>Emergency Contact</h3>
                        <div class="emergency-box"><strong>Emergency Contact</strong><small>Spouse â€¢ +44 7700 900 456</small></div>
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




