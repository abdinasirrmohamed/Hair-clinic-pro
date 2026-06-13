<?php
$page_title = 'Doctor Profile';
require_once __DIR__ . '/../includes/header.php';

$id = (int) ($_GET['id'] ?? 0);
$stmt = $conn->prepare('SELECT d.*, u.username FROM doctors d LEFT JOIN users u ON u.id = d.user_id WHERE d.id = ?');
$stmt->bind_param('i', $id);
$doctor = fetch_one($stmt);
if (!$doctor) {
    flash('danger', 'Doctor not found.');
    redirect('/doctors/view.php');
}

$stmt = $conn->prepare(
    'SELECT a.*, p.full_name FROM appointments a JOIN patients p ON p.id = a.patient_id WHERE a.doctor_id = ? ORDER BY a.appointment_date DESC LIMIT 8'
);
$stmt->bind_param('i', $id);
$appointments = fetch_all($stmt);
?>
<div class="profile-hero-card">
    <div class="profile-photo">
        <?php if (!empty($doctor['photo'])): ?>
            <img src="<?= BASE_URL ?>/<?= e($doctor['photo']) ?>" alt="<?= e($doctor['full_name']) ?>" style="width:100%;height:100%;object-fit:cover;border-radius:8px;">
        <?php else: ?>
            <span><?= e(substr($doctor['full_name'], 0, 2)) ?></span>
        <?php endif; ?>
        <i class="bi bi-person-badge"></i>
    </div>
    <div class="profile-identity">
        <h1><?= e($doctor['full_name']) ?></h1>
        <span class="patient-code"><?= e($doctor['license_number']) ?></span>
        <div class="profile-facts">
            <span><i class="bi bi-award"></i><?= e($doctor['specialization']) ?></span>
            <span><i class="bi bi-mortarboard"></i><?= e($doctor['qualification'] ?: 'Qualification N/A') ?></span>
            <span><i class="bi bi-clock-history"></i><?= (int) $doctor['experience_years'] ?> Years</span>
        </div>
        <p class="mb-0 text-muted"><?= e($doctor['bio'] ?: 'No profile biography saved.') ?></p>
    </div>
    <div class="profile-actions">
        <a class="edit-profile-btn" href="edit.php?id=<?= $doctor['id'] ?>"><i class="bi bi-pencil"></i><span>Edit<br>Doctor</span></a>
    </div>
</div>

<div class="profile-grid">
    <section class="profile-card appointment-history-card">
        <div class="profile-card-head"><h2>Appointment History</h2></div>
        <div class="profile-appointment-head"><span>Date & Time</span><span>Patient</span><span>Reason</span><span>Status</span></div>
        <?php foreach ($appointments as $appointment): ?>
            <div class="profile-appointment-row">
                <time><?= e(date('M j, Y', strtotime($appointment['appointment_date']))) ?><small><?= e(substr($appointment['appointment_time'], 0, 5)) ?></small></time>
                <strong><?= e($appointment['full_name']) ?></strong>
                <strong><?= e($appointment['reason']) ?></strong>
                <em class="profile-status <?= $appointment['status'] === 'Completed' ? 'completed' : ($appointment['status'] === 'Rejected' ? 'cancelled' : 'upcoming') ?>"><?= e($appointment['status']) ?></em>
            </div>
        <?php endforeach; ?>
        <?php if (!$appointments): ?><div class="empty-state">No appointment history.</div><?php endif; ?>
    </section>
    <aside class="profile-side-card">
        <h2><i class="bi bi-calendar-week"></i>Availability</h2>
        <div class="side-divider"></div>
        <p><?= nl2br(e($doctor['availability_schedule'] ?: 'No availability schedule saved.')) ?></p>
        <div class="side-divider mt-4"></div>
        <h3>Contact</h3>
        <p><strong><?= e($doctor['phone']) ?></strong><br><?= e($doctor['email'] ?: 'No email saved') ?><br>Login: <?= e($doctor['username'] ?: 'Not linked') ?></p>
    </aside>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
