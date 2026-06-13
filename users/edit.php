<?php
$page_title = 'Edit User';
require_once __DIR__ . '/../includes/header.php';

$roles = array_keys(role_permissions());
$id = (int) ($_GET['id'] ?? 0);
$stmt = $conn->prepare('SELECT id, username, full_name, role, status FROM users WHERE id = ?');
$stmt->bind_param('i', $id);
$user = fetch_one($stmt);

if (!$user) {
    flash('danger', 'User not found.');
    redirect('/users/view.php');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $role = $_POST['role'] ?? '';
    $status = $_POST['status'] ?? 'Active';
    $password = $_POST['password'] ?? '';

    if ($username === '' || $full_name === '' || !in_array($role, $roles, true)) {
        $errors[] = 'Full name, username, and role are required.';
    }

    if (!$errors) {
        if ($password !== '') {
            if (strlen($password) < 6) {
                $errors[] = 'Password must be at least 6 characters.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare('UPDATE users SET username = ?, full_name = ?, role = ?, status = ?, password = ? WHERE id = ?');
                $stmt->bind_param('sssssi', $username, $full_name, $role, $status, $hash, $id);
            }
        } else {
            $stmt = $conn->prepare('UPDATE users SET username = ?, full_name = ?, role = ?, status = ? WHERE id = ?');
            $stmt->bind_param('ssssi', $username, $full_name, $role, $status, $id);
        }

        if (!$errors) {
            try {
                $stmt->execute();
                if ((int) ($_SESSION['admin_id'] ?? 0) === $id) {
                    $_SESSION['admin_name'] = $full_name;
                    $_SESSION['admin_role'] = $role;
                }
                flash('success', 'User updated successfully.');
                redirect('/users/view.php');
            } catch (mysqli_sql_exception $e) {
                $errors[] = 'Username already exists.';
            }
        }
    }
}
?>
<div class="patient-head">
    <div>
        <h1>Edit User</h1>
        <p>Update account identity, role, or password.</p>
    </div>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger"><?= e(implode(' ', $errors)) ?></div>
<?php endif; ?>

<section class="form-panel">
    <form method="post" class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Full Name</label>
            <input class="form-control" name="full_name" value="<?= e($_POST['full_name'] ?? $user['full_name']) ?>" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Username</label>
            <input class="form-control" name="username" value="<?= e($_POST['username'] ?? $user['username']) ?>" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Role</label>
            <select class="form-select" name="role" required>
                <?php foreach ($roles as $role): ?>
                    <option value="<?= e($role) ?>" <?= ($_POST['role'] ?? $user['role']) === $role ? 'selected' : '' ?>><?= e($role) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6"><label class="form-label">Status</label><select class="form-select" name="status"><?php foreach (['Active', 'Inactive'] as $status): ?><option <?= ($_POST['status'] ?? $user['status']) === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6">
            <label class="form-label">New Password</label>
            <input class="form-control" type="password" name="password" placeholder="Leave blank to keep current password">
        </div>
        <div class="col-12 d-flex gap-2">
            <button class="btn btn-primary" type="submit">Save Changes</button>
            <a class="btn btn-outline-secondary" href="view.php">Cancel</a>
        </div>
    </form>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
