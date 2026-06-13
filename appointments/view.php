<?php
require_once __DIR__ . '/../includes/auth.php';
require_access('appointments');
$page_title = 'Appointments';
require_once __DIR__ . '/../includes/header.php';

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? 'All';
$doctor_id = (int) ($_GET['doctor_id'] ?? 0);
$date_from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_from'] ?? '') ? $_GET['date_from'] : '';
$date_to = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_to'] ?? '') ? $_GET['date_to'] : '';
$selected_id = (int) ($_GET['id'] ?? 0);

$doctors = $conn->query("SELECT id, full_name, specialization FROM doctors WHERE status = 'Active' ORDER BY full_name")->fetch_all(MYSQLI_ASSOC);
$where = 'WHERE 1=1';
$types = '';
$params = [];

if ($search !== '') {
    $like = '%' . $search . '%';
    $where .= ' AND (p.full_name LIKE ? OR p.phone LIKE ? OR a.reason LIKE ? OR a.notes LIKE ? OR d.full_name LIKE ?)';
    $types .= 'sssss';
    array_push($params, $like, $like, $like, $like, $like);
}

if (in_array($status, ['Pending', 'Approved', 'Rejected', 'Completed', 'Cancelled'], true)) {
    $where .= ' AND a.status = ?';
    $types .= 's';
    $params[] = $status;
}

if ($doctor_id > 0) {
    $where .= ' AND a.doctor_id = ?';
    $types .= 'i';
    $params[] = $doctor_id;
}

if ($date_from !== '') {
    $where .= ' AND a.appointment_date >= ?';
    $types .= 's';
    $params[] = $date_from;
}

if ($date_to !== '') {
    $where .= ' AND a.appointment_date <= ?';
    $types .= 's';
    $params[] = $date_to;
}

$sql = "SELECT a.*, p.full_name patient_name, p.phone, p.email, p.gender, d.full_name doctor_name, d.specialization
    FROM appointments a
    JOIN patients p ON p.id = a.patient_id
    LEFT JOIN doctors d ON d.id = a.doctor_id
    $where
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
    LIMIT 100";
$stmt = $conn->prepare($sql);
if ($types !== '') {
    bind_params($stmt, $types, $params);
}
$appointments = fetch_all($stmt);

if ($selected_id <= 0 && $appointments) {
    $selected_id = (int) $appointments[0]['id'];
}

$selected = null;
foreach ($appointments as $appointment) {
    if ((int) $appointment['id'] === $selected_id) {
        $selected = $appointment;
        break;
    }
}

if (!$selected && $selected_id > 0) {
    $stmt = $conn->prepare('SELECT a.*, p.full_name patient_name, p.phone, p.email, p.gender, d.full_name doctor_name, d.specialization
        FROM appointments a
        JOIN patients p ON p.id = a.patient_id
        LEFT JOIN doctors d ON d.id = a.doctor_id
        WHERE a.id = ?');
    $stmt->bind_param('i', $selected_id);
    $selected = fetch_one($stmt);
}

$status_counts = [
    'Pending' => count_table($conn, "SELECT COUNT(*) FROM appointments WHERE status = 'Pending'"),
    'Approved' => count_table($conn, "SELECT COUNT(*) FROM appointments WHERE status = 'Approved'"),
    'Completed' => count_table($conn, "SELECT COUNT(*) FROM appointments WHERE status = 'Completed'"),
    'Cancelled' => count_table($conn, "SELECT COUNT(*) FROM appointments WHERE status = 'Cancelled'"),
];

function appointment_badge_class($status)
{
    if ($status === 'Completed' || $status === 'Approved') {
        return 'active';
    }
    if ($status === 'Cancelled' || $status === 'Rejected') {
        return 'inactive';
    }
    return 'upcoming';
}

function appointment_query(array $overrides = [])
{
    $query = array_merge($_GET, $overrides);
    return http_build_query(array_filter($query, fn($value) => $value !== '' && $value !== null));
}
?>
<div class="patient-head">
    <div><h1>Appointments</h1><p>Advanced appointment list for reception, scheduling, and follow-up actions.</p></div>
    <a class="add-patient-btn" href="add.php"><i class="bi bi-plus-lg"></i>New Appointment</a>
</div>

<div class="patient-metrics">
    <?php foreach ($status_counts as $label => $value): ?>
        <article class="patient-metric"><div><p><?= e($label) ?></p><strong><?= number_format($value) ?></strong></div><span class="metric-icon <?= $label === 'Cancelled' ? 'red' : ($label === 'Pending' ? 'blue' : 'mint') ?>"><i class="bi bi-calendar-check"></i></span></article>
    <?php endforeach; ?>
</div>

<section class="appointment-list-shell">
    <div class="appointment-list-header">
        <div>
            <h2>Appointment Worklist</h2>
            <p><?= number_format(count($appointments)) ?> records showing</p>
        </div>
        <a href="add.php"><i class="bi bi-calendar-plus"></i> Book New</a>
    </div>

    <form class="appointment-list-toolbar" method="get">
        <label class="appointment-search-box"><i class="bi bi-search"></i><input name="search" value="<?= e($search) ?>" placeholder="Search patient, phone, doctor, reason..."></label>
        <select name="status">
            <?php foreach (['All', 'Pending', 'Approved', 'Rejected', 'Completed', 'Cancelled'] as $option): ?>
                <option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= e($option) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="doctor_id">
            <option value="0">All Doctors</option>
            <?php foreach ($doctors as $doctor): ?>
                <option value="<?= (int) $doctor['id'] ?>" <?= $doctor_id === (int) $doctor['id'] ? 'selected' : '' ?>><?= e($doctor['full_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="date_from" value="<?= e($date_from) ?>">
        <input type="date" name="date_to" value="<?= e($date_to) ?>">
        <button type="submit"><i class="bi bi-funnel"></i> Filter</button>
    </form>

    <div class="appointment-workspace">
        <div class="appointment-list-panel">
            <?php foreach ($appointments as $appointment): ?>
                <?php $active = (int) $appointment['id'] === $selected_id ? 'active' : ''; ?>
                <a class="appointment-list-item <?= e($active) ?>" href="view.php?<?= e(appointment_query(['id' => $appointment['id']])) ?>">
                    <time>
                        <strong><?= e(date('h:i', strtotime($appointment['appointment_time']))) ?></strong>
                        <span><?= e(date('A', strtotime($appointment['appointment_time']))) ?></span>
                    </time>
                    <div class="appointment-list-main">
                        <div>
                            <h3><?= e($appointment['patient_name']) ?></h3>
                            <p><?= e($appointment['reason']) ?></p>
                            <small><i class="bi bi-calendar3"></i><?= e(date('M j, Y', strtotime($appointment['appointment_date']))) ?> · <?= e($appointment['doctor_name'] ?: 'No doctor assigned') ?></small>
                        </div>
                        <em class="status-pill <?= e(appointment_badge_class($appointment['status'])) ?>"><?= e($appointment['status']) ?></em>
                    </div>
                </a>
            <?php endforeach; ?>
            <?php if (!$appointments): ?><div class="empty-state">No appointments match the selected filters.</div><?php endif; ?>
        </div>

        <aside class="appointment-detail-panel">
            <?php if ($selected): ?>
                <div class="appointment-detail-head">
                    <div>
                        <span class="status-pill <?= e(appointment_badge_class($selected['status'])) ?>"><?= e($selected['status']) ?></span>
                        <h2><?= e($selected['patient_name']) ?></h2>
                        <p><?= e($selected['reason']) ?></p>
                    </div>
                    <a href="edit.php?id=<?= (int) $selected['id'] ?>"><i class="bi bi-pencil-square"></i>Edit</a>
                </div>
                <div class="appointment-detail-grid">
                    <div><span>Date</span><strong><?= e(date('M j, Y', strtotime($selected['appointment_date']))) ?></strong></div>
                    <div><span>Time</span><strong><?= e(date('h:i A', strtotime($selected['appointment_time']))) ?></strong></div>
                    <div><span>Doctor</span><strong><?= e($selected['doctor_name'] ?: 'Unassigned') ?></strong><small><?= e($selected['specialization'] ?: '') ?></small></div>
                    <div><span>Phone</span><strong><?= e($selected['phone']) ?></strong></div>
                    <div><span>Email</span><strong><?= e($selected['email'] ?: 'Not provided') ?></strong></div>
                    <div><span>Gender</span><strong><?= e($selected['gender']) ?></strong></div>
                </div>
                <div class="appointment-notes-box">
                    <h3>Appointment Notes</h3>
                    <p><?= e($selected['notes'] ?: 'No appointment notes recorded yet.') ?></p>
                    <?php if (!empty($selected['remarks'])): ?><p><strong>Doctor Remarks:</strong> <?= e($selected['remarks']) ?></p><?php endif; ?>
                </div>
                <div class="appointment-detail-actions">
                    <a class="btn btn-primary" href="edit.php?id=<?= (int) $selected['id'] ?>"><i class="bi bi-check2-circle"></i>Manage Appointment</a>
                    <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>/patients/profile.php?id=<?= (int) $selected['patient_id'] ?>"><i class="bi bi-person-vcard"></i>Patient Profile</a>
                </div>
            <?php else: ?>
                <div class="empty-state">Select an appointment to view details.</div>
            <?php endif; ?>
        </aside>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
