<?php
require_once __DIR__ . '/../includes/auth.php';
require_access('pharmacy');
$page_title = 'Medicine Management';
require_once __DIR__ . '/../includes/header.php';

$can_manage_medicines = in_array(current_role(), ['Administrator', 'Inventory Officer'], true);
$search = trim($_GET['search'] ?? '');
if ($search !== '') {
    $like = '%' . $search . '%';
    $stmt = $conn->prepare('SELECT * FROM medicines WHERE medicine_name LIKE ? OR category LIKE ? OR supplier LIKE ? ORDER BY medicine_name');
    $stmt->bind_param('sss', $like, $like, $like);
    $medicines = fetch_all($stmt);
} else {
    $medicines = $conn->query('SELECT * FROM medicines ORDER BY medicine_name')->fetch_all(MYSQLI_ASSOC);
}
?>
<div class="patient-head">
    <div><h1>Medicine Management</h1><p>Manage pharmacy stock, pricing, suppliers, and expiry dates.</p></div>
    <?php if ($can_manage_medicines): ?><a class="add-patient-btn" href="medicine_add.php"><i class="bi bi-plus-lg"></i>Add Medicine</a><?php endif; ?>
</div>
<form class="top-search patient-search mb-4" method="get" style="width:100%;max-width:620px;"><i class="bi bi-search"></i><input name="search" value="<?= e($search) ?>" placeholder="Search medicines, category, supplier..."></form>
<section class="patient-management-card">
    <div class="patient-tabs"><div class="tab-links"><a href="dashboard.php">Dashboard</a><a class="active" href="medicines.php">Medicines</a><a href="sale.php">New Sale</a><a href="prescriptions.php">Prescriptions</a><a href="reports.php">Reports</a></div></div>
    <div class="patient-list-grid patient-list-head" style="grid-template-columns: 1.4fr 1fr .7fr .8fr .9fr 1fr .7fr;">
        <span>Name</span><span>Category</span><span>Qty</span><span>Price</span><span>Expiry</span><span>Supplier</span><span>Actions</span>
    </div>
    <?php foreach ($medicines as $medicine): ?>
        <div class="patient-list-grid patient-list-row" style="grid-template-columns: 1.4fr 1fr .7fr .8fr .9fr 1fr .7fr;">
            <span><strong><?= e($medicine['medicine_name']) ?></strong></span>
            <span><?= e($medicine['category']) ?></span>
            <span><em class="status-pill <?= (int) $medicine['quantity'] <= 10 ? 'inactive' : 'active' ?>"><?= number_format((int) $medicine['quantity']) ?></em></span>
            <span>$<?= number_format((float) $medicine['unit_price'], 2) ?></span>
            <span><?= e(date('M j, Y', strtotime($medicine['expiry_date']))) ?></span>
            <span><?= e($medicine['supplier']) ?></span>
            <span class="patient-actions">
                <?php if ($can_manage_medicines): ?>
                    <a href="medicine_edit.php?id=<?= $medicine['id'] ?>"><i class="bi bi-pencil-square"></i></a><a href="medicine_delete.php?id=<?= $medicine['id'] ?>" onclick="return confirm('Delete medicine?')"><i class="bi bi-trash"></i></a>
                <?php else: ?>
                    <span class="text-muted">View only</span>
                <?php endif; ?>
            </span>
        </div>
    <?php endforeach; ?>
    <?php if (!$medicines): ?><div class="empty-state">No medicines found.</div><?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
