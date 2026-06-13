<?php
require_once __DIR__ . '/../includes/auth.php';
require_access('treatments');
require_roles(['Administrator', 'Doctor']);
$id = (int) ($_GET['id'] ?? 0);

$stmt = $conn->prepare('SELECT patient_id FROM treatments WHERE id = ?');
$stmt->bind_param('i', $id);
$treatment = fetch_one($stmt);
if ($treatment) {
    require_patient_assignment((int) $treatment['patient_id']);
}

$stmt = $conn->prepare('DELETE FROM treatments WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
flash('success', 'Treatment record deleted successfully.');
redirect('/treatments/view.php');
