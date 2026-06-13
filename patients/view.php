<?php
require_once __DIR__ . '/../includes/auth.php';
require_access('patients');

$search = trim($_GET['search'] ?? '');
$patient_scope = doctor_patient_filter('p');
$total_patients = count_table($conn, "SELECT COUNT(*) FROM patients p WHERE 1=1 $patient_scope");
$new_this_month = count_table($conn, "SELECT COUNT(*) FROM patients p WHERE YEAR(p.created_at) = YEAR(CURDATE()) AND MONTH(p.created_at) = MONTH(CURDATE()) $patient_scope");
$critical_followups = count_table($conn, "SELECT COUNT(*) FROM followups f JOIN patients p ON p.id = f.patient_id WHERE f.status IN ('Scheduled', 'Missed') AND f.followup_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) $patient_scope");
$active_cases = count_table($conn, "SELECT COUNT(DISTINCT t.patient_id) FROM treatments t JOIN patients p ON p.id = t.patient_id WHERE t.progress <> 'Completed' $patient_scope");

if ($search !== '') {
    $like = '%' . $search . '%';
    $id_search = preg_replace('/[^0-9]/', '', $search);
    $stmt = $conn->prepare(
        "SELECT p.*,
            (SELECT MAX(a.appointment_date) FROM appointments a WHERE a.patient_id = p.id) AS last_visit,
            (SELECT a.reason FROM appointments a WHERE a.patient_id = p.id ORDER BY a.appointment_date DESC, a.appointment_time DESC LIMIT 1) AS last_reason
         FROM patients p
         WHERE (p.full_name LIKE ? OR p.phone LIKE ? OR p.email LIKE ? OR CAST(p.id AS CHAR) LIKE ?) $patient_scope
         ORDER BY p.created_at DESC"
    );
    $stmt->bind_param('ssss', $like, $like, $like, $id_search);
    $patients = fetch_all($stmt);
} else {
    $patients = $conn->query(
        "SELECT p.*,
            (SELECT MAX(a.appointment_date) FROM appointments a WHERE a.patient_id = p.id) AS last_visit,
            (SELECT a.reason FROM appointments a WHERE a.patient_id = p.id ORDER BY a.appointment_date DESC, a.appointment_time DESC LIMIT 1) AS last_reason
         FROM patients p
         WHERE 1=1 $patient_scope
         ORDER BY p.created_at DESC
         LIMIT 5"
    )->fetch_all(MYSQLI_ASSOC);
}

function pm_initials($name)
{
    $parts = preg_split('/\s+/', trim($name));
    $first = strtoupper(substr($parts[0] ?? '', 0, 1));
    $last = strtoupper(substr($parts[count($parts) - 1] ?? '', 0, 1));
    return $first . ($last !== $first ? $last : '');
}

function patient_age($dob)
{
    if (!$dob) {
        return 'Age N/A';
    }

    return date_diff(date_create($dob), date_create('today'))->y . ' Yrs';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Patient Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/css/style.css" rel="stylesheet">
</head>
<body class="dashboard-shell">
    <?php render_role_sidebar($_SERVER['SCRIPT_NAME'] ?? ''); ?>

    <section class="clinic-main">
        <header class="clinic-topbar patient-topbar">
            <form class="top-search patient-search" method="get">
                <i class="bi bi-search"></i>
                <input name="search" value="<?= e($search) ?>" placeholder="Search patients by name, ID or phone...">
            </form>
            <div class="top-actions admin-profile">
                <button type="button" aria-label="Notifications"><i class="bi bi-bell"></i></button>
                <button type="button" aria-label="Help"><i class="bi bi-question-circle"></i></button>
                <span class="profile-divider"></span>
                <span class="admin-copy"><strong><?= e($_SESSION['admin_name'] ?? 'Admin User') ?></strong><small><?= e(current_role()) ?></small></span>
                <div class="admin-avatar"><?= e(pm_initials($_SESSION['admin_name'] ?? 'Admin User')) ?></div>
            </div>
        </header>

        <main class="clinic-content">
            <div class="patient-head">
                <div>
                    <h1>Patient Management</h1>
                    <p>Manage records, medical history, and scheduled sessions.</p>
                </div>
                <?php if (in_array(current_role(), ['Administrator', 'Receptionist'], true)): ?>
                    <a class="add-patient-btn" href="add.php"><i class="bi bi-person-plus"></i>Add New Patient</a>
                <?php endif; ?>
            </div>

            <?php show_flash(); ?>

            <div class="patient-metrics">
                <article class="patient-metric">
                    <div>
                        <p>Total Patients</p>
                        <strong><?= number_format($total_patients) ?></strong>
                    </div>
                    <span class="metric-icon blue"><i class="bi bi-people"></i></span>
                </article>
                <article class="patient-metric">
                    <div>
                        <p>Active Cases</p>
                        <strong class="green-text"><?= number_format($active_cases) ?></strong>
                    </div>
                    <span class="metric-icon mint"><i class="bi bi-activity"></i></span>
                </article>
                <article class="patient-metric">
                    <div>
                        <p>New This<br>Month</p>
                        <strong class="blue-text"><?= number_format($new_this_month) ?></strong>
                    </div>
                    <span class="metric-icon blue"><i class="bi bi-graph-up-arrow"></i></span>
                </article>
                <article class="patient-metric">
                    <div>
                        <p>Critical<br>Followups</p>
                        <strong class="red-text"><?= number_format($critical_followups) ?></strong>
                    </div>
                    <span class="metric-icon pale-red"><i class="bi bi-exclamation-lg"></i></span>
                </article>
            </div>

            <section class="patient-management-card">
                <div class="patient-tabs">
                    <div class="tab-links">
                        <a class="active" href="<?= BASE_URL ?>/patients/view.php">All Patients</a>
                        <a href="<?= BASE_URL ?>/patients/view.php">Active</a>
                        <a href="<?= BASE_URL ?>/patients/view.php">Archive</a>
                    </div>
                    <button type="button"><i class="bi bi-filter-left"></i>Advanced Filters</button>
                </div>

                <div class="patient-list-grid patient-list-head">
                    <span>ID</span>
                    <span>Patient Name</span>
                    <span>Contact Info</span>
                    <span>Last Visit</span>
                    <span>Status</span>
                    <span>Actions</span>
                </div>

                <?php foreach ($patients as $index => $patient): ?>
                    <?php
                    $avatar_classes = ['blue-avatar', 'lavender-avatar', 'mint-avatar', 'rose-avatar', 'gray-avatar'];
                    $active = !empty($patient['last_visit']) || strtotime($patient['created_at']) >= strtotime('-60 days');
                    ?>
                    <div class="patient-list-grid patient-list-row">
                        <span class="patient-id">#HC-<br><?= str_pad((string) $patient['id'], 4, '0', STR_PAD_LEFT) ?></span>
                        <span class="patient-list-name">
                            <span class="patient-avatar <?= $avatar_classes[$index % count($avatar_classes)] ?>"><?= e(pm_initials($patient['full_name'])) ?></span>
                            <span><a class="patient-profile-link" href="profile.php?id=<?= $patient['id'] ?>"><?= e($patient['full_name']) ?></a><small><?= e($patient['gender']) ?>, <?= e(patient_age($patient['date_of_birth'])) ?></small></span>
                        </span>
                        <span class="patient-contact">
                            <strong><?= e($patient['phone']) ?></strong>
                            <small><?= e($patient['email'] ?: 'No email saved') ?></small>
                        </span>
                        <span class="last-visit">
                            <strong><?= $patient['last_visit'] ? e(date('M j, Y', strtotime($patient['last_visit']))) : 'No visit yet' ?></strong>
                            <small><?= e($patient['last_reason'] ?: 'Initial record') ?></small>
                        </span>
                        <span><em class="status-pill <?= $active ? 'active' : 'inactive' ?>"><?= $active ? 'Active' : 'Inactive' ?></em></span>
                        <span class="patient-actions">
                            <a href="profile.php?id=<?= $patient['id'] ?>" title="Profile"><i class="bi bi-eye"></i></a>
                            <?php if (in_array(current_role(), ['Administrator', 'Receptionist'], true)): ?>
                                <a href="edit.php?id=<?= $patient['id'] ?>" title="Edit"><i class="bi bi-pencil-square"></i></a>
                            <?php endif; ?>
                            <?php if (current_role() === 'Administrator'): ?>
                                <a href="delete.php?id=<?= $patient['id'] ?>" title="Delete" onclick="return confirm('Delete this patient?')"><i class="bi bi-trash"></i></a>
                            <?php endif; ?>
                        </span>
                    </div>
                <?php endforeach; ?>

                <?php if (!$patients): ?>
                    <div class="empty-state">No patients found.</div>
                <?php endif; ?>

                <div class="patient-pagination">
                    <span>Showing 1 to <?= count($patients) ?> of <?= number_format($total_patients) ?> patients</span>
                    <div>
                        <button type="button" disabled><i class="bi bi-chevron-left"></i></button>
                        <button type="button" class="active">1</button>
                        <button type="button">2</button>
                        <button type="button">3</button>
                        <span>...</span>
                        <button type="button">250</button>
                        <button type="button"><i class="bi bi-chevron-right"></i></button>
                    </div>
                </div>
            </section>

            <div class="patient-bottom-grid">
                <section class="capacity-card">
                    <div class="capacity-head">
                        <h2>Clinic Capacity</h2>
                        <span>Live Update</span>
                    </div>
                    <div class="capacity-row">
                        <div><span>Examination Rooms</span><strong>85% Full</strong></div>
                        <div class="capacity-track"><span style="width: 85%"></span></div>
                    </div>
                    <div class="capacity-row">
                        <div><span>Surgical Suites</span><strong>40% Full</strong></div>
                        <div class="capacity-track green"><span style="width: 40%"></span></div>
                    </div>
                </section>

                <section class="trust-card">
                    <h2>Medical Trust</h2>
                    <p>Our surgical procedures maintain a 99.8% precision rating as of Q4 2023.</p>
                    <a href="<?= BASE_URL ?>/reports/index.php">View Compliance Report <i class="bi bi-arrow-right"></i></a>
                </section>
            </div>
        </main>
    </section>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
