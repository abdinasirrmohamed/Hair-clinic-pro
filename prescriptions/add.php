<?php
require_once __DIR__ . '/../includes/auth.php';
require_access('prescriptions');
$page_title = 'Create Prescription';

$doctor_id = 0;
if (current_role() === 'Doctor') {
    $stmt = $conn->prepare('SELECT id FROM doctors WHERE user_id = ? LIMIT 1');
    $admin_id = (int) ($_SESSION['admin_id'] ?? 0);
    $stmt->bind_param('i', $admin_id);
    $doctor = fetch_one($stmt);
    $doctor_id = (int) ($doctor['id'] ?? 0);
}

$patients = $conn->query('SELECT id, full_name, phone FROM patients ORDER BY full_name')->fetch_all(MYSQLI_ASSOC);
$doctors = $conn->query("SELECT id, full_name, specialization FROM doctors WHERE status = 'Active' ORDER BY full_name")->fetch_all(MYSQLI_ASSOC);
$medicines = $conn->query('SELECT id, medicine_name, quantity, unit_price FROM medicines ORDER BY medicine_name')->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = (int) ($_POST['patient_id'] ?? 0);
    $selected_doctor_id = current_role() === 'Doctor' ? $doctor_id : (int) ($_POST['doctor_id'] ?? 0);
    $instructions = trim($_POST['instructions'] ?? '');
    $medicine_ids = $_POST['medicine_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $line_instructions = $_POST['line_instructions'] ?? [];
    $transaction_started = false;

    try {
        if ($patient_id <= 0 || $selected_doctor_id <= 0) {
            throw new Exception('Patient and doctor are required.');
        }

        $items = [];
        foreach ($medicine_ids as $index => $medicine_id) {
            $medicine_id = (int) $medicine_id;
            $quantity = max(0, (int) ($quantities[$index] ?? 0));
            $line_note = trim($line_instructions[$index] ?? '');
            if ($medicine_id > 0 && $quantity > 0) {
                $items[] = [$medicine_id, $quantity, $line_note];
            }
        }
        if (!$items) {
            throw new Exception('Add at least one medicine to the prescription.');
        }

        $conn->begin_transaction();
        $transaction_started = true;
        $number = 'RX-' . date('Ymd') . '-' . random_int(1000, 9999);
        $date = date('Y-m-d');
        $stmt = $conn->prepare('INSERT INTO prescriptions (prescription_number, patient_id, doctor_id, prescription_date, instructions) VALUES (?, ?, ?, ?, ?)');
        $stmt->bind_param('siiss', $number, $patient_id, $selected_doctor_id, $date, $instructions);
        $stmt->execute();
        $prescription_id = $conn->insert_id;

        foreach ($items as $item) {
            $stmt = $conn->prepare('INSERT INTO prescription_medicines (prescription_id, medicine_id, quantity, instructions) VALUES (?, ?, ?, ?)');
            $stmt->bind_param('iiis', $prescription_id, $item[0], $item[1], $item[2]);
            $stmt->execute();
        }

        $conn->commit();
        flash('success', 'Prescription sent to pharmacy.');
        redirect('/prescriptions/view.php');
    } catch (Throwable $e) {
        if ($transaction_started) {
            $conn->rollback();
        }
        flash('danger', $e->getMessage());
        redirect('/prescriptions/add.php');
    }
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="patient-head">
    <div><h1>Create Prescription</h1><p>Send patient medication instructions directly to the pharmacy queue.</p></div>
    <a class="add-patient-btn" href="view.php"><i class="bi bi-list-ul"></i>Prescription History</a>
</div>

<section class="patient-management-card">
    <form method="post" class="p-4">
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label class="form-label">Patient</label>
                <select class="form-select" name="patient_id" required>
                    <option value="">Select patient</option>
                    <?php foreach ($patients as $patient): ?><option value="<?= (int) $patient['id'] ?>"><?= e($patient['full_name']) ?> - <?= e($patient['phone']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Doctor</label>
                <?php if (current_role() === 'Doctor'): ?>
                    <select class="form-select" disabled>
                        <?php foreach ($doctors as $doctor): ?><option <?= (int) $doctor['id'] === $doctor_id ? 'selected' : '' ?>><?= e($doctor['full_name']) ?> - <?= e($doctor['specialization']) ?></option><?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <select class="form-select" name="doctor_id" required>
                        <option value="">Select doctor</option>
                        <?php foreach ($doctors as $doctor): ?><option value="<?= (int) $doctor['id'] ?>"><?= e($doctor['full_name']) ?> - <?= e($doctor['specialization']) ?></option><?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Medicine</th><th style="width:120px;">Qty</th><th>Instructions</th><th style="width:60px;"></th></tr></thead>
                <tbody id="rxRows"></tbody>
            </table>
        </div>
        <button class="btn btn-outline-primary" type="button" id="addRxRow"><i class="bi bi-plus-lg"></i> Add Medicine</button>

        <div class="mt-4">
            <label class="form-label">Prescription Notes</label>
            <textarea class="form-control" name="instructions" rows="4" placeholder="General prescription notes"></textarea>
        </div>
        <div class="mt-4"><button class="btn btn-primary"><i class="bi bi-send"></i> Send to Pharmacy</button></div>
    </form>
</section>

<template id="rxTemplate">
    <tr>
        <td>
            <select class="form-select" name="medicine_id[]" required>
                <option value="">Select medicine</option>
                <?php foreach ($medicines as $medicine): ?>
                    <option value="<?= (int) $medicine['id'] ?>"><?= e($medicine['medicine_name']) ?> - Stock <?= (int) $medicine['quantity'] ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <td><input class="form-control" type="number" name="quantity[]" min="1" value="1" required></td>
        <td><input class="form-control" name="line_instructions[]" placeholder="e.g. Once daily after meal"></td>
        <td><button type="button" class="btn btn-outline-danger remove-rx-row"><i class="bi bi-x-lg"></i></button></td>
    </tr>
</template>
<script>
const rxRows = document.getElementById('rxRows');
const rxTemplate = document.getElementById('rxTemplate');
function addRxRow() {
    rxRows.appendChild(rxTemplate.content.cloneNode(true));
}
document.getElementById('addRxRow').addEventListener('click', addRxRow);
rxRows.addEventListener('click', event => {
    const button = event.target.closest('.remove-rx-row');
    if (button) button.closest('tr').remove();
});
addRxRow();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
