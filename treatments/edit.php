<?php
require_once __DIR__ . '/../includes/auth.php';
require_roles(['Administrator', 'Doctor']);
$page_title = 'Update Treatment';
require_once __DIR__ . '/../includes/header.php';
$id = (int) ($_GET['id'] ?? 0);
$patients = $conn->query('SELECT id, full_name FROM patients p WHERE 1=1 ' . doctor_patient_filter('p') . ' ORDER BY full_name')->fetch_all(MYSQLI_ASSOC);
$stmt = $conn->prepare('SELECT * FROM treatments WHERE id = ?');
$stmt->bind_param('i', $id);
$treatment = fetch_one($stmt);
if (!$treatment) {
    flash('danger', 'Treatment record not found.');
    redirect('/treatments/view.php');
}
require_patient_assignment((int) $treatment['patient_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

    $upload_dir = __DIR__ . '/../images/treatments/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0775, true);

    $pre_op_photo = $treatment['pre_op_photo'];
    if (!empty($_FILES['pre_op_photo']['name']) && is_uploaded_file($_FILES['pre_op_photo']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['pre_op_photo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $filename = 'pre-' . time() . '-' . rand(100, 999) . '.' . $ext;
            if (move_uploaded_file($_FILES['pre_op_photo']['tmp_name'], $upload_dir . $filename)) {
                $pre_op_photo = 'images/treatments/' . $filename;
            }
        }
    }

    $post_op_photo = $treatment['post_op_photo'];
    if (!empty($_FILES['post_op_photo']['name']) && is_uploaded_file($_FILES['post_op_photo']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['post_op_photo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $filename = 'post-' . time() . '-' . rand(100, 999) . '.' . $ext;
            if (move_uploaded_file($_FILES['post_op_photo']['tmp_name'], $upload_dir . $filename)) {
                $post_op_photo = 'images/treatments/' . $filename;
            }
        }
    }

    $stmt = $conn->prepare('UPDATE treatments SET patient_id = ?, treatment_name = ?, treatment_date = ?, treatment_stage = ?, progress = ?, cost = ?, grafts_planned = ?, grafts_extracted = ?, grafts_implanted = ?, donor_area_status = ?, recipient_area_status = ?, pre_op_photo = ?, post_op_photo = ?, notes = ? WHERE id = ?');
    $stmt->bind_param('issssdiiisssssi', $patient_id, $treatment_name, $treatment_date, $treatment_stage, $progress, $cost, $grafts_planned, $grafts_extracted, $grafts_implanted, $donor_area_status, $recipient_area_status, $pre_op_photo, $post_op_photo, $notes, $id);
    $stmt->execute();
    log_activity('Updated treatment record', 'Treatments', $id);
    flash('success', 'Treatment record updated successfully.');
    redirect('/treatments/view.php');
}
?>

<div class="patient-head">
    <div>
        <h1>Update Treatment Record</h1>
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
                        <?php foreach ($patients as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= (int) $treatment['patient_id'] === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Treatment Name <span class="text-danger">*</span></label>
                    <input class="form-control" name="treatment_name" value="<?= e($treatment['treatment_name']) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Treatment Date <span class="text-danger">*</span></label>
                    <input class="form-control" type="date" name="treatment_date" value="<?= e($treatment['treatment_date']) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Treatment Stage</label>
                    <select class="form-select" name="treatment_stage">
                        <?php foreach (['Pre-Treatment Evaluation', 'Surgery', 'Post-Treatment Review'] as $st): ?>
                            <option <?= ($treatment['treatment_stage'] ?? 'Surgery') === $st ? 'selected' : '' ?>><?= e($st) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Progress</label>
                    <select class="form-select" name="progress">
                        <?php foreach (['Started','In Progress','Completed'] as $p): ?>
                            <option <?= $treatment['progress'] === $p ? 'selected' : '' ?>><?= $p ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Cost (USD)</label>
                    <input class="form-control" type="number" min="0" step="0.01" name="cost" value="<?= e($treatment['cost']) ?>">
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
                    <input class="form-control" type="number" min="0" name="grafts_planned" value="<?= e($treatment['grafts_planned'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Grafts Extracted</label>
                    <input class="form-control" type="number" min="0" name="grafts_extracted" value="<?= e($treatment['grafts_extracted'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Grafts Implanted</label>
                    <input class="form-control" type="number" min="0" name="grafts_implanted" value="<?= e($treatment['grafts_implanted'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Donor Area Status</label>
                    <textarea class="form-control" name="donor_area_status" rows="2"><?= e($treatment['donor_area_status'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Recipient Area Status</label>
                    <textarea class="form-control" name="recipient_area_status" rows="2"><?= e($treatment['recipient_area_status'] ?? '') ?></textarea>
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
                    <?php if (!empty($treatment['pre_op_photo'])): ?>
                        <div class="mt-2"><img src="<?= BASE_URL ?>/<?= e($treatment['pre_op_photo']) ?>" alt="Pre-Op" style="max-height:100px; border-radius:8px;"></div>
                    <?php else: ?>
                        <div class="form-text">No photo uploaded yet.</div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Post-Treatment Photo (After)</label>
                    <input class="form-control" type="file" name="post_op_photo" accept="image/jpeg,image/png,image/webp">
                    <?php if (!empty($treatment['post_op_photo'])): ?>
                        <div class="mt-2"><img src="<?= BASE_URL ?>/<?= e($treatment['post_op_photo']) ?>" alt="Post-Op" style="max-height:100px; border-radius:8px;"></div>
                    <?php else: ?>
                        <div class="form-text">No photo uploaded yet.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <hr>

        <!-- Section 4: Notes -->
        <div class="col-12">
            <h6 class="fw-bold text-primary mb-3"><i class="bi bi-card-text me-2"></i>General Notes</h6>
            <textarea class="form-control" name="notes" rows="4"><?= e($treatment['notes']) ?></textarea>
        </div>

        <div class="col-12 mt-4">
            <button class="btn btn-primary"><i class="bi bi-save"></i> Update Treatment</button>
            <a class="btn btn-secondary" href="view.php">Cancel</a>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
