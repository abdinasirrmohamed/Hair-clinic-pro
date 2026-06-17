<?php
require_once __DIR__ . '/../includes/auth.php';
require_roles(['Administrator', 'Doctor']);
$page_title = 'Add Treatment';
require_once __DIR__ . '/../includes/header.php';
$patients = $conn->query('SELECT id, full_name FROM patients p WHERE 1=1 ' . doctor_patient_filter('p') . ' ORDER BY full_name')->fetch_all(MYSQLI_ASSOC);
$medicines = $conn->query('SELECT id, medicine_name, quantity, unit_price, expiry_date FROM medicines ORDER BY medicine_name')->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->begin_transaction();
        $patient_id = (int) $_POST['patient_id'];
        require_patient_assignment($patient_id);
        
        $treatment_name = trim($_POST['treatment_name']);
        $treatment_date = $_POST['treatment_date'];
        $treatment_stage = $_POST['treatment_stage'];
        $progress = $_POST['progress'];
        $cost = (float) $_POST['cost'];
        
        $grafts_planned = $_POST['grafts_planned'] !== '' ? (int)$_POST['grafts_planned'] : null;
        $grafts_extracted = $_POST['grafts_extracted'] !== '' ? (int)$_POST['grafts_extracted'] : null;
        $grafts_implanted = $_POST['grafts_implanted'] !== '' ? (int)$_POST['grafts_implanted'] : null;
        $donor_area_status = trim($_POST['donor_area_status'] ?? '');
        $recipient_area_status = trim($_POST['recipient_area_status'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        // Handle Photo Uploads
        $upload_dir = __DIR__ . '/../images/treatments/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0775, true);

        $pre_op_photo = null;
        if (!empty($_FILES['pre_op_photo']['name']) && is_uploaded_file($_FILES['pre_op_photo']['tmp_name'])) {
            $ext = strtolower(pathinfo($_FILES['pre_op_photo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $filename = 'pre-' . time() . '-' . rand(100, 999) . '.' . $ext;
                if (move_uploaded_file($_FILES['pre_op_photo']['tmp_name'], $upload_dir . $filename)) {
                    $pre_op_photo = 'images/treatments/' . $filename;
                }
            }
        }

        $post_op_photo = null;
        if (!empty($_FILES['post_op_photo']['name']) && is_uploaded_file($_FILES['post_op_photo']['tmp_name'])) {
            $ext = strtolower(pathinfo($_FILES['post_op_photo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $filename = 'post-' . time() . '-' . rand(100, 999) . '.' . $ext;
                if (move_uploaded_file($_FILES['post_op_photo']['tmp_name'], $upload_dir . $filename)) {
                    $post_op_photo = 'images/treatments/' . $filename;
                }
            }
        }

        $stmt = $conn->prepare('INSERT INTO treatments (patient_id, treatment_name, treatment_date, treatment_stage, progress, cost, grafts_planned, grafts_extracted, grafts_implanted, donor_area_status, recipient_area_status, pre_op_photo, post_op_photo, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('issssdiiisssss', $patient_id, $treatment_name, $treatment_date, $treatment_stage, $progress, $cost, $grafts_planned, $grafts_extracted, $grafts_implanted, $donor_area_status, $recipient_area_status, $pre_op_photo, $post_op_photo, $notes);
        $stmt->execute();
        $treatment_id = (int) $conn->insert_id;

        $usage_medicine_id = (int) ($_POST['usage_medicine_id'] ?? 0);
        $usage_quantity = max(0, (int) ($_POST['usage_quantity'] ?? 0));
        if ($usage_medicine_id > 0 && $usage_quantity > 0) {
            $stock = $conn->prepare('SELECT * FROM medicines WHERE id = ? FOR UPDATE');
            $stock->bind_param('i', $usage_medicine_id);
            $medicine = fetch_one($stock);
            ensure_medicine_can_issue($medicine, $usage_quantity);
            $update = $conn->prepare('UPDATE medicines SET quantity = quantity - ? WHERE id = ?');
            $update->bind_param('ii', $usage_quantity, $usage_medicine_id);
            $update->execute();
            record_inventory_movement($usage_medicine_id, 'Treatment Consumption', $usage_quantity, (float) $medicine['unit_price'], [
                'department' => 'Treatment',
                'purpose' => 'Medicine or supply used during treatment',
                'reference_type' => 'Treatment',
                'reference_id' => $treatment_id,
            ]);
        }

        $conn->commit();
        log_activity('Created treatment record', 'Treatments', $treatment_id);
        flash('success', 'Treatment record added successfully.');
    } catch (Throwable $e) {
        if ($conn->errno === 0) $conn->rollback();
        flash('danger', $e->getMessage());
    }
    redirect('/treatments/view.php');
}
?>

<div class="patient-head">
    <div>
        <h1>Add Treatment Record</h1>
        <p>Record clinical data, grafts, and before/after photos for the procedure.</p>
    </div>
    <a class="add-patient-btn" href="view.php"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<form class="form-panel" method="post" enctype="multipart/form-data">
    <div class="row g-4">
        <!-- Section 1: Basic Info -->
        <div class="col-12">
            <h6 class="fw-bold text-primary mb-3"><i class="bi bi-info-circle me-2"></i>Basic Information</h6>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Patient <span class="text-danger">*</span></label>
                    <select class="form-select" name="patient_id" required>
                        <option value="">Select patient</option>
                        <?php foreach ($patients as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= e($p['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Treatment Name <span class="text-danger">*</span></label>
                    <input class="form-control" name="treatment_name" placeholder="e.g. FUE Hair Transplant" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Treatment Date <span class="text-danger">*</span></label>
                    <input class="form-control" type="date" name="treatment_date" value="<?= date('Y-m-d') ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Treatment Stage</label>
                    <select class="form-select" name="treatment_stage">
                        <option>Pre-Treatment Evaluation</option>
                        <option selected>Surgery</option>
                        <option>Post-Treatment Review</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Progress</label>
                    <select class="form-select" name="progress">
                        <option>Started</option>
                        <option>In Progress</option>
                        <option>Completed</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Cost (USD)</label>
                    <input class="form-control" type="number" min="0" step="0.01" name="cost" value="0">
                </div>
            </div>
        </div>

        <hr>

        <!-- Section 2: Clinical Data (Grafts & Area Status) -->
        <div class="col-12">
            <h6 class="fw-bold text-primary mb-3"><i class="bi bi-activity me-2"></i>Clinical Details (Hair Transplant)</h6>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Grafts Planned</label>
                    <input class="form-control" type="number" min="0" name="grafts_planned" placeholder="e.g. 2500">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Grafts Extracted</label>
                    <input class="form-control" type="number" min="0" name="grafts_extracted">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Grafts Implanted</label>
                    <input class="form-control" type="number" min="0" name="grafts_implanted">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Donor Area Status</label>
                    <textarea class="form-control" name="donor_area_status" rows="2" placeholder="Describe the donor area..."></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Recipient Area Status</label>
                    <textarea class="form-control" name="recipient_area_status" rows="2" placeholder="Describe the recipient area..."></textarea>
                </div>
            </div>
        </div>

        <hr>

        <!-- Section 3: Before & After Photos -->
        <div class="col-12">
            <h6 class="fw-bold text-primary mb-3"><i class="bi bi-camera me-2"></i>Pre-Op & Post-Op Photos</h6>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Pre-Treatment Photo (Before)</label>
                    <input class="form-control" type="file" name="pre_op_photo" accept="image/jpeg,image/png,image/webp">
                    <div class="form-text">Upload a clear photo of the area before treatment.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Post-Treatment Photo (After)</label>
                    <input class="form-control" type="file" name="post_op_photo" accept="image/jpeg,image/png,image/webp">
                    <div class="form-text">Upload a clear photo of the area after treatment.</div>
                </div>
            </div>
        </div>

        <hr>

        <!-- Section 4: Inventory & Notes -->
        <div class="col-12">
            <h6 class="fw-bold text-primary mb-3"><i class="bi bi-box-seam me-2"></i>Inventory Usage & Notes</h6>
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label fw-semibold">Treatment Stock Usage</label>
                    <select class="form-select" name="usage_medicine_id">
                        <option value="">No inventory item used</option>
                        <?php foreach ($medicines as $medicine): ?>
                            <option value="<?= (int) $medicine['id'] ?>">
                                <?= e($medicine['medicine_name']) ?> - Stock <?= number_format((int) $medicine['quantity']) ?> - Exp <?= e($medicine['expiry_date']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Usage Quantity</label>
                    <input class="form-control" type="number" min="0" name="usage_quantity" value="0">
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">General Notes</label>
                    <textarea class="form-control" name="notes" rows="3"></textarea>
                </div>
            </div>
        </div>

        <div class="col-12 mt-4">
            <button class="btn btn-primary"><i class="bi bi-save"></i> Save Treatment</button>
            <a class="btn btn-secondary" href="view.php">Cancel</a>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
