<?php
require_once __DIR__ . '/../includes/auth.php';
require_access('inventory');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';
    $id = (int) ($_POST['id'] ?? 0);
    if ($action === 'delete') {
        $stmt = $conn->prepare('DELETE FROM suppliers WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        log_activity('Deleted supplier', 'Inventory', $id);
        flash('success', 'Supplier deleted.');
        redirect('/inventory/suppliers.php');
    }

    $company = trim($_POST['company_name'] ?? '');
    $contact = trim($_POST['contact_person'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    if ($company === '' || $phone === '') {
        flash('danger', 'Company name and phone are required.');
        redirect('/inventory/suppliers.php');
    }
    if ($id > 0) {
        $stmt = $conn->prepare('UPDATE suppliers SET company_name = ?, contact_person = ?, phone = ?, email = ?, address = ? WHERE id = ?');
        $stmt->bind_param('sssssi', $company, $contact, $phone, $email, $address, $id);
        $stmt->execute();
        log_activity('Updated supplier', 'Inventory', $id);
        flash('success', 'Supplier updated.');
    } else {
        $stmt = $conn->prepare('INSERT INTO suppliers (company_name, contact_person, phone, email, address) VALUES (?, ?, ?, ?, ?)');
        $stmt->bind_param('sssss', $company, $contact, $phone, $email, $address);
        $stmt->execute();
        log_activity('Created supplier', 'Inventory', $conn->insert_id);
        flash('success', 'Supplier added.');
    }
    redirect('/inventory/suppliers.php');
}

$suppliers = $conn->query('SELECT s.*, COUNT(m.id) item_count FROM suppliers s LEFT JOIN medicines m ON m.supplier_id = s.id GROUP BY s.id ORDER BY s.company_name')->fetch_all(MYSQLI_ASSOC);
$page_title = 'Supplier Management';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="patient-head"><div><h1>Supplier Management</h1><p>Manage companies providing medicines and medical supplies.</p></div></div>
<section class="patient-management-card">
    <div class="patient-tabs"><div class="tab-links"><a href="items.php">General Items</a><a href="medicines.php">Medicines</a><a href="purchase.php">Purchase</a><a href="stock_in.php">Stock In</a><a href="stock_out.php">Stock Out</a><a class="active" href="suppliers.php">Suppliers</a><a href="movements.php">Movements</a><a href="reports.php">Reports</a></div></div>
    <div class="row g-4 p-4">
        <div class="col-lg-4">
            <form class="form-panel m-0" method="post">
                <h2 class="h5 mb-3">Add Supplier</h2>
                <input class="form-control mb-3" name="company_name" placeholder="Company Name" required>
                <input class="form-control mb-3" name="contact_person" placeholder="Contact Person">
                <input class="form-control mb-3" name="phone" placeholder="Phone Number" required>
                <input class="form-control mb-3" type="email" name="email" placeholder="Email Address">
                <textarea class="form-control mb-3" name="address" rows="3" placeholder="Address"></textarea>
                <button class="btn btn-primary w-100">Save Supplier</button>
            </form>
        </div>
        <div class="col-lg-8">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>Company</th><th>Contact</th><th>Phone</th><th>Email</th><th>Items</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($suppliers as $supplier): ?>
                        <tr>
                            <td><strong><?= e($supplier['company_name']) ?></strong><small class="d-block text-muted"><?= e($supplier['address']) ?></small></td>
                            <td><?= e($supplier['contact_person'] ?: '-') ?></td>
                            <td><?= e($supplier['phone']) ?></td>
                            <td><?= e($supplier['email'] ?: '-') ?></td>
                            <td><?= number_format((int) $supplier['item_count']) ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#supplier<?= (int) $supplier['id'] ?>">Edit</button>
                                <form class="d-inline" method="post" onsubmit="return confirm('Delete supplier?');"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $supplier['id'] ?>"><button class="btn btn-sm btn-outline-danger">Delete</button></form>
                            </td>
                        </tr>
                        <tr class="collapse" id="supplier<?= (int) $supplier['id'] ?>"><td colspan="6">
                            <form method="post" class="row g-2">
                                <input type="hidden" name="id" value="<?= (int) $supplier['id'] ?>">
                                <div class="col-md-3"><input class="form-control" name="company_name" value="<?= e($supplier['company_name']) ?>"></div>
                                <div class="col-md-2"><input class="form-control" name="contact_person" value="<?= e($supplier['contact_person']) ?>"></div>
                                <div class="col-md-2"><input class="form-control" name="phone" value="<?= e($supplier['phone']) ?>"></div>
                                <div class="col-md-2"><input class="form-control" name="email" value="<?= e($supplier['email']) ?>"></div>
                                <div class="col-md-2"><input class="form-control" name="address" value="<?= e($supplier['address']) ?>"></div>
                                <div class="col-md-1"><button class="btn btn-primary w-100">Save</button></div>
                            </form>
                        </td></tr>
                    <?php endforeach; ?>
                    <?php if (!$suppliers): ?><tr><td colspan="6"><div class="empty-state">No suppliers found.</div></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

