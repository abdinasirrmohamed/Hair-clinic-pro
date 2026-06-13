<?php
require_once __DIR__ . '/../includes/auth.php';
require_access('pharmacy');

$id = (int) ($_GET['id'] ?? 0);
$stmt = $conn->prepare('DELETE FROM medicines WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
flash('success', 'Medicine deleted successfully.');
redirect('/pharmacy/medicines.php');
