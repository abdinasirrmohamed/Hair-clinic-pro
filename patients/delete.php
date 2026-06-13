<?php
require_once __DIR__ . '/../includes/auth.php';
require_access('patients');
require_roles(['Administrator']);
$id = (int) ($_GET['id'] ?? 0);
$stmt = $conn->prepare('DELETE FROM patients WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
log_activity('Deleted patient record', 'Patients', $id);
flash('success', 'Patient deleted successfully.');
redirect('/patients/view.php');
