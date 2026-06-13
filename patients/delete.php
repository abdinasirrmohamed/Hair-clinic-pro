<?php
require_once __DIR__ . '/../includes/auth.php';
require_access('patients');
require_roles(['Administrator']);
$id = (int) ($_GET['id'] ?? 0);
$stmt = $conn->prepare('DELETE FROM patients WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
flash('success', 'Patient deleted successfully.');
redirect('/patients/view.php');
