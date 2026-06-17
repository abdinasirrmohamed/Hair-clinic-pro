<?php
error_reporting(E_ALL);
require_once __DIR__ . '/includes/db.php';
\ = 1;
\ = 1;
\ = 'Approved';
\ = 'Test remark';
\ = 'approved_at';

\ = \->prepare("UPDATE appointments SET status=?, remarks=?, $col=NOW() WHERE id=? AND doctor_id=?");
if (!\) { die("Prepare failed: " . \->error); }
\->bind_param('ssii', \, \, \, \);
\ = \->execute();
echo \ ? "Success. Rows affected: " . \->affected_rows : "Failed: " . \->error;
