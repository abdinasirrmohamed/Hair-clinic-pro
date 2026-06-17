<?php
require_once __DIR__ . '/../includes/auth.php';
require_access('inventory');
$page_title = 'General Inventory Items';
require_once __DIR__ . '/../includes/header.php';

$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');
$status = trim($_GET['status'] ?? '');
$where = 'WHERE 1=1';
$types = '';
$params = [];

if ($search !== '') {
    $like = '%' . $search . '%';
    $where .= ' AND (item_name LIKE ? OR vendor LIKE ?)';
    $types .= 'ss';
    array_push($params, $like, $like);
}
if ($category !== '') {
    $where .= ' AND category = ?';
    $types .= 's';
    $params[] = $category;
}
if ($status !== '') {
    $where .= ' AND status = ?';
    $types .= 's';
    $params[] = $status;
}

$stmt = $conn->prepare("SELECT * FROM inventory_items $where ORDER BY item_name");
if ($types !== '') {
    bind_params($stmt, $types, $params);
}
$items = fetch_all($stmt);

// Categories can be dynamic or static. Let's use distinct categories from DB + some defaults.
$categories = $conn->query("SELECT DISTINCT category FROM inventory_items WHERE category != ''")->fetch_all(MYSQLI_ASSOC);
$cat_list = array_column($categories, 'category');
if (empty($cat_list)) {
    $cat_list = ['Office Supplies', 'Cleaning Supplies', 'General Equipment', 'IT Equipment', 'Furniture'];
}
?>

<div class="patient-head">
    <div>
        <h1>General Inventory Items</h1>
        <p>Manage non-medical clinic inventory: equipment, office supplies, and furniture.</p>
    </div>
    <a class="add-patient-btn" href="item_form.php"><i class="bi bi-plus-lg"></i>Add New Item</a>
</div>

<section class="patient-management-card">
    <div class="patient-tabs">
        <div class="tab-links">
            <a class="active" href="items.php">General Items</a>
            <a href="medicines.php">Medicines</a>
            <a href="purchase.php">Purchase</a>
            <a href="stock_in.php">Stock In</a>
            <a href="stock_out.php">Stock Out</a>
            <a href="suppliers.php">Suppliers</a>
            <a href="movements.php">Movements</a>
            <a href="reports.php">Reports</a>
        </div>
    </div>

    <form class="appointment-list-toolbar m-4" method="get">
        <label class="appointment-search-box">
            <i class="bi bi-search"></i>
            <input name="search" value="<?= e($search) ?>" placeholder="Search item name or vendor...">
        </label>
        <select name="category">
            <option value="">All Categories</option>
            <?php foreach ($cat_list as $cat): ?>
                <option value="<?= e($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="status">
            <option value="">All Statuses</option>
            <option value="In Stock" <?= $status === 'In Stock' ? 'selected' : '' ?>>In Stock</option>
            <option value="Low Stock" <?= $status === 'Low Stock' ? 'selected' : '' ?>>Low Stock</option>
            <option value="Out of Stock" <?= $status === 'Out of Stock' ? 'selected' : '' ?>>Out of Stock</option>
        </select>
        <button><i class="bi bi-funnel"></i>Filter</button>
    </form>

    <div class="table-responsive p-4 pt-0">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Item Name</th>
                    <th>Category</th>
                    <th>Stock Level</th>
                    <th>Unit Price</th>
                    <th>Total Value</th>
                    <th>Vendor</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <?php 
                        $status_class = '';
                        if ($item['status'] === 'Out of Stock') $status_class = 'bg-danger text-white';
                        elseif ($item['status'] === 'Low Stock') $status_class = 'bg-warning text-dark';
                        else $status_class = 'bg-success text-white';
                    ?>
                    <tr>
                        <td><strong><?= e($item['item_name']) ?></strong></td>
                        <td><?= e($item['category']) ?></td>
                        <td><?= number_format((int) $item['stock_level']) ?></td>
                        <td>$<?= number_format((float) $item['unit_price'], 2) ?></td>
                        <td>$<?= number_format((float) $item['unit_price'] * (int) $item['stock_level'], 2) ?></td>
                        <td><?= e($item['vendor'] ?: '-') ?></td>
                        <td><span class="badge <?= $status_class ?>"><?= e($item['status']) ?></span></td>
                        <td class="patient-actions">
                            <a href="item_form.php?id=<?= (int) $item['id'] ?>"><i class="bi bi-pencil-square"></i></a>
                            <a href="item_delete.php?id=<?= (int) $item['id'] ?>" onclick="return confirm('Delete this inventory item?')"><i class="bi bi-trash"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$items): ?>
                    <tr><td colspan="8"><div class="empty-state">No general inventory items found.</div></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
