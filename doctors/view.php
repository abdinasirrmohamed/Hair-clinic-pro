<?php
$page_title = 'Doctor Management';
require_once __DIR__ . '/../includes/header.php';

$search = trim($_GET['search'] ?? '');
if ($search !== '') {
    $like = '%' . $search . '%';
    $stmt = $conn->prepare(
        'SELECT d.*, u.username
         FROM doctors d
         LEFT JOIN users u ON u.id = d.user_id
         WHERE d.full_name LIKE ? OR d.specialization LIKE ? OR d.phone LIKE ? OR d.email LIKE ? OR d.license_number LIKE ?
         ORDER BY d.full_name'
    );
    $stmt->bind_param('sssss', $like, $like, $like, $like, $like);
    $doctors = fetch_all($stmt);
} else {
    $doctors = $conn->query(
        'SELECT d.*, u.username
         FROM doctors d
         LEFT JOIN users u ON u.id = d.user_id
         ORDER BY d.full_name'
    )->fetch_all(MYSQLI_ASSOC);
}
?>
<div class="patient-head">
    <div>
        <h1>Doctor Management</h1>
        <p>Add doctors, link login accounts, and manage clinical staff records.</p>
    </div>
    <a class="add-patient-btn" href="add.php"><i class="bi bi-person-plus"></i>Add Doctor</a>
</div>

<form class="top-search patient-search mb-4" method="get" style="width: 100%; max-width: 620px;">
    <i class="bi bi-search"></i>
    <input name="search" value="<?= e($search) ?>" placeholder="Search doctors by name, phone, specialty, license...">
</form>

<section class="patient-management-card">
    <div class="patient-tabs">
        <div class="tab-links">
            <a class="active" href="view.php">All Doctors</a>
        </div>
    </div>
    <div class="patient-list-grid patient-list-head" style="grid-template-columns: 1.4fr 1.3fr 1.2fr 1fr 1fr .8fr;">
        <span>Doctor</span>
        <span>Specialization</span>
        <span>Contact</span>
        <span>License</span>
        <span>Login User</span>
        <span>Actions</span>
    </div>
    <?php foreach ($doctors as $doctor): ?>
        <div class="patient-list-grid patient-list-row" style="grid-template-columns: 1.4fr 1.3fr 1.2fr 1fr 1fr .8fr;">
            <span class="patient-list-name">
                <span class="patient-avatar blue-avatar"><?= e(substr($doctor['full_name'], 0, 2)) ?></span>
                <span><strong><?= e($doctor['full_name']) ?></strong><small><?= e($doctor['status']) ?></small></span>
            </span>
            <span><?= e($doctor['specialization']) ?></span>
            <span class="patient-contact"><strong><?= e($doctor['phone']) ?></strong><small><?= e($doctor['email'] ?: 'No email') ?></small></span>
            <span><?= e($doctor['license_number']) ?></span>
            <span><?= e($doctor['username'] ?: 'Not linked') ?></span>
            <span class="patient-actions">
                <a href="edit.php?id=<?= $doctor['id'] ?>" title="Edit"><i class="bi bi-pencil-square"></i></a>
                <a href="delete.php?id=<?= $doctor['id'] ?>" title="Delete" onclick="return confirm('Delete this doctor?')"><i class="bi bi-trash"></i></a>
            </span>
        </div>
    <?php endforeach; ?>
    <?php if (!$doctors): ?>
        <div class="empty-state">No doctors found.</div>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
