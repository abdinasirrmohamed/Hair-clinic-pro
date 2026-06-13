<?php
require_once __DIR__ . '/../includes/auth.php';
require_access('dashboard');

header('Content-Type: application/json');

$revenue_today = (float) $conn->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payment_status IN ('Paid','Partial') AND DATE(created_at) = CURDATE()")->fetch_row()[0]
    + (float) $conn->query("SELECT COALESCE(SUM(total_amount), 0) FROM pharmacy_sales WHERE status <> 'Returned' AND DATE(created_at) = CURDATE()")->fetch_row()[0];
$revenue_month = (float) $conn->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payment_status IN ('Paid','Partial') AND YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())")->fetch_row()[0]
    + (float) $conn->query("SELECT COALESCE(SUM(total_amount), 0) FROM pharmacy_sales WHERE status <> 'Returned' AND YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())")->fetch_row()[0]
    + (float) $conn->query("SELECT COALESCE(SUM(cost), 0) FROM treatments WHERE YEAR(treatment_date) = YEAR(CURDATE()) AND MONTH(treatment_date) = MONTH(CURDATE())")->fetch_row()[0];
$expenses_month = (float) $conn->query("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE YEAR(expense_date) = YEAR(CURDATE()) AND MONTH(expense_date) = MONTH(CURDATE())")->fetch_row()[0];
$activity_today = count_table($conn, 'SELECT COUNT(*) FROM audit_logs WHERE DATE(created_at) = CURDATE()');
$doctor_scope = doctor_patient_filter('p');
$doctor_pending = count_table($conn, "SELECT COUNT(*) FROM appointments a JOIN patients p ON p.id = a.patient_id WHERE a.status = 'Pending' $doctor_scope");
$doctor_approved = count_table($conn, "SELECT COUNT(*) FROM appointments a JOIN patients p ON p.id = a.patient_id WHERE a.status = 'Approved' $doctor_scope");
$doctor_completed = count_table($conn, "SELECT COUNT(*) FROM appointments a JOIN patients p ON p.id = a.patient_id WHERE a.status = 'Completed' $doctor_scope");

echo json_encode([
    'metrics' => [
        'Revenue Today' => '$' . number_format($revenue_today, 2),
        'Total Revenue Today' => '$' . number_format($revenue_today, 2),
        'Total Revenue This Month' => '$' . number_format($revenue_month, 2),
        'Monthly Expenses' => '$' . number_format($expenses_month, 2),
        'Net Profit This Month' => '$' . number_format($revenue_month - $expenses_month, 2),
        'Activities Today' => number_format($activity_today),
        'Pending Approvals' => number_format($doctor_pending),
        'Approved Appointments' => number_format($doctor_approved),
        'Completed Appointments' => number_format($doctor_completed),
    ],
]);
