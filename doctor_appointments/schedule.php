<?php
require_once __DIR__ . '/../includes/auth.php';
require_access('doctor_appointments');
$page_title = 'Doctor Schedule';
require_once __DIR__ . '/../includes/header.php';

$user_id = (int) ($_SESSION['admin_id'] ?? 0);
$doctor = $conn->query("SELECT * FROM doctors WHERE user_id = $user_id LIMIT 1")->fetch_assoc();
$doctor_id = (int) ($doctor['id'] ?? 0);
$days = ['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $doctor_id > 0) {
    if (($_POST['form_type'] ?? '') === 'schedule') {
        foreach ($days as $day) {
            $is_working = isset($_POST['working_days'][$day]) ? 1 : 0;
            $start = $_POST['start_time'][$day] ?? '08:00';
            $end = $_POST['end_time'][$day] ?? '16:00';
            $slot = max(10, (int) ($_POST['slot_minutes'][$day] ?? 30));
            $stmt = $conn->prepare('INSERT INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time, slot_minutes, is_working) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE start_time = VALUES(start_time), end_time = VALUES(end_time), slot_minutes = VALUES(slot_minutes), is_working = VALUES(is_working)');
            $stmt->bind_param('isssii', $doctor_id, $day, $start, $end, $slot, $is_working);
            $stmt->execute();
        }
        log_activity('Updated doctor schedule', 'Doctor Schedule', $doctor_id);
        flash('success', 'Schedule updated successfully.');
    }

    if (($_POST['form_type'] ?? '') === 'block_date') {
        $block_date = $_POST['block_date'] ?? '';
        $block_type = $_POST['block_type'] ?? 'Blocked';
        $reason = trim($_POST['reason'] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $block_date) && in_array($block_type, ['Leave', 'Blocked'], true)) {
            $stmt = $conn->prepare('INSERT INTO doctor_blocked_dates (doctor_id, block_date, block_type, reason) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE block_type = VALUES(block_type), reason = VALUES(reason)');
            $stmt->bind_param('isss', $doctor_id, $block_date, $block_type, $reason);
            $stmt->execute();
            log_activity('Blocked doctor date', 'Doctor Schedule', $block_date);
            flash('success', 'Unavailable date saved.');
        }
    }

    if (($_POST['form_type'] ?? '') === 'remove_block') {
        $block_id = (int) ($_POST['block_id'] ?? 0);
        $stmt = $conn->prepare('DELETE FROM doctor_blocked_dates WHERE id = ? AND doctor_id = ?');
        $stmt->bind_param('ii', $block_id, $doctor_id);
        $stmt->execute();
        log_activity('Removed blocked doctor date', 'Doctor Schedule', $block_id);
        flash('success', 'Blocked date removed.');
    }

    redirect('/doctor_appointments/schedule.php');
}

$schedule_by_day = [];
if ($doctor_id > 0) {
    $stmt = $conn->prepare('SELECT * FROM doctor_schedules WHERE doctor_id = ? ORDER BY FIELD(day_of_week, "Saturday","Sunday","Monday","Tuesday","Wednesday","Thursday","Friday")');
    $stmt->bind_param('i', $doctor_id);
    foreach (fetch_all($stmt) as $row) {
        $schedule_by_day[$row['day_of_week']] = $row;
    }

    $stmt = $conn->prepare('SELECT * FROM doctor_blocked_dates WHERE doctor_id = ? AND block_date >= CURDATE() ORDER BY block_date ASC LIMIT 20');
    $stmt->bind_param('i', $doctor_id);
    $blocked_dates = fetch_all($stmt);

    $stmt = $conn->prepare("SELECT a.*, p.full_name FROM appointments a JOIN patients p ON p.id = a.patient_id WHERE a.doctor_id = ? AND a.appointment_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND a.status IN ('Pending','Approved') ORDER BY a.appointment_date, a.appointment_time");
    $stmt->bind_param('i', $doctor_id);
    $monthly_appointments = fetch_all($stmt);
} else {
    $blocked_dates = [];
    $monthly_appointments = [];
}
?>
<div class="patient-head">
    <div>
        <h1>Doctor Schedule</h1>
        <p>Manage working days, time slots, leave days, and blocked dates.</p>
    </div>
    <a class="add-patient-btn" href="view.php"><i class="bi bi-calendar-check"></i>My Appointments</a>
</div>

<?php if (!$doctor_id): ?>
    <div class="alert alert-warning">No doctor profile is linked to this user account.</div>
<?php else: ?>
<section class="patient-management-card">
    <div class="patient-tabs"><div class="tab-links"><a href="view.php">My Appointments</a><a class="active" href="schedule.php">Schedule</a></div></div>
    <form class="p-4" method="post">
        <input type="hidden" name="form_type" value="schedule">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Day</th><th>Working</th><th>Start</th><th>End</th><th>Slot Minutes</th></tr></thead>
                <tbody>
                    <?php foreach ($days as $day): ?>
                        <?php $row = $schedule_by_day[$day] ?? ['start_time' => '08:00:00', 'end_time' => '16:00:00', 'slot_minutes' => 30, 'is_working' => in_array($day, ['Saturday','Sunday','Monday','Tuesday','Wednesday'], true) ? 1 : 0]; ?>
                        <tr>
                            <td><strong><?= e($day) ?></strong></td>
                            <td><input class="form-check-input" type="checkbox" name="working_days[<?= e($day) ?>]" <?= (int) $row['is_working'] === 1 ? 'checked' : '' ?>></td>
                            <td><input class="form-control" type="time" name="start_time[<?= e($day) ?>]" value="<?= e(substr($row['start_time'], 0, 5)) ?>"></td>
                            <td><input class="form-control" type="time" name="end_time[<?= e($day) ?>]" value="<?= e(substr($row['end_time'], 0, 5)) ?>"></td>
                            <td><input class="form-control" type="number" min="10" step="5" name="slot_minutes[<?= e($day) ?>]" value="<?= e($row['slot_minutes']) ?>"></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <button class="btn btn-primary"><i class="bi bi-save"></i>Save Schedule</button>
    </form>
</section>

<div class="row g-4 mt-1">
    <div class="col-lg-5">
        <section class="form-panel h-100">
            <h2 class="h5 mb-3">Leave or Blocked Date</h2>
            <form method="post" class="row g-3">
                <input type="hidden" name="form_type" value="block_date">
                <div class="col-md-6"><label class="form-label">Date</label><input class="form-control" type="date" name="block_date" required></div>
                <div class="col-md-6"><label class="form-label">Type</label><select class="form-select" name="block_type"><option>Leave</option><option>Blocked</option></select></div>
                <div class="col-12"><label class="form-label">Reason</label><input class="form-control" name="reason" placeholder="Conference, emergency, maintenance..."></div>
                <div class="col-12"><button class="btn btn-outline-primary">Save Date</button></div>
            </form>
            <hr>
            <?php foreach ($blocked_dates as $block): ?>
                <form method="post" class="d-flex justify-content-between align-items-center border-bottom py-2">
                    <input type="hidden" name="form_type" value="remove_block">
                    <input type="hidden" name="block_id" value="<?= $block['id'] ?>">
                    <span><strong><?= e(date('M j, Y', strtotime($block['block_date']))) ?></strong><small class="d-block text-muted"><?= e($block['block_type']) ?> - <?= e($block['reason'] ?: 'No reason') ?></small></span>
                    <button class="btn btn-sm btn-outline-danger">Remove</button>
                </form>
            <?php endforeach; ?>
            <?php if (!$blocked_dates): ?><div class="empty-state">No upcoming blocked dates.</div><?php endif; ?>
        </section>
    </div>
    <div class="col-lg-7">
        <section class="form-panel h-100">
            <h2 class="h5 mb-3">Next 30 Days</h2>
            <?php foreach ($monthly_appointments as $appt): ?>
                <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                    <span><strong><?= e($appt['full_name']) ?></strong><small class="d-block text-muted"><?= e($appt['reason']) ?></small></span>
                    <span class="text-end"><?= e(date('M j', strtotime($appt['appointment_date']))) ?><small class="d-block text-muted"><?= e(substr($appt['appointment_time'], 0, 5)) ?> - <?= e($appt['status']) ?></small></span>
                </div>
            <?php endforeach; ?>
            <?php if (!$monthly_appointments): ?><div class="empty-state">No upcoming scheduled appointments.</div><?php endif; ?>
        </section>
    </div>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
