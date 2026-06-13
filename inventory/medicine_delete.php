<?php
require_once __DIR__ . '/../includes/auth.php';
require_access('inventory');
$id = (int) ($_GET['id'] ?? 0);
$stmt = $conn->prepare('DELETE FROM medicines WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
log_activity('Deleted inventory item', 'Inventory', $id);
flash('success', 'Inventory item deleted.');
redirect('/inventory/medicines.php');
