<?php
$page_title = 'Edit Doctor';
require_once __DIR__ . '/../includes/header.php';

$id = (int) ($_GET['id'] ?? 0);
$stmt = $conn->prepare('SELECT * FROM doctors WHERE id = ?');
$stmt->bind_param('i', $id);
$doctor = fetch_one($stmt);
if (!$doctor) {
    flash('danger', 'Doctor not found.');
    redirect('/doctors/view.php');
}

$doctor_users = $conn->query("SELECT id, full_name, username FROM users WHERE role = 'Doctor' ORDER BY full_name")->fetch_all(MYSQLI_ASSOC);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'] !== '' ? (int) $_POST['user_id'] : null;
    $full_name = trim($_POST['full_name'] ?? '');
    $specialization = trim($_POST['specialization'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $license_number = trim($_POST['license_number'] ?? '');
    $qualification = trim($_POST['qualification'] ?? '');
    $experience_years = (int) ($_POST['experience_years'] ?? 0);
    $availability_schedule = trim($_POST['availability_schedule'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $status = $_POST['status'] ?? 'Active';

    if ($full_name === '' || $specialization === '' || $phone === '' || $license_number === '') {
        $errors[] = 'Doctor name, specialization, phone, and license number are required.';
    }

    if (!$errors) {
        $photo = $doctor['photo'];
        if (!empty($_FILES['photo']['name']) && is_uploaded_file($_FILES['photo']['tmp_name'])) {
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                $photo = 'images/doctors/doctor-' . time() . '-' . random_int(1000, 9999) . '.' . $ext;
                move_uploaded_file($_FILES['photo']['tmp_name'], __DIR__ . '/../' . $photo);
            }
        }
        $stmt = $conn->prepare('UPDATE doctors SET user_id = ?, full_name = ?, specialization = ?, qualification = ?, phone = ?, email = ?, license_number = ?, photo = ?, experience_years = ?, availability_schedule = ?, bio = ?, status = ? WHERE id = ?');
        $stmt->bind_param('isssssssisssi', $user_id, $full_name, $specialization, $qualification, $phone, $email, $license_number, $photo, $experience_years, $availability_schedule, $bio, $status, $id);
        try {
            $stmt->execute();
            flash('success', 'Doctor updated successfully.');
            redirect('/doctors/view.php');
        } catch (mysqli_sql_exception $e) {
            $errors[] = 'A doctor with this license number already exists.';
        }
    }
}
?>
<div class="patient-head">
    <div>
        <h1>Edit Doctor</h1>
        <p>Update clinical staff details and linked user account.</p>
    </div>
</div>

<?php if ($errors): ?><div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>

<section class="form-panel">
    <form method="post" enctype="multipart/form-data" class="row g-3">
        <div class="col-md-6"><label class="form-label">Doctor Name</label><input class="form-control" name="full_name" value="<?= e($_POST['full_name'] ?? $doctor['full_name']) ?>" required></div>
        <div class="col-md-6"><label class="form-label">Specialization</label><input class="form-control" name="specialization" value="<?= e($_POST['specialization'] ?? $doctor['specialization']) ?>" required></div>
        <div class="col-md-6"><label class="form-label">Qualification</label><input class="form-control" name="qualification" value="<?= e($_POST['qualification'] ?? $doctor['qualification']) ?>"></div>
        <div class="col-md-3"><label class="form-label">Experience Years</label><input class="form-control" type="number" name="experience_years" value="<?= e($_POST['experience_years'] ?? $doctor['experience_years']) ?>"></div>
        <div class="col-md-3"><label class="form-label">Photo</label><input class="form-control" type="file" name="photo" accept="image/*"></div>
        <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" name="phone" value="<?= e($_POST['phone'] ?? $doctor['phone']) ?>" required></div>
        <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="<?= e($_POST['email'] ?? $doctor['email']) ?>"></div>
        <div class="col-md-6"><label class="form-label">License Number</label><input class="form-control" name="license_number" value="<?= e($_POST['license_number'] ?? $doctor['license_number']) ?>" required></div>
        <div class="col-md-3"><label class="form-label">Status</label><select class="form-select" name="status"><?php foreach (['Active', 'Inactive'] as $status): ?><option <?= ($_POST['status'] ?? $doctor['status']) === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3">
            <label class="form-label">Linked User</label>
            <select class="form-select" name="user_id">
                <option value="">No linked user</option>
                <?php foreach ($doctor_users as $user): ?>
                    <option value="<?= $user['id'] ?>" <?= (int) ($_POST['user_id'] ?? $doctor['user_id']) === (int) $user['id'] ? 'selected' : '' ?>><?= e($user['full_name']) ?> (<?= e($user['username']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12"><label class="form-label">Availability Schedule</label><textarea class="form-control" name="availability_schedule" rows="3"><?= e($_POST['availability_schedule'] ?? $doctor['availability_schedule']) ?></textarea></div>
        <div class="col-12"><label class="form-label">Doctor Bio</label><textarea class="form-control" name="bio" rows="3"><?= e($_POST['bio'] ?? $doctor['bio']) ?></textarea></div>
        <div class="col-12 d-flex gap-2">
            <button class="btn btn-primary" type="submit">Save Changes</button>
            <a class="btn btn-outline-secondary" href="view.php">Cancel</a>
        </div>
    </form>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
