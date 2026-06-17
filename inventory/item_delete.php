<?php
require_once __DIR__ . '/../includes/auth.php';
require_access('inventory');

$id = (int) ($_GET['id'] ?? 0);

if ($id > 0) {
    $stmt = $conn->prepare('DELETE FROM inventory_items WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    log_activity('Deleted general inventory item', 'Inventory', $id);
    flash('success', 'Item deleted successfully.');
}

redirect('/inventory/items.php');
