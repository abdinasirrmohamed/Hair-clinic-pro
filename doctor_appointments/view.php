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

    $stmt = $conn->prepare('UPDATE appointments SET status = ?, remarks = ? WHERE id = ? AND doctor_id = ?');
    $stmt->bind_param('ssii', $status, $remarks, $appointment_id, $doctor_id);
    $stmt->execute();
    flash('success', 'Appointment updated successfully.');
    redirect('/doctor_appointments/view.php');
}

if ($doctor_id > 0) {
    $stmt = $conn->prepare(
        'SELECT a.*, p.full_name, p.phone, p.medical_notes
         FROM appointments a
         JOIN patients p ON p.id = a.patient_id
         WHERE a.doctor_id = ?
         ORDER BY a.appointment_date DESC, a.appointment_time DESC'
    );
    $stmt->bind_param('i', $doctor_id);
    $appointments = fetch_all($stmt);
} else {
    $appointments = [];
}
?>
<div class="patient-head">
    <div>
        <h1>My Appointments</h1>
        <p>Review appointment requests, approve or reject bookings, and mark completed visits.</p>
    </div>
</div>

<?php if (!$doctor_id): ?>
    <div class="alert alert-warning">No doctor profile is linked to this user account.</div>
<?php endif; ?>

<section class="patient-management-card">
    <div class="patient-tabs">
        <div class="tab-links">
            <a class="active" href="view.php">All Appointments</a>
        </div>
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
