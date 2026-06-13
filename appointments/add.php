<?php
require_once __DIR__ . '/../includes/auth.php';
require_roles(['Administrator', 'Receptionist']);
$page_title = 'Book Appointment';
require_once __DIR__ . '/../includes/header.php';
$patients = $conn->query('SELECT id, full_name FROM patients ORDER BY full_name')->fetch_all(MYSQLI_ASSOC);
$prefill_date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'] ?? '') ? $_GET['date'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_mode = $_POST['patient_mode'] ?? 'existing';
    $patient_id = (int) ($_POST['patient_id'] ?? 0);
    $appointment_date = $_POST['appointment_date'] ?? '';
    $appointment_time = $_POST['appointment_time'] ?? '';
    $reason = trim($_POST['reason'] ?? '');
    $status = $_POST['status'] ?? 'Pending';
    $appointment_notes = trim($_POST['notes'] ?? '');

    try {
        $conn->begin_transaction();

        if ($patient_mode === 'new') {
            $full_name = trim($_POST['new_full_name'] ?? '');
            $phone = trim($_POST['new_phone'] ?? '');
            $email = trim($_POST['new_email'] ?? '');
            $gender = $_POST['new_gender'] ?? '';
            $dob = $_POST['new_date_of_birth'] ?: null;
            $address = trim($_POST['new_address'] ?? '');
            $medical_notes = trim($_POST['new_medical_notes'] ?? '');

            if ($full_name === '' || $phone === '' || $gender === '') {
                throw new Exception('Please enter the new patient name, phone, and gender.');
            }

            $stmt = $conn->prepare('INSERT INTO patients (full_name, phone, email, gender, date_of_birth, address, medical_notes) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('sssssss', $full_name, $phone, $email, $gender, $dob, $address, $medical_notes);
            $stmt->execute();
            $patient_id = $conn->insert_id;
        }

        if ($patient_id <= 0 || $appointment_date === '' || $appointment_time === '' || $reason === '') {
            throw new Exception('Please complete patient, date, time, and reason fields.');
        }

        $stmt = $conn->prepare('INSERT INTO appointments (patient_id, appointment_date, appointment_time, reason, status, notes) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('isssss', $patient_id, $appointment_date, $appointment_time, $reason, $status, $appointment_notes);
        $stmt->execute();

        $conn->commit();
        flash('success', $patient_mode === 'new' ? 'New patient registered and appointment booked successfully.' : 'Appointment booked successfully.');
        redirect('/appointments/view.php');
    } catch (Throwable $e) {
        $conn->rollback();
        flash('danger', $e->getMessage());
        redirect('/appointments/add.php');
    }
}
?>
<div class="page-head"><h1 class="h3 mb-0">Book Appointment</h1></div>
<form class="form-panel" method="post">
    <div class="row g-3">
        <div class="col-12">
            <label class="form-label">Patient Type</label>
            <div class="inline-choice">
                <label><input type="radio" name="patient_mode" value="existing" checked> Existing Patient</label>
                <label><input type="radio" name="patient_mode" value="new"> Register New Patient</label>
            </div>
        </div>
        <div class="col-md-6 existing-patient-field"><label class="form-label">Existing Patient</label><select class="form-select" name="patient_id" id="patient_id"><option value="">Select patient</option><?php foreach ($patients as $p): ?><option value="<?= $p['id'] ?>"><?= e($p['full_name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-12 new-patient-panel d-none">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">New Patient Name</label><input class="form-control new-patient-required" name="new_full_name"></div>
                <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control new-patient-required" name="new_phone"></div>
                <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" type="email" name="new_email"></div>
                <div class="col-md-3"><label class="form-label">Gender</label><select class="form-select new-patient-required" name="new_gender"><option value="">Select gender</option><option>Male</option><option>Female</option><option>Other</option></select></div>
                <div class="col-md-3"><label class="form-label">Date of Birth</label><input class="form-control" type="date" name="new_date_of_birth"></div>
                <div class="col-12"><label class="form-label">Address</label><input class="form-control" name="new_address"></div>
                <div class="col-12"><label class="form-label">Medical Notes</label><textarea class="form-control" name="new_medical_notes" rows="3"></textarea></div>
            </div>
        </div>
        <div class="col-md-3"><label class="form-label">Date</label><input class="form-control" type="date" name="appointment_date" value="<?= e($prefill_date) ?>" required></div>
        <div class="col-md-3"><label class="form-label">Time</label><input class="form-control" type="time" name="appointment_time" required></div>
        <div class="col-md-8"><label class="form-label">Reason</label><input class="form-control" name="reason" required></div>
        <div class="col-md-4"><label class="form-label">Status</label><select class="form-select" name="status"><option>Pending</option><option>Completed</option><option>Cancelled</option></select></div>
        <div class="col-12"><label class="form-label">Notes</label><textarea class="form-control" name="notes" rows="4"></textarea></div>
    </div>
    <div class="mt-4"><button class="btn btn-primary">Save Appointment</button> <a class="btn btn-secondary" href="view.php">Cancel</a></div>
</form>
<script>
document.querySelectorAll('input[name="patient_mode"]').forEach((input) => {
    input.addEventListener('change', () => {
        const isNew = document.querySelector('input[name="patient_mode"]:checked').value === 'new';
        document.querySelector('.new-patient-panel').classList.toggle('d-none', !isNew);
        document.querySelector('.existing-patient-field').classList.toggle('d-none', isNew);
        document.getElementById('patient_id').required = !isNew;
        document.querySelectorAll('.new-patient-required').forEach((field) => field.required = isNew);
    });
});
document.querySelector('input[name="patient_mode"]:checked').dispatchEvent(new Event('change'));
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
