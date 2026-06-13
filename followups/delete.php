<?php
require_once __DIR__ . '/../includes/auth.php';
require_access('followups');
require_roles(['Administrator', 'Doctor']);
$id = (int) ($_GET['id'] ?? 0);

$stmt = $conn->prepare('SELECT patient_id FROM followups WHERE id = ?');
$stmt->bind_param('i', $id);
$followup = fetch_one($stmt);
if ($followup) {
    require_patient_assignment((int) $followup['patient_id']);
}

$stmt = $conn->prepare('DELETE FROM followups WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
log_activity('Deleted follow-up record', 'Follow-Ups', $id);
flash('success', 'Follow-up deleted successfully.');
redirect('/followups/view.php');
