<?php
require_once __DIR__ . '/../includes/auth.php';
require_access('doctor_appointments');
$page_title = 'My Appointments';
require_once __DIR__ . '/../includes/header.php';

$user_id = (int) ($_SESSION['admin_id'] ?? 0);
$doctor = $conn->query("SELECT * FROM doctors WHERE user_id = $user_id LIMIT 1")->fetch_assoc();
$doctor_id = (int) ($doctor['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appointment_id = (int) ($_POST['appointment_id'] ?? 0);
    $status = $_POST['status'] ?? 'Pending';
    $remarks = trim($_POST['remarks'] ?? '');
    if (!in_array($status, ['Approved', 'Rejected', 'Completed'], true)) {
        flash('danger', 'Invalid appointment action.');
        redirect('/doctor_appointments/view.php');
    }

    $timestamp_column = $status === 'Approved' ? 'approved_at' : ($status === 'Rejected' ? 'rejected_at' : 'completed_at');
    $stmt = $conn->prepare("UPDATE appointments SET status = ?, remarks = ?, `$timestamp_column` = NOW() WHERE id = ? AND doctor_id = ?");
    $stmt->bind_param('ssii', $status, $remarks, $appointment_id, $doctor_id);
    $stmt->execute();
    log_activity($status . ' appointment', 'Appointments', $appointment_id);
    flash('success', 'Appointment updated successfully.');
    redirect('/doctor_appointments/view.php');
}

$filter = $_GET['filter'] ?? 'all';
$where_filter = '';
if ($filter === 'pending') {
    $where_filter = " AND a.status = 'Pending'";
} elseif ($filter === 'today') {
    $where_filter = ' AND a.appointment_date = CURDATE()';
} elseif ($filter === 'upcoming') {
    $where_filter = " AND a.appointment_date >= CURDATE() AND a.status IN ('Pending','Approved')";
} elseif ($filter === 'history') {
    $where_filter = " AND (a.appointment_date < CURDATE() OR a.status IN ('Completed','Rejected','Cancelled'))";
}

if ($doctor_id > 0) {
    $stmt = $conn->prepare(
        'SELECT a.*, p.full_name, p.phone, p.medical_notes
         FROM appointments a
         JOIN patients p ON p.id = a.patient_id
         WHERE a.doctor_id = ?
         ' . $where_filter . '
         ORDER BY a.appointment_date DESC, a.appointment_time DESC'
    );
    $stmt->bind_param('i', $doctor_id);
    $appointments = fetch_all($stmt);

    $today_count = count_table($conn, "SELECT COUNT(*) FROM appointments WHERE doctor_id = $doctor_id AND appointment_date = CURDATE()");
    $pending_count = count_table($conn, "SELECT COUNT(*) FROM appointments WHERE doctor_id = $doctor_id AND status = 'Pending'");
    $approved_count = count_table($conn, "SELECT COUNT(*) FROM appointments WHERE doctor_id = $doctor_id AND status = 'Approved'");
    $completed_count = count_table($conn, "SELECT COUNT(*) FROM appointments WHERE doctor_id = $doctor_id AND status = 'Completed'");
} else {
    $appointments = [];
    $today_count = $pending_count = $approved_count = $completed_count = 0;
}
?>
<div class="patient-head">
    <div>
        <h1>My Appointments</h1>
        <p>Review appointment requests, approve or reject bookings, and mark completed visits.</p>
    </div>
    <a class="add-patient-btn" href="schedule.php"><i class="bi bi-clock"></i>Manage Schedule</a>
</div>

<?php if (!$doctor_id): ?>
    <div class="alert alert-warning">No doctor profile is linked to this user account.</div>
<?php endif; ?>

<section class="patient-management-card">
    <div class="patient-tabs">
        <div class="tab-links">
            <a class="<?= $filter === 'all' ? 'active' : '' ?>" href="view.php">All Appointments</a>
            <a class="<?= $filter === 'pending' ? 'active' : '' ?>" href="view.php?filter=pending">Pending Approvals</a>
            <a class="<?= $filter === 'today' ? 'active' : '' ?>" href="view.php?filter=today">Today's Schedule</a>
            <a class="<?= $filter === 'upcoming' ? 'active' : '' ?>" href="view.php?filter=upcoming">Upcoming</a>
            <a class="<?= $filter === 'history' ? 'active' : '' ?>" href="view.php?filter=history">History</a>
            <a href="schedule.php">Schedule</a>
        </div>
    </div>
    <div class="patient-metrics p-4 pb-0">
        <article class="patient-metric"><div><p>Today's Appointments</p><strong><?= number_format($today_count) ?></strong></div><span class="metric-icon blue"><i class="bi bi-calendar-day"></i></span></article>
        <article class="patient-metric"><div><p>Pending Approvals</p><strong><?= number_format($pending_count) ?></strong></div><span class="metric-icon red"><i class="bi bi-hourglass-split"></i></span></article>
        <article class="patient-metric"><div><p>Approved Appointments</p><strong><?= number_format($approved_count) ?></strong></div><span class="metric-icon mint"><i class="bi bi-check2-circle"></i></span></article>
        <article class="patient-metric"><div><p>Completed Appointments</p><strong><?= number_format($completed_count) ?></strong></div><span class="metric-icon blue"><i class="bi bi-clipboard-check"></i></span></article>
    </div>
    <div class="patient-list-grid patient-list-head" style="grid-template-columns: 1.25fr 1fr 1.3fr .8fr 1.4fr;">
        <span>Patient</span>
        <span>Date / Time</span>
        <span>Reason</span>
        <span>Status</span>
        <span>Action</span>
    </div>
    <?php foreach ($appointments as $appointment): ?>
        <div class="patient-list-grid patient-list-row" style="grid-template-columns: 1.25fr 1fr 1.3fr .8fr 1.4fr;">
            <span class="patient-list-name"><span class="patient-avatar blue-avatar"><?= e(substr($appointment['full_name'], 0, 2)) ?></span><span><strong><?= e($appointment['full_name']) ?></strong><small><?= e($appointment['phone']) ?></small></span></span>
            <span><?= e(date('M j, Y', strtotime($appointment['appointment_date']))) ?><small class="d-block text-muted"><?= e(substr($appointment['appointment_time'], 0, 5)) ?></small></span>
            <span><?= e($appointment['reason']) ?><small class="d-block text-muted"><?= e($appointment['medical_notes'] ? substr($appointment['medical_notes'], 0, 70) . '...' : 'No medical notes') ?></small></span>
            <span><em class="status-pill <?= $appointment['status'] === 'Completed' || $appointment['status'] === 'Approved' ? 'active' : 'inactive' ?>"><?= e($appointment['status']) ?></em></span>
            <span>
                <form method="post" class="d-grid gap-2">
                    <input type="hidden" name="appointment_id" value="<?= $appointment['id'] ?>">
                    <textarea class="form-control form-control-sm" name="remarks" rows="2" placeholder="Doctor remarks"><?= e($appointment['remarks'] ?? '') ?></textarea>
                    <div class="d-flex flex-wrap gap-2">
                        <button class="btn btn-sm btn-success" name="status" value="Approved">Approve</button>
                        <button class="btn btn-sm btn-outline-danger" name="status" value="Rejected">Reject</button>
                        <button class="btn btn-sm btn-primary" name="status" value="Completed">Complete</button>
                    </div>
                </form>
            </span>
        </div>
    <?php endforeach; ?>
    <?php if (!$appointments): ?>
        <div class="empty-state">No appointments assigned to you yet.</div>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
