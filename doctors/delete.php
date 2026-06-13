<?php
require_once __DIR__ . '/../includes/auth.php';
require_access('doctors');

$id = (int) ($_GET['id'] ?? 0);
$stmt = $conn->prepare('DELETE FROM doctors WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
log_activity('Deleted doctor record', 'Doctors', $id);

flash('success', 'Doctor deleted successfully.');
redirect('/doctors/view.php');
