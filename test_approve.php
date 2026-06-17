<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Mock session and include
session_start();
\['admin_id'] = 3; // Dr. Sarah Jenkins
\['admin_role'] = 'Doctor';

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// Test Approve
\['REQUEST_METHOD'] = 'POST';
\['action'] = 'status';
\['status'] = 'Approved';
\['appointment_id'] = 1;
\['remarks'] = 'Looking good!';

// Copy the logic from view.php
\ = 3;
\ = \->query("SELECT * FROM doctors WHERE user_id = \ LIMIT 1")->fetch_assoc();
\ = (int) (\['id'] ?? 0);

\ = \['status'];
\ = \['remarks'];
\ = \['appointment_id'];

\ = 'approved_at';
\ = \->prepare("UPDATE appointments SET status=?, remarks=?, $col=NOW() WHERE id=? AND doctor_id=?");
\->bind_param('ssii', \, \, \, \);
\ = \->execute();
\ = \->affected_rows;

echo "Result: " . (\ ? 'Success' : 'Fail') . "\n";
echo "Affected Rows: \\n";
echo "Doctor ID: \\n";
echo "Error: " . \->error . "\n";

