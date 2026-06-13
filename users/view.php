<?php
$page_title = 'User Role Management';
require_once __DIR__ . '/../includes/header.php';

$users = $conn->query('SELECT id, username, full_name, role, created_at FROM users ORDER BY role, full_name')->fetch_all(MYSQLI_ASSOC);
$roles = ['Receptionist', 'Doctor', 'Inventory Officer', 'Administrator'];
$permission_groups = [
    'Patient Management' => [
        'Register patients' => ['Receptionist', 'Administrator'],
        'View patient information' => ['Receptionist', 'Doctor', 'Administrator'],
        'Edit patient records' => ['Receptionist', 'Administrator'],
        'Delete patient records' => ['Administrator'],
    ],
    'Appointment Management' => [
        'Book new appointments' => ['Receptionist', 'Administrator'],
        'Update appointments' => ['Receptionist', 'Administrator'],
        'Cancel appointments' => ['Receptionist', 'Administrator'],
        'View appointment calendar' => ['Receptionist', 'Administrator'],
    ],
    'Treatment & Follow-Up' => [
        'View assigned patients' => ['Doctor', 'Administrator'],
        'Manage treatments' => ['Doctor', 'Administrator'],
        'Record consultations' => ['Doctor', 'Administrator'],
        'Manage follow-ups' => ['Doctor', 'Administrator'],
    ],
    'Inventory Management' => [
        'Manage inventory items' => ['Inventory Officer', 'Administrator'],
        'Stock in' => ['Inventory Officer', 'Administrator'],
        'Stock out' => ['Inventory Officer', 'Administrator'],
        'Monitor low stock' => ['Inventory Officer', 'Administrator'],
    ],
    'Reports & Administration' => [
        'View all reports' => ['Administrator'],
        'View patient and appointment reports' => ['Receptionist', 'Administrator'],
        'View treatment and follow-up reports' => ['Doctor', 'Administrator'],
        'View inventory and stock reports' => ['Inventory Officer', 'Administrator'],
        'Manage system users' => ['Administrator'],
        'Manage doctors' => ['Administrator'],
    ],
];

$role_counts = array_fill_keys($roles, 0);
foreach ($users as $user) {
    if (isset($role_counts[$user['role']])) {
        $role_counts[$user['role']]++;
    }
}
?>
<div class="role-page-hero">
    <div>
        <span>Access Control</span>
        <h1>User Role Management</h1>
        <p>Manage clinic accounts, review role coverage, and audit what every role can access.</p>
    </div>
    <a href="add.php"><i class="bi bi-person-plus"></i>Add Member</a>
</div>

<div class="role-settings-page">
    <aside class="settings-nav-panel">
        <h1>Settings</h1>
        <section>
            <h2>Personal</h2>
            <a href="#"><i class="bi bi-person"></i>Profile</a>
            <a href="#"><i class="bi bi-shield-lock"></i>Password</a>
            <a href="#"><i class="bi bi-database"></i>Data</a>
        </section>
        <section>
            <h2>Company</h2>
            <a href="#"><i class="bi bi-building"></i>Clinic details</a>
            <a class="active" href="<?= BASE_URL ?>/users/view.php"><i class="bi bi-people"></i>Team members</a>
            <a href="<?= BASE_URL ?>/doctors/view.php"><i class="bi bi-person-badge"></i>Doctors</a>
            <a href="<?= BASE_URL ?>/reports/index.php"><i class="bi bi-graph-up"></i>Reports</a>
            <a href="<?= BASE_URL ?>/inventory/index.php"><i class="bi bi-archive"></i>Inventory</a>
        </section>
    </aside>

    <section class="role-manager-panel">
        <div class="role-panel-head">
            <div>
                <span>Team members</span>
                <h2>Invite or manage your clinic users</h2>
                <p><?= count($users) ?> active account records synced with the RBAC policy.</p>
            </div>
            <a href="add.php"><i class="bi bi-plus-lg"></i>Add Member</a>
        </div>

        <div class="role-tabs">
            <a href="#team-members">All users <em><?= count($users) ?></em></a>
            <a class="active" href="#role-manager">User role manager</a>
        </div>

        <div class="team-strip" id="team-members">
            <?php foreach ($role_counts as $role => $count): ?>
                <article class="role-stat-card <?= e(role_key($role)) ?>">
                    <i class="bi <?= $role === 'Administrator' ? 'bi-shield-check' : ($role === 'Doctor' ? 'bi-person-badge' : ($role === 'Inventory Officer' ? 'bi-archive' : 'bi-headset')) ?>"></i>
                    <span><?= e($role) ?></span>
                    <strong><?= number_format($count) ?></strong>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="member-list-card">
            <div class="section-kicker">
                <div>
                    <span>All users</span>
                    <h3>Team directory</h3>
                </div>
                <small>CRUD actions</small>
            </div>
            <div class="member-list-head">
                <span>User</span>
                <span>Role</span>
                <span>Joined</span>
                <span>Actions</span>
            </div>
            <?php foreach ($users as $user): ?>
                <div class="member-list-row">
                    <span class="member-identity">
                        <b><?= e(strtoupper(substr($user['full_name'], 0, 1) . substr($user['username'], 0, 1))) ?></b>
                        <span><strong><?= e($user['full_name']) ?></strong><small><?= e($user['username']) ?></small></span>
                    </span>
                    <em class="role-badge <?= e(role_key($user['role'])) ?>"><?= e($user['role']) ?></em>
                    <span><?= e(date('M j, Y', strtotime($user['created_at']))) ?></span>
                    <span class="patient-actions">
                        <a href="edit.php?id=<?= $user['id'] ?>" title="Edit"><i class="bi bi-pencil-square"></i></a>
                        <?php if ((int) $user['id'] !== (int) ($_SESSION['admin_id'] ?? 0)): ?>
                            <a href="delete.php?id=<?= $user['id'] ?>" title="Delete" onclick="return confirm('Delete this user?')"><i class="bi bi-trash"></i></a>
                        <?php endif; ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="permission-matrix-card" id="role-manager">
            <div class="section-kicker permission-kicker">
                <div>
                    <span>Permission matrix</span>
                    <h3>User role manager</h3>
                </div>
                <small>Read-only policy map</small>
            </div>
            <div class="permission-grid permission-grid-head">
                <span>Actions</span>
                <?php foreach ($roles as $role): ?>
                    <span><?= e($role) ?></span>
                <?php endforeach; ?>
            </div>

            <?php foreach ($permission_groups as $group => $actions): ?>
                <div class="permission-group-title">
                    <i class="bi bi-sliders"></i><?= e($group) ?>
                </div>
                <?php foreach ($actions as $action => $allowed_roles): ?>
                    <div class="permission-grid permission-row">
                        <span><?= e($action) ?></span>
                        <?php foreach ($roles as $role): ?>
                            <label class="permission-check" title="<?= e($role . ': ' . $action) ?>">
                                <input type="checkbox" disabled <?= in_array($role, $allowed_roles, true) ? 'checked' : '' ?>>
                                <i class="bi <?= in_array($role, $allowed_roles, true) ? 'bi-check-square-fill' : 'bi-square' ?>"></i>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
    </section>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
