<?php
require_once __DIR__ . '/../includes/auth.php';
require_access('inventory');

$conn->query("CREATE TABLE IF NOT EXISTS inventory_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(150) NOT NULL,
    category VARCHAR(80) NOT NULL,
    stock_level INT NOT NULL DEFAULT 0,
    unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
    vendor VARCHAR(150) NOT NULL,
    status ENUM('In Stock', 'Low Stock', 'Out of Stock') NOT NULL DEFAULT 'In Stock',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS inventory_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    order_code VARCHAR(30) NOT NULL UNIQUE,
    quantity INT NOT NULL,
    priority VARCHAR(40) NOT NULL DEFAULT 'Normal',
    shipping_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_estimate DECIMAL(10,2) NOT NULL DEFAULT 0,
    order_status ENUM('Pending', 'Shipped', 'Delivered', 'Cancelled') NOT NULL DEFAULT 'Pending',
    note VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE
)");

if (count_table($conn, 'SELECT COUNT(*) FROM inventory_items') === 0) {
    $conn->query("INSERT INTO inventory_items (id, item_name, category, stock_level, unit_price, vendor, status) VALUES
        (1, 'FUE Graft Preservation Solution', 'Surgical', 8, 124.00, 'BioMed Logistics', 'Low Stock'),
        (2, 'Minoxidil 5% Topical Sol.', 'Post-Op', 72, 38.00, 'ClinicSupplies Co.', 'In Stock'),
        (3, 'Nitrile Gloves (Box of 100)', 'General', 45, 18.00, 'ClinicSupplies Co.', 'In Stock'),
        (4, 'Sterile Scalpels #11', 'Surgical', 85, 42.00, 'SurgiTech Global', 'In Stock')");
}

if (count_table($conn, 'SELECT COUNT(*) FROM inventory_orders') === 0) {
    $conn->query("INSERT INTO inventory_orders (id, item_id, order_code, quantity, priority, shipping_cost, total_estimate, order_status, note, created_at) VALUES
        (1, 1, '#ORD-28491', 4, 'Urgent (24h)', 45.00, 665.00, 'Shipped', 'Est. Arrival: Oct 24, 2023', '2023-10-22 09:30:00'),
        (2, 2, '#ORD-28485', 8, 'Normal', 20.00, 324.00, 'Delivered', 'Arrived: Oct 21, 2023', '2023-10-19 14:15:00'),
        (3, 4, '#ORD-28502', 6, 'Normal', 30.00, 282.00, 'Pending', 'Awaiting Supplier', '2023-10-23 10:40:00')");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'confirm_order') {
    $item_id = (int) ($_POST['item_id'] ?? 0);
    $quantity = max(1, (int) ($_POST['quantity'] ?? 1));
    $priority = $_POST['priority'] ?? 'Urgent (24h)';
    $shipping_cost = $priority === 'Urgent (24h)' ? 45.00 : 20.00;

    $stmt = $conn->prepare('SELECT unit_price FROM inventory_items WHERE id = ?');
    $stmt->bind_param('i', $item_id);
    $item = fetch_one($stmt);

    if ($item) {
        $order_code = '#ORD-' . random_int(30000, 99999);
        $total = ((float) $item['unit_price'] * $quantity) + $shipping_cost;
        $note = $priority === 'Urgent (24h)' ? 'Awaiting supplier confirmation' : 'Standard replenishment requested';
        $stmt = $conn->prepare('INSERT INTO inventory_orders (item_id, order_code, quantity, priority, shipping_cost, total_estimate, order_status, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $status = 'Pending';
        $stmt->bind_param('isisddss', $item_id, $order_code, $quantity, $priority, $shipping_cost, $total, $status, $note);
        $stmt->execute();
        flash('success', 'Inventory order created successfully.');
    } else {
        flash('danger', 'Selected inventory item was not found.');
    }

    redirect('/inventory/index.php');
}

$search = trim($_GET['search'] ?? '');
if ($search !== '') {
    $like = '%' . $search . '%';
    $stmt = $conn->prepare('SELECT * FROM inventory_items WHERE item_name LIKE ? OR category LIKE ? OR vendor LIKE ? ORDER BY stock_level ASC, item_name ASC');
    $stmt->bind_param('sss', $like, $like, $like);
    $items = fetch_all($stmt);
} else {
    $items = $conn->query('SELECT * FROM inventory_items ORDER BY stock_level ASC, item_name ASC')->fetch_all(MYSQLI_ASSOC);
}

$selected_item = $conn->query("SELECT * FROM inventory_items WHERE status = 'Low Stock' ORDER BY stock_level ASC LIMIT 1")->fetch_assoc();
$selected_item = $selected_item ?: ($items[0] ?? null);
$orders = $conn->query(
    'SELECT o.*, i.vendor, i.item_name FROM inventory_orders o JOIN inventory_items i ON i.id = o.item_id ORDER BY o.created_at DESC LIMIT 5'
)->fetch_all(MYSQLI_ASSOC);
$total_items = count_table($conn, 'SELECT COUNT(*) FROM inventory_items');
$in_transit = count_table($conn, "SELECT COUNT(*) FROM inventory_orders WHERE order_status IN ('Pending', 'Shipped')");
$critical_alerts = count_table($conn, 'SELECT COUNT(*) FROM inventory_items WHERE stock_level < 10');
$stock_health = $total_items ? round((count_table($conn, 'SELECT COUNT(*) FROM inventory_items WHERE stock_level >= 10') / $total_items) * 100) : 0;

function inventory_initials($name)
{
    $parts = preg_split('/\s+/', trim($name));
    $first = strtoupper(substr($parts[0] ?? '', 0, 1));
    $last = strtoupper(substr($parts[count($parts) - 1] ?? '', 0, 1));
    return $first . ($last !== $first ? $last : '');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inventory & Orders</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/css/style.css" rel="stylesheet">
</head>
<body class="dashboard-shell">
    <?php render_role_sidebar($_SERVER['SCRIPT_NAME'] ?? ''); ?>

    <section class="clinic-main">
        <header class="clinic-topbar inventory-topbar">
            <h1>Inventory Management</h1>
            <form class="top-search inventory-search" method="get">
                <i class="bi bi-search"></i>
                <input name="search" value="<?= e($search) ?>" placeholder="Search supplies...">
            </form>
            <div class="top-actions admin-profile">
                <button type="button" aria-label="Notifications"><i class="bi bi-bell"></i></button>
                <button type="button" aria-label="Help"><i class="bi bi-question-circle"></i></button>
                <span class="profile-divider"></span>
                <div class="admin-avatar"><?= e(inventory_initials($_SESSION['admin_name'] ?? 'Admin User')) ?></div>
                <span class="admin-copy"><strong><?= e($_SESSION['admin_name'] ?? 'Admin User') ?></strong><small><?= e(current_role()) ?></small></span>
            </div>
        </header>

        <main class="clinic-content inventory-content">
            <div class="inventory-breadcrumb"><a href="<?= BASE_URL ?>/dashboard.php">Dashboard</a><i class="bi bi-chevron-right"></i><span>Inventory</span></div>
            <h2 class="inventory-title">Inventory & Orders</h2>

            <section class="stock-warning">
                <span><i class="bi bi-exclamation-triangle"></i></span>
                <div>
                    <h3>Critical Low Stock Warning</h3>
                    <p>The 'FUE Graft Preservation Solution' has reached 8% capacity. Immediate replenishment is required to prevent surgery delays.</p>
                </div>
                <a href="#quick-order">Order Now</a>
            </section>

            <div class="inventory-layout">
                <section class="stock-card">
                    <div class="stock-card-head">
                        <h3>Stock Management</h3>
                        <div><button>Filter</button><button>Export</button></div>
                    </div>
                    <div class="stock-table">
                        <div class="stock-table-head"><span>Item Name</span><span>Category</span><span>Stock Level</span><span>Status</span><span>Action</span></div>
                        <?php foreach ($items as $item): ?>
                            <div class="stock-row <?= (int) $item['stock_level'] < 10 ? 'critical' : '' ?>">
                                <strong><?= e($item['item_name']) ?></strong>
                                <span class="stock-category"><?= e($item['category']) ?></span>
                                <span class="stock-level"><i><b style="width: <?= (int) $item['stock_level'] ?>%"></b></i><?= (int) $item['stock_level'] ?>%</span>
                                <em><?= e($item['status']) ?></em>
                                <a href="index.php?search=<?= urlencode($item['item_name']) ?>#quick-order">Edit</a>
                            </div>
                        <?php endforeach; ?>
                        <?php if (!$items): ?><div class="empty-state">No inventory items found.</div><?php endif; ?>
                    </div>
                </section>

                <aside class="quick-order" id="quick-order">
                    <section class="quick-order-card">
                        <div class="quick-head"><span><i class="bi bi-cart3"></i></span><div><h3>Quick Order</h3><p>Fast replenishment</p></div></div>
                        <?php if ($selected_item): ?>
                            <form method="post">
                                <input type="hidden" name="action" value="confirm_order">
                                <input type="hidden" name="item_id" value="<?= (int) $selected_item['id'] ?>">
                                <h4>Selected Item</h4>
                                <div class="selected-item"><strong><?= e($selected_item['item_name']) ?></strong><span><?= (int) $selected_item['stock_level'] ?>%<small>left</small></span></div>
                                <div class="order-controls">
                                    <div><h4>Quantity</h4><div class="qty-control"><button type="button">-</button><input name="quantity" type="number" min="1" value="4"><button type="button">+</button></div></div>
                                    <div><h4>Priority</h4><select class="priority-btn" name="priority"><option>Urgent (24h)</option><option>Normal</option></select></div>
                                </div>
                                <div class="order-total">
                                    <span>Unit Price <strong>$<?= number_format((float) $selected_item['unit_price'], 2) ?></strong></span>
                                    <span>Shipping (Express) <strong>$45.00</strong></span>
                                    <b>Total Est. <strong>$<?= number_format(((float) $selected_item['unit_price'] * 4) + 45, 2) ?></strong></b>
                                </div>
                                <button class="confirm-order" type="submit">Confirm Order</button>
                            </form>
                        <?php else: ?>
                            <div class="empty-state">No inventory item selected.</div>
                        <?php endif; ?>
                    </section>

                    <section class="tracking-card">
                        <h3>Order Tracking</h3>
                        <?php foreach ($orders as $order): ?>
                            <article>
                                <div><strong><?= e($order['order_code']) ?></strong><em><?= e($order['order_status']) ?></em></div>
                                <p><?= e($order['vendor']) ?></p>
                                <small><i class="bi bi-clock"></i><?= e($order['note']) ?></small>
                            </article>
                        <?php endforeach; ?>
                        <a href="#">View All Orders</a>
                    </section>
                </aside>
            </div>

            <div class="inventory-stats">
                <article><span class="stock-health"><?= $stock_health ?>%</span><div><p>Total Stock Health</p><strong><?= number_format($total_items) ?> items active</strong></div></article>
                <article><span class="metric-icon blue"><i class="bi bi-truck"></i></span><div><p>In Transit</p><strong><?= number_format($in_transit) ?> Active Deliveries</strong></div></article>
                <article><span class="metric-icon pale-red"><i class="bi bi-exclamation-lg"></i></span><div><p>Critical Alerts</p><strong><?= number_format($critical_alerts) ?> Item Below 10%</strong></div></article>
            </div>
        </main>
    </section>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
