<?php
require_once __DIR__ . '/../includes/auth.php';
require_access('doctor_appointments');
$page_title = 'My Appointments';
require_once __DIR__ . '/../includes/header.php';

$user_id   = (int) ($_SESSION['admin_id'] ?? 0);
$doctor    = $conn->query("SELECT * FROM doctors WHERE user_id = $user_id LIMIT 1")->fetch_assoc();
$doctor_id = (int) ($doctor['id'] ?? 0);

// â”€â”€ Handle POST actions â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action         = $_POST['action'] ?? '';
    $appointment_id = (int) ($_POST['appointment_id'] ?? 0);

    if ($action === 'status') {
        $new_status = $_POST['status'] ?? '';
        $remarks    = trim($_POST['remarks'] ?? '');
        if (!in_array($new_status, ['Approved', 'Rejected', 'Completed'], true)) {
            flash('danger', 'Invalid action.');
            redirect('/doctor_appointments/view.php');
        }
        $col  = $new_status === 'Approved' ? 'approved_at' : ($new_status === 'Rejected' ? 'rejected_at' : 'completed_at');
        $stmt = $conn->prepare("UPDATE appointments SET status=?, remarks=?, `$col`=NOW() WHERE id=? AND doctor_id=?");
        $stmt->bind_param('ssii', $new_status, $remarks, $appointment_id, $doctor_id);
        $stmt->execute();
        log_activity("$new_status appointment", 'Appointments', $appointment_id);
        flash('success', "Appointment marked as $new_status.");
        redirect('/doctor_appointments/view.php');
    }

    if ($action === 'cancel') {
        $stmt = $conn->prepare("UPDATE appointments SET status='Cancelled' WHERE id=? AND doctor_id=?");
        $stmt->bind_param('ii', $appointment_id, $doctor_id);
        $stmt->execute();
        log_activity('Cancelled appointment', 'Appointments', $appointment_id);
        flash('success', 'Appointment cancelled.');
        redirect('/doctor_appointments/view.php');
    }

    if ($action === 'create' && $doctor_id > 0) {
        $patient_id = (int) ($_POST['patient_id'] ?? 0);
        $appt_date  = $_POST['appointment_date'] ?? '';
        $appt_time  = $_POST['appointment_time'] ?? '';
        $reason     = trim($_POST['reason'] ?? '');
        $notes      = trim($_POST['notes'] ?? '');
        if (!$patient_id || !$appt_date || !$appt_time || !$reason) {
            flash('danger', 'Patient, date, time, and reason are required.');
            redirect('/doctor_appointments/view.php?modal=new');
        }
        $stmt = $conn->prepare('INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, reason, notes, status) VALUES (?,?,?,?,?,?,?)');
        $status_pending = 'Approved';
        $stmt->bind_param('iisssss', $patient_id, $doctor_id, $appt_date, $appt_time, $reason, $notes, $status_pending);
        $stmt->execute();
        log_activity('Created appointment', 'Appointments', $conn->insert_id);
        flash('success', 'Appointment booked successfully.');
        redirect('/doctor_appointments/view.php');
    }

    if ($action === 'edit' && $doctor_id > 0) {
        $appt_date = $_POST['appointment_date'] ?? '';
        $appt_time = $_POST['appointment_time'] ?? '';
        $reason    = trim($_POST['reason'] ?? '');
        $notes     = trim($_POST['notes'] ?? '');
        $stmt      = $conn->prepare('UPDATE appointments SET appointment_date=?, appointment_time=?, reason=?, notes=? WHERE id=? AND doctor_id=?');
        $stmt->bind_param('ssssii', $appt_date, $appt_time, $reason, $notes, $appointment_id, $doctor_id);
        $stmt->execute();
        log_activity('Edited appointment', 'Appointments', $appointment_id);
        flash('success', 'Appointment updated.');
        redirect('/doctor_appointments/view.php');
    }

    redirect('/doctor_appointments/view.php');
}

// â”€â”€ Fetch data â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
$filter       = $_GET['filter'] ?? 'all';
$open_modal   = $_GET['modal']  ?? '';
$where_filter = '';
if ($filter === 'pending')  $where_filter = " AND a.status='Pending'";
elseif ($filter === 'today') $where_filter = ' AND a.appointment_date=CURDATE()';
elseif ($filter === 'upcoming') $where_filter = " AND a.appointment_date>=CURDATE() AND a.status IN ('Pending','Approved')";
elseif ($filter === 'history') $where_filter = " AND (a.appointment_date<CURDATE() OR a.status IN ('Completed','Rejected','Cancelled'))";

if ($doctor_id > 0) {
    $stmt = $conn->prepare(
        'SELECT a.*, p.full_name, p.phone, p.medical_notes
         FROM appointments a
         JOIN patients p ON p.id = a.patient_id
         WHERE a.doctor_id = ?' . $where_filter . '
         ORDER BY a.appointment_date DESC, a.appointment_time DESC'
    );
    $stmt->bind_param('i', $doctor_id);
    $appointments = fetch_all($stmt);

    $today_count     = count_table($conn, "SELECT COUNT(*) FROM appointments WHERE doctor_id=$doctor_id AND appointment_date=CURDATE()");
    $pending_count   = count_table($conn, "SELECT COUNT(*) FROM appointments WHERE doctor_id=$doctor_id AND status='Pending'");
    $approved_count  = count_table($conn, "SELECT COUNT(*) FROM appointments WHERE doctor_id=$doctor_id AND status='Approved'");
    $completed_count = count_table($conn, "SELECT COUNT(*) FROM appointments WHERE doctor_id=$doctor_id AND status='Completed'");

    // Patients assigned to this doctor (for new appointment form)
    $my_patients = $conn->query(
        "SELECT id, full_name, phone FROM patients WHERE assigned_doctor_id=$doctor_id ORDER BY full_name"
    )->fetch_all(MYSQLI_ASSOC);
} else {
    $appointments = $my_patients = [];
    $today_count = $pending_count = $approved_count = $completed_count = 0;
}

function status_color(string $s): string {
    return match($s) {
        'Approved'  => 'success',
        'Completed' => 'primary',
        'Rejected', 'Cancelled' => 'danger',
        default     => 'warning',
    };
}
?>

<div class="patient-head">
    <div>
        <h1>My Appointments</h1>
        <p>Manage bookings, approve requests, and mark completed visits.</p>
    </div>
    <div class="d-flex gap-2">
        <?php if ($doctor_id > 0): ?>
        <button class="add-patient-btn btn btn-success" data-bs-toggle="modal" data-bs-target="#newApptModal">
            <i class="bi bi-plus-circle"></i> New Appointment
        </button>
        <?php endif; ?>
        <a class="add-patient-btn" href="schedule.php"><i class="bi bi-clock"></i> Manage Schedule</a>
    </div>
</div>

<?php show_flash(); ?>

<?php if (!$doctor_id): ?>
    <div class="alert alert-warning d-flex align-items-center gap-2">
        <i class="bi bi-exclamation-triangle-fill fs-5"></i>
        <span>No doctor profile is linked to your account.
        <?php if (current_role() === 'Administrator'): ?>
            <a href="<?= BASE_URL ?>/doctors/add.php" class="alert-link">Create a doctor profile</a> and link it to this user to use this view.
        <?php endif; ?>
        </span>
    </div>
<?php endif; ?>

<section class="patient-management-card">
    <div class="patient-tabs">
        <div class="tab-links">
            <a class="<?= $filter === 'all'      ? 'active' : '' ?>" href="view.php">All</a>
            <a class="<?= $filter === 'pending'  ? 'active' : '' ?>" href="view.php?filter=pending">Pending <span class="badge bg-warning text-dark ms-1"><?= $pending_count ?></span></a>
            <a class="<?= $filter === 'today'    ? 'active' : '' ?>" href="view.php?filter=today">Today <span class="badge bg-primary ms-1"><?= $today_count ?></span></a>
            <a class="<?= $filter === 'upcoming' ? 'active' : '' ?>" href="view.php?filter=upcoming">Upcoming</a>
            <a class="<?= $filter === 'history'  ? 'active' : '' ?>" href="view.php?filter=history">History</a>
            <a href="schedule.php">Schedule</a>
        </div>
    </div>

    <!-- Metrics -->
    <div class="patient-metrics p-4 pb-0">
        <article class="patient-metric"><div><p>Today</p><strong><?= $today_count ?></strong></div><span class="metric-icon blue"><i class="bi bi-calendar-day"></i></span></article>
        <article class="patient-metric"><div><p>Pending</p><strong><?= $pending_count ?></strong></div><span class="metric-icon red"><i class="bi bi-hourglass-split"></i></span></article>
        <article class="patient-metric"><div><p>Approved</p><strong><?= $approved_count ?></strong></div><span class="metric-icon mint"><i class="bi bi-check2-circle"></i></span></article>
        <article class="patient-metric"><div><p>Completed</p><strong><?= $completed_count ?></strong></div><span class="metric-icon blue"><i class="bi bi-clipboard-check"></i></span></article>
    </div>

    <!-- Appointment rows -->
    <div class="patient-list-grid patient-list-head mt-3" style="grid-template-columns:1.3fr 1fr 1.4fr .7fr 1.5fr;">
        <span>Patient</span><span>Date / Time</span><span>Reason</span><span>Status</span><span>Actions</span>
    </div>

    <?php foreach ($appointments as $a): ?>
        <div class="patient-list-grid patient-list-row" style="grid-template-columns:1.3fr 1fr 1.4fr .7fr 1.5fr; align-items:start; padding-top:.85rem; padding-bottom:.85rem;">
            <span class="patient-list-name">
                <span class="patient-avatar blue-avatar"><?= e(strtoupper(substr($a['full_name'], 0, 2))) ?></span>
                <span><strong><?= e($a['full_name']) ?></strong><small><?= e($a['phone']) ?></small></span>
            </span>
            <span>
                <?= e(date('M j, Y', strtotime($a['appointment_date']))) ?>
                <small class="d-block text-muted"><?= e(substr($a['appointment_time'], 0, 5)) ?></small>
            </span>
            <span>
                <?= e($a['reason']) ?>
                <?php if ($a['remarks']): ?>
                    <small class="d-block text-muted fst-italic"><?= e(substr($a['remarks'], 0, 60)) ?></small>
                <?php endif; ?>
            </span>
            <span><em class="status-pill <?= in_array($a['status'], ['Approved','Completed'], true) ? 'active' : 'inactive' ?>"><?= e($a['status']) ?></em></span>
            <span>
                <?php if (!in_array($a['status'], ['Completed','Cancelled'], true)): ?>
                <form method="post" class="d-grid gap-1 mb-2">
                    <input type="hidden" name="action" value="status">
                    <input type="hidden" name="appointment_id" value="<?= $a['id'] ?>">
                    <textarea class="form-control form-control-sm" name="remarks" rows="2" placeholder="Doctor remarks (optional)"><?= e($a['remarks'] ?? '') ?></textarea>
                    <div class="d-flex flex-wrap gap-1 mt-1">
                        <?php if ($a['status'] === 'Pending'): ?>
                            <button class="btn btn-sm btn-success" name="status" value="Approved"><i class="bi bi-check2"></i> Approve</button>
                            <button class="btn btn-sm btn-outline-danger" name="status" value="Rejected" onclick="return confirm('Reject this appointment?')"><i class="bi bi-x"></i> Reject</button>
                        <?php endif; ?>
                        <?php if ($a['status'] === 'Approved'): ?>
                            <button class="btn btn-sm btn-primary" name="status" value="Completed"><i class="bi bi-clipboard-check"></i> Complete</button>
                        <?php endif; ?>
                    </div>
                </form>
                <div class="d-flex gap-1">
                    <button class="btn btn-sm btn-outline-secondary"
                            onclick='openEdit(<?= $a["id"] ?>, <?= htmlspecialchars(json_encode($a["full_name"]), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($a["appointment_date"]), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode(substr($a["appointment_time"],0,5)), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($a["reason"]), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($a["notes"] ?? ""), ENT_QUOTES) ?>)'>
                        <i class="bi bi-pencil"></i> Edit
                    </button>
                    <form method="post" class="d-inline" onsubmit="return confirm('Cancel this appointment?')">
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="appointment_id" value="<?= $a['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-x-circle"></i> Cancel</button>
                    </form>
                </div>
                <?php else: ?>
                    <span class="text-muted small">No actions â€” <?= e($a['status']) ?></span>
                <?php endif; ?>
            </span>
        </div>
    <?php endforeach; ?>

    <?php if (!$appointments): ?>
        <div class="empty-state py-5 text-center">
            <i class="bi bi-calendar-x fs-1 d-block mb-2 text-muted"></i>
            No appointments found for this filter.
        </div>
    <?php endif; ?>
</section>

<!-- â”€â”€ New Appointment Modal â”€â”€ -->
<div class="modal fade" id="newApptModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-calendar-plus me-2"></i>Book New Appointment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Patient <span class="text-danger">*</span></label>
                        <select class="form-select" name="patient_id" required>
                            <option value="">â€” Select patient â€”</option>
                            <?php foreach ($my_patients as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= e($p['full_name']) ?> â€” <?= e($p['phone']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!$my_patients): ?>
                            <div class="form-text text-warning"><i class="bi bi-info-circle"></i> No patients are assigned to you yet. Ask admin to assign patients.</div>
                        <?php endif; ?>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label fw-bold">Date <span class="text-danger">*</span></label>
                            <input class="form-control" type="date" name="appointment_date" required min="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold">Time <span class="text-danger">*</span></label>
                            <input class="form-control" type="time" name="appointment_time" required>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label fw-bold">Reason <span class="text-danger">*</span></label>
                        <input class="form-control" type="text" name="reason" placeholder="e.g. Hair loss consultation" required>
                    </div>
                    <div class="mt-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="3" placeholder="Optional medical notes or instructions"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-calendar-check"></i> Book Appointment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- â”€â”€ Edit Appointment Modal â”€â”€ -->
<div class="modal fade" id="editApptModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="appointment_id" id="editApptId">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Edit Appointment â€” <span id="editPatientName"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label fw-bold">Date</label>
                            <input class="form-control" type="date" name="appointment_date" id="editDate" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold">Time</label>
                            <input class="form-control" type="time" name="appointment_time" id="editTime" required>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label fw-bold">Reason</label>
                        <input class="form-control" type="text" name="reason" id="editReason" required>
                    </div>
                    <div class="mt-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" id="editNotes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openEdit(id, name, date, time, reason, notes) {
    document.getElementById('editApptId').value     = id;
    document.getElementById('editPatientName').textContent = name;
    document.getElementById('editDate').value       = date;
    document.getElementById('editTime').value       = time;
    document.getElementById('editReason').value     = reason;
    document.getElementById('editNotes').value      = notes;
    new bootstrap.Modal(document.getElementById('editApptModal')).show();
}
<?php if ($open_modal === 'new'): ?>
document.addEventListener('DOMContentLoaded', function () {
    new bootstrap.Modal(document.getElementById('newApptModal')).show();
});
<?php endif; ?>
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

