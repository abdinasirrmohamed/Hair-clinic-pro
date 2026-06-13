<?php
$page_title = 'Add User';
require_once __DIR__ . '/../includes/header.php';

$roles = array_keys(role_permissions());
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $role = $_POST['role'] ?? '';
    $status = $_POST['status'] ?? 'Active';
    $password = $_POST['password'] ?? '';

    if ($username === '') {
        $errors[] = 'Username is required.';
    }
    if ($full_name === '') {
        $errors[] = 'Full name is required.';
    }
    if (!in_array($role, $roles, true)) {
        $errors[] = 'A valid role is required.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('INSERT INTO users (username, password, full_name, role, status) VALUES (?, ?, ?, ?, ?)');
        $stmt->bind_param('sssss', $username, $hash, $full_name, $role, $status);
        try {
            $stmt->execute();
            log_activity('Created user account', 'Users', $conn->insert_id);
            flash('success', 'User created successfully.');
            redirect('/users/view.php');
        } catch (mysqli_sql_exception $e) {
            $errors[] = 'Username already exists.';
        }
    }
}
?>
<div class="patient-head">
    <div>
        <h1>Add New User</h1>
        <p>Assign a role so this account only sees the correct modules.</p>
    </div>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div>
<?php endif; ?>

<section class="form-panel">
    <form method="post" class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Full Name</label>
            <input class="form-control" name="full_name" value="<?= e($_POST['full_name'] ?? '') ?>" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Username</label>
            <input class="form-control" name="username" value="<?= e($_POST['username'] ?? '') ?>" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Role</label>
            <select class="form-select" name="role" required>
                <?php foreach ($roles as $role): ?>
                    <option value="<?= e($role) ?>" <?= ($_POST['role'] ?? '') === $role ? 'selected' : '' ?>><?= e($role) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6"><label class="form-label">Status</label><select class="form-select" name="status"><option>Active</option><option>Inactive</option></select></div>
        <div class="col-md-6">
            <label class="form-label">Password</label>
            <input class="form-control" type="password" name="password" required>
        </div>
        <div class="col-12 d-flex gap-2">
            <button class="btn btn-primary" type="submit">Create User</button>
            <a class="btn btn-outline-secondary" href="view.php">Cancel</a>
        </div>
    </form>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
