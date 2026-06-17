<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/waafi_payment.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$amount      = (float)  ($_POST['amount']      ?? 0);
$accountNo   = trim(    ($_POST['account_no']  ?? ''));
$referenceId = trim(    ($_POST['reference_id'] ?? ''));
$invoiceId   = trim(    ($_POST['invoice_id']   ?? ''));
$description = trim(    ($_POST['description']  ?? 'Hair Clinic Payment'));

if ($amount <= 0 || $accountNo === '' || $referenceId === '' || $invoiceId === '') {
    echo json_encode(['success' => false, 'message' => 'Missing required payment fields.']);
    exit;
}

$waafi  = new WaafiPayment();
$result = $waafi->charge($amount, $accountNo, $referenceId, $invoiceId, $description);

echo json_encode($result);
