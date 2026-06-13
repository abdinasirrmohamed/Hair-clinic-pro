<?php
require_once __DIR__ . '/../includes/auth.php';
require_access('appointments');
require_roles(['Administrator', 'Receptionist']);
$id = (int) ($_GET['id'] ?? 0);
$stmt = $conn->prepare("UPDATE appointments SET status = 'Cancelled' WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
flash('success', 'Appointment cancelled successfully.');
redirect('/appointments/view.php');
