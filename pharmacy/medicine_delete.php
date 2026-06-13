<?php
require_once __DIR__ . '/../includes/auth.php';
require_access('pharmacy');
require_roles(['Administrator', 'Inventory Officer']);

$id = (int) ($_GET['id'] ?? 0);
$stmt = $conn->prepare('DELETE FROM medicines WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
log_activity('Deleted medicine inventory', 'Pharmacy', $id);
flash('success', 'Medicine deleted successfully.');
redirect('/pharmacy/medicines.php');
