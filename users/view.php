<?php
$page_title = 'User Management';
require_once __DIR__ . '/../includes/header.php';

$users = $conn->query('SELECT id, username, full_name, role, status, created_at FROM users ORDER BY role, full_name')->fetch_all(MYSQLI_ASSOC);
?>
<div class="patient-head">
    <div>
        <h1>User Management</h1>
        <p>Create users, assign roles, and control system access.</p>
    </div>
    <a class="add-patient-btn" href="add.php"><i class="bi bi-person-plus"></i>Add New User</a>
</div>

<section class="patient-management-card">
    <div class="patient-tabs">
        <div class="tab-links">
            <a class="active" href="view.php">All Users</a>
        </div>
    </div>
    <div class="patient-list-grid patient-list-head" style="grid-template-columns: 1fr 1.4fr 1.1fr .8fr 1fr 1fr;">
        <span>Username</span>
        <span>Full Name</span>
        <span>Role</span>
        <span>Status</span>
        <span>Created</span>
        <span>Actions</span>
    </div>
    <?php foreach ($users as $user): ?>
        <div class="patient-list-grid patient-list-row" style="grid-template-columns: 1fr 1.4fr 1.1fr .8fr 1fr 1fr;">
            <span><strong><?= e($user['username']) ?></strong></span>
            <span><?= e($user['full_name']) ?></span>
            <span><em class="status-pill active"><?= e($user['role']) ?></em></span>
            <span><em class="status-pill <?= $user['status'] === 'Active' ? 'active' : 'inactive' ?>"><?= e($user['status']) ?></em></span>
            <span><?= e(date('M j, Y', strtotime($user['created_at']))) ?></span>
            <span class="patient-actions">
                <a href="edit.php?id=<?= $user['id'] ?>" title="Edit"><i class="bi bi-pencil-square"></i></a>
                <?php if ((int) $user['id'] !== (int) ($_SESSION['admin_id'] ?? 0)): ?>
                    <a href="delete.php?id=<?= $user['id'] ?>" title="Delete" onclick="return confirm('Delete this user?')"><i class="bi bi-trash"></i></a>
                <?php endif; ?>
            </span>
        </div>
    <?php endforeach; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
