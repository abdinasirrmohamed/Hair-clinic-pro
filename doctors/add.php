<?php
require_once __DIR__ . '/../includes/auth.php';
require_roles(['Administrator']);

$errors = [];

// ── POST handler BEFORE any HTML output ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name            = trim($_POST['full_name']            ?? '');
    $specialization       = trim($_POST['specialization']       ?? '');
    $qualification        = trim($_POST['qualification']        ?? '');
    $phone                = trim($_POST['phone']                ?? '');
    $email                = trim($_POST['email']                ?? '');
    $license_number       = trim($_POST['license_number']       ?? '');
    $experience_years     = max(0, (int) ($_POST['experience_years'] ?? 0));
    $availability_schedule = trim($_POST['availability_schedule'] ?? '');
    $bio                  = trim($_POST['bio']                  ?? '');
    $status               = $_POST['status']                   ?? 'Active';
    $photo                = null;

    if ($full_name === '')      $errors[] = 'Doctor full name is required.';
    if ($specialization === '') $errors[] = 'Specialization is required.';
    if ($phone === '')          $errors[] = 'Phone number is required.';
    if ($license_number === '') $errors[] = 'License number is required.';
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }
    if (!in_array($status, ['Active', 'Inactive'], true)) $errors[] = 'Invalid status.';

    // Photo upload
    if (!$errors && !empty($_FILES['photo']['name']) && is_uploaded_file($_FILES['photo']['tmp_name'])) {
        $ext     = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allowed, true)) {
            $errors[] = 'Profile photo must be JPG, PNG, or WEBP.';
        } elseif ($_FILES['photo']['size'] > 3 * 1024 * 1024) {
            $errors[] = 'Profile photo must be under 3 MB.';
        } else {
            $photo_dir = __DIR__ . '/../images/doctors/';
            if (!is_dir($photo_dir)) mkdir($photo_dir, 0775, true);
            $filename = 'doctor-' . time() . '-' . random_int(1000, 9999) . '.' . $ext;
            if (!move_uploaded_file($_FILES['photo']['tmp_name'], $photo_dir . $filename)) {
                $errors[] = 'Could not save photo. Check images/doctors/ folder permissions.';
            } else {
                $photo = 'images/doctors/' . $filename;
            }
        }
    }

    if (!$errors) {
        // Find a linked user account with matching name and Doctor role (optional auto-link)
        $user_id = null;
        if (!empty($_POST['user_id']) && (int) $_POST['user_id'] > 0) {
            $user_id = (int) $_POST['user_id'];
        }

        $stmt = $conn->prepare('INSERT INTO doctors (user_id, full_name, specialization, qualification, phone, email, license_number, photo, experience_years, availability_schedule, bio, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('isssssssisss', $user_id, $full_name, $specialization, $qualification, $phone, $email, $license_number, $photo, $experience_years, $availability_schedule, $bio, $status);
        try {
            $stmt->execute();
            $doctor_id = (int) $conn->insert_id;

            // Auto-create default weekly schedule
            $days = ['Saturday','Sunday','Monday','Tuesday','Wednesday','Thursday','Friday'];
            foreach ($days as $day) {
                $working = in_array($day, ['Saturday','Sunday','Monday','Tuesday','Wednesday'], true) ? 1 : 0;
                $sched   = $conn->prepare('INSERT INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time, slot_minutes, is_working) VALUES (?, ?, "08:00:00", "16:00:00", 30, ?)');
                $sched->bind_param('isi', $doctor_id, $day, $working);
                $sched->execute();
            }

            log_activity('Created doctor profile', 'Doctors', $doctor_id);
            flash('success', 'Doctor profile created successfully.');
            redirect('/doctors/view.php');
        } catch (mysqli_sql_exception $e) {
            $errors[] = 'A doctor with this license number already exists.';
        }
    }
}

// Doctor-role user accounts available to link
$doctor_users = $conn->query("SELECT id, full_name, username FROM users WHERE role = 'Doctor' ORDER BY full_name")->fetch_all(MYSQLI_ASSOC);

$page_title = 'Add doctor profile';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="patient-head">
    <div>
        <h1>Add doctor profile</h1>
        <p>Clinical staff record with photo, credentials, and schedule. Separate from the login account.</p>
    </div>
    <a class="add-patient-btn" href="view.php"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <strong>Please fix the following:</strong>
        <ul class="mb-0 mt-1">
            <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" id="doctor-form" novalidate>
<div class="row g-4">

    <!-- LEFT: Photo card -->
    <div class="col-lg-3">
        <div class="form-panel h-100 text-center d-flex flex-column align-items-center gap-3 py-4">
            <div id="photo-preview-wrap" style="width:130px;height:130px;border-radius:18px;overflow:hidden;background:#eef2f8;display:flex;align-items:center;justify-content:center;border:2px dashed #b9c3d5;">
                <img id="photo-preview" src="" alt="Preview" style="width:100%;height:100%;object-fit:cover;display:none;">
                <i class="bi bi-person-bounding-box" id="photo-placeholder" style="font-size:3rem;color:#8b9ab5;"></i>
            </div>
            <div class="w-100">
                <label class="form-label fw-semibold d-block text-start">Profile photo</label>
                <input class="form-control form-control-sm" type="file" name="photo" id="photo-input"
                       accept="image/jpeg,image/png,image/webp">
                <div class="form-text">JPG, PNG or WEBP — max 3 MB</div>
            </div>
            <div class="w-100">
                <label class="form-label fw-semibold d-block text-start" for="status">Status</label>
                <select class="form-select" id="status" name="status">
                    <option <?= ($_POST['status'] ?? 'Active') === 'Active'   ? 'selected' : '' ?>>Active</option>
                    <option <?= ($_POST['status'] ?? 'Active') === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="w-100">
                <label class="form-label fw-semibold d-block text-start" for="user_id">
                    Linked login account
                    <span class="text-muted fw-normal">(optional)</span>
                </label>
                <select class="form-select" id="user_id" name="user_id">
                    <option value="">— None —</option>
                    <?php foreach ($doctor_users as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= ((int)($_POST['user_id'] ?? 0)) === $u['id'] ? 'selected' : '' ?>>
                            <?= e($u['full_name']) ?> (<?= e($u['username']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Link to a Doctor-role user account so this doctor can sign in.</div>
            </div>
        </div>
    </div>

    <!-- RIGHT: Details -->
    <div class="col-lg-9">
        <div class="form-panel">

            <!-- Personal info -->
            <h6 class="fw-bold mb-3 text-primary"><i class="bi bi-person-badge me-2"></i>Personal information</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="full_name">Full name <span class="text-danger">*</span></label>
                    <input class="form-control" id="full_name" name="full_name"
                           value="<?= e($_POST['full_name'] ?? '') ?>" required autofocus>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="specialization">Specialization <span class="text-danger">*</span></label>
                    <input class="form-control" id="specialization" name="specialization"
                           value="<?= e($_POST['specialization'] ?? '') ?>"
                           placeholder="e.g. Hair Restoration Specialist" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="qualification">Qualification</label>
                    <input class="form-control" id="qualification" name="qualification"
                           value="<?= e($_POST['qualification'] ?? '') ?>"
                           placeholder="e.g. MBBS, MD">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold" for="experience_years">Years of experience</label>
                    <input class="form-control" type="number" id="experience_years" name="experience_years"
                           min="0" max="60" value="<?= e($_POST['experience_years'] ?? '0') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold" for="license_number">License number <span class="text-danger">*</span></label>
                    <input class="form-control" id="license_number" name="license_number"
                           value="<?= e($_POST['license_number'] ?? '') ?>"
                           placeholder="e.g. HC-MD-1001" required>
                </div>
            </div>

            <hr>

            <!-- Contact -->
            <h6 class="fw-bold mb-3 text-primary"><i class="bi bi-telephone me-2"></i>Contact details</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="phone">Phone <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-phone"></i></span>
                        <input class="form-control" type="tel" id="phone" name="phone"
                               value="<?= e($_POST['phone'] ?? '') ?>"
                               placeholder="+252 61 000 0000" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold" for="email">Email address</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input class="form-control" type="email" id="email" name="email"
                               value="<?= e($_POST['email'] ?? '') ?>"
                               placeholder="doctor@example.com">
                    </div>
                </div>
            </div>

            <hr>

            <!-- Profile -->
            <h6 class="fw-bold mb-3 text-primary"><i class="bi bi-file-person me-2"></i>Profile &amp; schedule</h6>
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label fw-semibold" for="bio">Doctor bio</label>
                    <textarea class="form-control" id="bio" name="bio" rows="3"
                              placeholder="Brief professional summary visible to staff..."><?= e($_POST['bio'] ?? '') ?></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold" for="availability_schedule">Availability notes</label>
                    <textarea class="form-control" id="availability_schedule" name="availability_schedule" rows="2"
                              placeholder="e.g. Available Sat–Wed, 8 AM – 4 PM"><?= e($_POST['availability_schedule'] ?? '') ?></textarea>
                    <div class="form-text">A default weekly schedule (Sat–Wed, 8 AM–4 PM) is created automatically.</div>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4">
                <button class="btn btn-primary" type="submit">
                    <i class="bi bi-person-plus"></i> Save doctor profile
                </button>
                <a class="btn btn-outline-secondary" href="view.php">Cancel</a>
            </div>

        </div>
    </div>

</div>
</form>

<script>
(function () {
    // Live photo preview
    document.getElementById('photo-input').addEventListener('change', function () {
        var file = this.files[0];
        if (!file) return;
        var reader = new FileReader();
        reader.onload = function (e) {
            var img   = document.getElementById('photo-preview');
            var icon  = document.getElementById('photo-placeholder');
            img.src   = e.target.result;
            img.style.display  = '';
            icon.style.display = 'none';
        };
        reader.readAsDataURL(file);
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
