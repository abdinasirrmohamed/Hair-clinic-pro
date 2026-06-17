<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';
require_roles(['Administrator', 'Receptionist']);
$page_title = 'Appointment Reminders';
require_once __DIR__ . '/../includes/header.php';

// Handle Dispatch
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dispatch'])) {
    $dispatch_ids = $_POST['appointment_ids'] ?? [];
    $success_count = 0;
    
    foreach ($dispatch_ids as $id) {
        $stmt = $conn->prepare("SELECT a.*, p.full_name, p.phone, p.email, d.full_name as doctor_name 
                                FROM appointments a 
                                JOIN patients p ON p.id = a.patient_id 
                                LEFT JOIN doctors d ON d.id = a.doctor_id 
                                WHERE a.id = ? AND a.reminder_sent = 0 AND a.status IN ('Pending', 'Approved')");
        $stmt->bind_param('i', $id);
        $appt = fetch_one($stmt);
        
        if ($appt) {
            // Simulate sending SMS / Email
            $message = "Reminder: You have an appointment at Hair Clinic Pro on " . date('M j, Y', strtotime($appt['appointment_date'])) . " at " . substr($appt['appointment_time'], 0, 5) . " with " . ($appt['doctor_name'] ?: 'our specialist') . ".";
            
            if (!empty($appt['email'])) {
                send_mock_email($appt['email'], "Appointment Reminder", $message);
            }
            if (!empty($appt['phone'])) {
                send_mock_sms($appt['phone'], $message);
            }
            
            // Mark as sent
            $update = $conn->prepare("UPDATE appointments SET reminder_sent = 1 WHERE id = ?");
            $update->bind_param('i', $id);
            $update->execute();
            $success_count++;
            
            log_activity('Sent appointment reminder', 'Appointments', $id);
        }
    }
    
    flash('success', "$success_count reminders dispatched successfully via Email/SMS.");
    redirect('/appointments/reminders.php');
}

// Fetch upcoming un-reminded appointments (next 7 days)
$stmt = $conn->prepare("
    SELECT a.*, p.full_name, p.phone, p.email, d.full_name as doctor_name 
    FROM appointments a 
    JOIN patients p ON p.id = a.patient_id 
    LEFT JOIN doctors d ON d.id = a.doctor_id 
    WHERE a.reminder_sent = 0 
      AND a.status IN ('Pending', 'Approved') 
      AND a.appointment_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
");
$stmt->execute();
$upcoming = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch recently reminded appointments
$recent = $conn->query("
    SELECT a.*, p.full_name 
    FROM appointments a 
    JOIN patients p ON p.id = a.patient_id 
    WHERE a.reminder_sent = 1 
    ORDER BY a.appointment_date DESC 
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

?>
<div class="patient-head">
    <div>
        <h1>Automated Reminders</h1>
        <p>Send SMS and Email reminders to patients with upcoming appointments.</p>
    </div>
    <a class="add-patient-btn" href="view.php"><i class="bi bi-arrow-left"></i> Back to Appointments</a>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <section class="patient-management-card">
            <div class="p-4 border-bottom d-flex justify-content-between align-items-center">
                <h2 class="h5 mb-0 text-primary"><i class="bi bi-send-check me-2"></i>Upcoming Unreminded Appointments</h2>
            </div>
            
            <form method="post" id="dispatchForm" class="p-4">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selectAll" class="form-check-input" checked></th>
                                <th>Patient</th>
                                <th>Contact</th>
                                <th>Date & Time</th>
                                <th>Doctor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($upcoming as $u): ?>
                                <tr>
                                    <td><input type="checkbox" name="appointment_ids[]" value="<?= $u['id'] ?>" class="form-check-input row-checkbox" checked></td>
                                    <td><strong><?= e($u['full_name']) ?></strong></td>
                                    <td>
                                        <small class="d-block text-muted"><i class="bi bi-telephone"></i> <?= e($u['phone']) ?></small>
                                        <?php if ($u['email']): ?>
                                            <small class="d-block text-muted"><i class="bi bi-envelope"></i> <?= e($u['email']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?= date('M j', strtotime($u['appointment_date'])) ?></strong><br>
                                        <small class="text-muted"><?= substr($u['appointment_time'], 0, 5) ?></small>
                                    </td>
                                    <td><?= e($u['doctor_name'] ?: '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$upcoming): ?>
                                <tr><td colspan="5"><div class="empty-state">No pending reminders for the next 7 days.</div></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($upcoming): ?>
                <div class="mt-3">
                    <button type="submit" name="dispatch" class="btn btn-primary" onclick="return confirm('Send reminders to selected patients?')">
                        <i class="bi bi-envelope-paper"></i> Dispatch Selected Reminders
                    </button>
                </div>
                <?php endif; ?>
            </form>
        </section>
    </div>
    
    <div class="col-lg-4">
        <section class="patient-management-card h-100">
            <div class="p-4 border-bottom">
                <h2 class="h5 mb-0"><i class="bi bi-clock-history me-2"></i>Recently Reminded</h2>
            </div>
            <div class="p-4 pt-0">
                <ul class="list-group list-group-flush mt-2">
                    <?php foreach ($recent as $r): ?>
                        <li class="list-group-item px-0 py-3">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1 fw-bold"><?= e($r['full_name']) ?></h6>
                                <small class="text-success"><i class="bi bi-check-circle-fill"></i> Sent</small>
                            </div>
                            <p class="mb-1 text-muted small">Appt: <?= date('M j, Y', strtotime($r['appointment_date'])) ?> at <?= substr($r['appointment_time'], 0, 5) ?></p>
                        </li>
                    <?php endforeach; ?>
                    <?php if (!$recent): ?>
                        <li class="list-group-item px-0 text-muted">No recent reminders.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </section>
    </div>
</div>

<script>
document.getElementById('selectAll').addEventListener('change', function() {
    var checkboxes = document.querySelectorAll('.row-checkbox');
    for (var checkbox of checkboxes) {
        checkbox.checked = this.checked;
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
