<?php
require_once __DIR__ . '/../includes/db.php';

function first_id(mysqli $conn, string $sql): int
{
    return (int) ($conn->query($sql)->fetch_row()[0] ?? 0);
}

function scalar(mysqli $conn, string $sql): int
{
    return (int) ($conn->query($sql)->fetch_row()[0] ?? 0);
}

function ensure_demo_patient(mysqli $conn, int $i, array $doctor_user_ids): int
{
    $phone = '+25261700' . str_pad((string) $i, 3, '0', STR_PAD_LEFT);
    $stmt = $conn->prepare('SELECT id FROM patients WHERE phone = ? LIMIT 1');
    $stmt->bind_param('s', $phone);
    $row = fetch_one($stmt);
    if ($row) {
        return (int) $row['id'];
    }

    $first_names = ['Ahmed', 'Amina', 'Hassan', 'Maryan', 'Yusuf', 'Fadumo', 'Khalid', 'Sahra', 'Omar', 'Hodan'];
    $last_names = ['Ali', 'Hassan', 'Nur', 'Osman', 'Abdi', 'Mohamed', 'Jama', 'Farah', 'Ibrahim', 'Warsame'];
    $full_name = $first_names[$i % count($first_names)] . ' ' . $last_names[$i % count($last_names)] . ' Demo ' . $i;
    $email = 'demo.patient' . $i . '@hairclinic.test';
    $gender = $i % 2 === 0 ? 'Male' : 'Female';
    $dob = date('Y-m-d', strtotime('-' . (22 + ($i % 32)) . ' years'));
    $address = 'Demo District ' . (($i % 8) + 1) . ', Mogadishu';
    $notes = 'Demo seed patient with scalp history, consultation notes, and follow-up requirements.';
    $assigned = $doctor_user_ids[$i % count($doctor_user_ids)] ?? null;

    $stmt = $conn->prepare('INSERT INTO patients (full_name, phone, email, gender, date_of_birth, address, medical_notes, assigned_doctor_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, DATE_SUB(NOW(), INTERVAL ? DAY))');
    $days_ago = $i % 45;
    $stmt->bind_param('sssssssii', $full_name, $phone, $email, $gender, $dob, $address, $notes, $assigned, $days_ago);
    $stmt->execute();
    return (int) $conn->insert_id;
}

function status_for(int $i): string
{
    $statuses = ['Pending', 'Approved', 'Completed', 'Rejected', 'Cancelled'];
    return $statuses[$i % count($statuses)];
}

$doctor_ids = [];
$doctor_user_ids = [];
$result = $conn->query("SELECT id, user_id FROM doctors WHERE status = 'Active' ORDER BY id");
while ($row = $result->fetch_assoc()) {
    $doctor_ids[] = (int) $row['id'];
    if (!empty($row['user_id'])) {
        $doctor_user_ids[] = (int) $row['user_id'];
    }
}

if (!$doctor_ids) {
    throw new RuntimeException('No active doctors found. Add a doctor before seeding demo data.');
}

if (!$doctor_user_ids) {
    $doctor_user_ids[] = first_id($conn, "SELECT id FROM users WHERE role = 'Doctor' ORDER BY id LIMIT 1");
}

$user_rows = $conn->query('SELECT id, full_name, role FROM users ORDER BY id')->fetch_all(MYSQLI_ASSOC);
$medicine_rows = $conn->query('SELECT id, unit_price FROM medicines ORDER BY id')->fetch_all(MYSQLI_ASSOC);
$inventory_item_ids = $conn->query('SELECT id FROM inventory_items ORDER BY id')->fetch_all(MYSQLI_ASSOC);

$patient_ids = [];
for ($i = 1; $i <= 50; $i++) {
    $patient_ids[] = ensure_demo_patient($conn, $i, $doctor_user_ids);
}

if (scalar($conn, "SELECT COUNT(*) FROM appointments WHERE notes LIKE 'Demo seed appointment%'") < 50) {
    for ($i = 1; $i <= 50; $i++) {
        $patient_id = $patient_ids[($i - 1) % count($patient_ids)];
        $doctor_id = $doctor_ids[($i - 1) % count($doctor_ids)];
        $date = date('Y-m-d', strtotime(($i - 25) . ' days'));
        $time = sprintf('%02d:%02d:00', 8 + ($i % 8), ($i % 2) * 30);
        $reason = ['Initial Consultation', 'PRP Review', 'FUE Follow-up', 'Laser Therapy', 'Medication Review'][$i % 5] . ' #' . $i;
        $status = status_for($i);
        $notes = 'Demo seed appointment #' . $i . ' for workflow testing.';
        $remarks = $status === 'Rejected' ? 'Doctor rejected this demo request.' : ($status === 'Completed' ? 'Demo visit completed.' : '');
        $approved_at = $status === 'Approved' || $status === 'Completed' ? date('Y-m-d H:i:s', strtotime($date . ' ' . $time)) : null;
        $rejected_at = $status === 'Rejected' ? date('Y-m-d H:i:s', strtotime($date . ' ' . $time)) : null;
        $completed_at = $status === 'Completed' ? date('Y-m-d H:i:s', strtotime($date . ' ' . $time . ' +30 minutes')) : null;

        $stmt = $conn->prepare('INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, reason, status, notes, remarks, approved_at, rejected_at, completed_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_SUB(NOW(), INTERVAL ? DAY))');
        $days_ago = max(0, 50 - $i);
        $stmt->bind_param('iisssssssssi', $patient_id, $doctor_id, $date, $time, $reason, $status, $notes, $remarks, $approved_at, $rejected_at, $completed_at, $days_ago);
        $stmt->execute();
    }
}

if (scalar($conn, "SELECT COUNT(*) FROM treatments WHERE notes LIKE 'Demo seed treatment%'") < 50) {
    for ($i = 1; $i <= 50; $i++) {
        $patient_id = $patient_ids[($i - 1) % count($patient_ids)];
        $name = ['FUE Hair Restoration', 'PRP Treatment', 'Laser Therapy', 'Scalp Consultation', 'Post-op Review'][$i % 5];
        $date = date('Y-m-d', strtotime(($i - 40) . ' days'));
        $progress = ['Started', 'In Progress', 'Completed'][$i % 3];
        $cost = 75 + (($i % 12) * 125);
        $notes = 'Demo seed treatment #' . $i . ' with progress and cost data.';
        $stmt = $conn->prepare('INSERT INTO treatments (patient_id, treatment_name, treatment_date, progress, cost, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, DATE_SUB(NOW(), INTERVAL ? DAY))');
        $days_ago = max(0, 45 - $i);
        $stmt->bind_param('isssdsi', $patient_id, $name, $date, $progress, $cost, $notes, $days_ago);
        $stmt->execute();
    }
}

$appointment_ids = $conn->query("SELECT id, patient_id FROM appointments ORDER BY id DESC LIMIT 60")->fetch_all(MYSQLI_ASSOC);
if (scalar($conn, "SELECT COUNT(*) FROM payments WHERE notes LIKE 'Demo seed payment%'") < 50) {
    for ($i = 1; $i <= 50; $i++) {
        $appointment = $appointment_ids[($i - 1) % count($appointment_ids)];
        $patient_id = (int) $appointment['patient_id'];
        $appointment_id = (int) $appointment['id'];
        $amount = 25 + (($i % 18) * 35);
        $method = ['Cash', 'EVC Plus', 'Sahal', 'Bank Transfer'][$i % 4];
        $status = ['Paid', 'Paid', 'Partial', 'Outstanding'][$i % 4];
        $reference = 'DEMO-PAY-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT);
        $notes = 'Demo seed payment #' . $i . ' for revenue testing.';
        $created_by = (int) ($user_rows[$i % count($user_rows)]['id'] ?? 1);
        $stmt = $conn->prepare('INSERT INTO payments (patient_id, appointment_id, amount, payment_method, payment_status, reference_number, notes, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, DATE_SUB(NOW(), INTERVAL ? DAY))');
        $days_ago = $i % 50;
        $stmt->bind_param('iidssssii', $patient_id, $appointment_id, $amount, $method, $status, $reference, $notes, $created_by, $days_ago);
        $stmt->execute();
        $payment_id = (int) $conn->insert_id;
        $receipt_number = 'DEMO-RCP-' . str_pad((string) $payment_id, 5, '0', STR_PAD_LEFT);
        $receipt = $conn->prepare('INSERT IGNORE INTO receipts (payment_id, receipt_number) VALUES (?, ?)');
        $receipt->bind_param('is', $payment_id, $receipt_number);
        $receipt->execute();
    }
}

$treatment_ids = $conn->query('SELECT id, patient_id FROM treatments ORDER BY id DESC LIMIT 60')->fetch_all(MYSQLI_ASSOC);
if (scalar($conn, "SELECT COUNT(*) FROM followups WHERE result LIKE 'Demo seed follow-up%'") < 50) {
    for ($i = 1; $i <= 50; $i++) {
        $treatment = $treatment_ids[($i - 1) % count($treatment_ids)];
        $patient_id = (int) $treatment['patient_id'];
        $treatment_id = (int) $treatment['id'];
        $date = date('Y-m-d', strtotime(($i - 20) . ' days'));
        $result_text = 'Demo seed follow-up #' . $i . ' showing scheduled clinical tracking.';
        $status = ['Scheduled', 'Done', 'Missed'][$i % 3];
        $stmt = $conn->prepare('INSERT INTO followups (patient_id, treatment_id, followup_date, result, status, created_at) VALUES (?, ?, ?, ?, ?, DATE_SUB(NOW(), INTERVAL ? DAY))');
        $days_ago = $i % 30;
        $stmt->bind_param('iisssi', $patient_id, $treatment_id, $date, $result_text, $status, $days_ago);
        $stmt->execute();
    }
}

if ($medicine_rows && scalar($conn, "SELECT COUNT(*) FROM pharmacy_sales WHERE sale_number LIKE 'DEMO-SALE-%'") < 50) {
    for ($i = 1; $i <= 50; $i++) {
        $patient_id = $patient_ids[($i - 1) % count($patient_ids)];
        $sale_number = 'DEMO-SALE-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT);
        $customer = 'Demo Customer ' . $i;
        $medicine_count = 1 + ($i % 3);
        $subtotal = 0.0;
        $items = [];
        for ($j = 0; $j < $medicine_count; $j++) {
            $medicine = $medicine_rows[($i + $j) % count($medicine_rows)];
            $quantity = 1 + (($i + $j) % 2);
            $line = (float) $medicine['unit_price'] * $quantity;
            $subtotal += $line;
            $items[] = [(int) $medicine['id'], $quantity, (float) $medicine['unit_price'], $line];
        }
        $discount_type = $i % 5 === 0 ? 'Fixed' : 'None';
        $discount_value = $discount_type === 'Fixed' ? 5.00 : 0.00;
        $discount_amount = min($discount_value, $subtotal);
        $tax_percent = 0.00;
        $tax_amount = 0.00;
        $total = $subtotal - $discount_amount;
        $method = ['Cash', 'EVC Plus', 'Sahal', 'Bank Transfer'][$i % 4];
        $payment_status = 'Paid';
        $status = $i % 17 === 0 ? 'Returned' : 'Paid';
        $notes = 'Demo seed pharmacy sale #' . $i . '.';
        $created_by = (int) ($user_rows[$i % count($user_rows)]['id'] ?? 1);
        $prescription_id = null;

        $stmt = $conn->prepare('INSERT INTO pharmacy_sales (sale_number, customer_name, patient_id, prescription_id, medicine_count, subtotal, discount_type, discount_value, discount_amount, tax_percent, tax_amount, total_amount, payment_method, payment_status, status, notes, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_SUB(NOW(), INTERVAL ? DAY))');
        $days_ago = $i % 45;
        $stmt->bind_param('ssiiidsdddddssssii', $sale_number, $customer, $patient_id, $prescription_id, $medicine_count, $subtotal, $discount_type, $discount_value, $discount_amount, $tax_percent, $tax_amount, $total, $method, $payment_status, $status, $notes, $created_by, $days_ago);
        $stmt->execute();
        $sale_id = (int) $conn->insert_id;

        foreach ($items as $item) {
            $line_stmt = $conn->prepare('INSERT INTO pharmacy_sale_medicines (sale_id, medicine_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)');
            $line_stmt->bind_param('iiidd', $sale_id, $item[0], $item[1], $item[2], $item[3]);
            $line_stmt->execute();
        }
    }
}

if ($medicine_rows && scalar($conn, "SELECT COUNT(*) FROM prescriptions WHERE prescription_number LIKE 'DEMO-RX-%'") < 50) {
    for ($i = 1; $i <= 50; $i++) {
        $number = 'DEMO-RX-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT);
        $patient_id = $patient_ids[($i - 1) % count($patient_ids)];
        $doctor_id = $doctor_ids[($i - 1) % count($doctor_ids)];
        $date = date('Y-m-d', strtotime('-' . ($i % 40) . ' days'));
        $status = ['Pending', 'Dispensed', 'Completed'][$i % 3];
        $instructions = 'Demo seed prescription #' . $i . ' for pharmacy workflow.';
        $stmt = $conn->prepare('INSERT INTO prescriptions (prescription_number, patient_id, doctor_id, prescription_date, status, instructions, created_at) VALUES (?, ?, ?, ?, ?, ?, DATE_SUB(NOW(), INTERVAL ? DAY))');
        $days_ago = $i % 40;
        $stmt->bind_param('siisssi', $number, $patient_id, $doctor_id, $date, $status, $instructions, $days_ago);
        $stmt->execute();
        $prescription_id = (int) $conn->insert_id;

        for ($j = 0; $j < 2; $j++) {
            $medicine = $medicine_rows[($i + $j) % count($medicine_rows)];
            $qty = 1 + (($i + $j) % 2);
            $line_note = 'Demo dosage instruction.';
            $line = $conn->prepare('INSERT INTO prescription_medicines (prescription_id, medicine_id, quantity, instructions) VALUES (?, ?, ?, ?)');
            $medicine_id = (int) $medicine['id'];
            $line->bind_param('iiis', $prescription_id, $medicine_id, $qty, $line_note);
            $line->execute();
        }
    }
}

if (scalar($conn, "SELECT COUNT(*) FROM expenses WHERE description LIKE 'Demo seed expense%'") < 50) {
    $categories = ['Staff Salaries', 'Medical Supplies', 'Medicine Purchases', 'Rent', 'Electricity', 'Water', 'Internet', 'Equipment Maintenance', 'Other Expenses'];
    for ($i = 1; $i <= 50; $i++) {
        $category = $categories[$i % count($categories)];
        $date = date('Y-m-d', strtotime('-' . ($i % 45) . ' days'));
        $amount = 40 + (($i % 16) * 55);
        $vendor = 'Demo Vendor ' . (($i % 12) + 1);
        $description = 'Demo seed expense #' . $i . ' for financial reporting.';
        $created_by = (int) ($user_rows[$i % count($user_rows)]['id'] ?? 1);
        $receipt = null;
        $stmt = $conn->prepare('INSERT INTO expenses (category, expense_date, amount, vendor, description, receipt_path, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, DATE_SUB(NOW(), INTERVAL ? DAY))');
        $days_ago = $i % 45;
        $stmt->bind_param('ssdsssii', $category, $date, $amount, $vendor, $description, $receipt, $created_by, $days_ago);
        $stmt->execute();
    }
}

if ($inventory_item_ids && scalar($conn, "SELECT COUNT(*) FROM inventory_orders WHERE note LIKE 'Demo seed order%'") < 50) {
    for ($i = 1; $i <= 50; $i++) {
        $item_id = (int) $inventory_item_ids[($i - 1) % count($inventory_item_ids)]['id'];
        $order_code = '#DEMO-ORD-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT);
        $quantity = 2 + ($i % 12);
        $priority = $i % 4 === 0 ? 'Urgent (24h)' : 'Normal';
        $shipping = $priority === 'Urgent (24h)' ? 45.00 : 20.00;
        $total = 100 + ($quantity * 25) + $shipping;
        $status = ['Pending', 'Shipped', 'Delivered', 'Cancelled'][$i % 4];
        $note = 'Demo seed order #' . $i . ' for inventory tracking.';
        $stmt = $conn->prepare('INSERT IGNORE INTO inventory_orders (item_id, order_code, quantity, priority, shipping_cost, total_estimate, order_status, note, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, DATE_SUB(NOW(), INTERVAL ? DAY))');
        $days_ago = $i % 35;
        $stmt->bind_param('isisddssi', $item_id, $order_code, $quantity, $priority, $shipping, $total, $status, $note, $days_ago);
        $stmt->execute();
    }
}

if (scalar($conn, "SELECT COUNT(*) FROM audit_logs WHERE action LIKE 'Demo activity%'") < 250) {
    $modules = ['Patients', 'Appointments', 'Doctor Schedule', 'Payments', 'Pharmacy', 'Inventory', 'Finance', 'Reports'];
    $actions = ['Demo activity created record', 'Demo activity updated record', 'Demo activity approved workflow', 'Demo activity generated report', 'Demo activity processed transaction'];
    for ($i = 1; $i <= 250; $i++) {
        $user = $user_rows[($i - 1) % count($user_rows)];
        $user_id = (int) $user['id'];
        $user_name = $user['full_name'];
        $user_role = $user['role'];
        $action = $actions[$i % count($actions)];
        $module = $modules[$i % count($modules)];
        $record = 'DEMO-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT);
        $ip = '127.0.0.' . (($i % 200) + 1);
        $stmt = $conn->prepare('INSERT INTO audit_logs (user_id, user_name, user_role, action, module_name, record_id, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, DATE_SUB(NOW(), INTERVAL ? HOUR))');
        $hours_ago = $i % 240;
        $stmt->bind_param('issssssi', $user_id, $user_name, $user_role, $action, $module, $record, $ip, $hours_ago);
        $stmt->execute();
    }
}

$summary = [
    'patients' => scalar($conn, 'SELECT COUNT(*) FROM patients'),
    'appointments' => scalar($conn, 'SELECT COUNT(*) FROM appointments'),
    'treatments' => scalar($conn, 'SELECT COUNT(*) FROM treatments'),
    'followups' => scalar($conn, 'SELECT COUNT(*) FROM followups'),
    'payments' => scalar($conn, 'SELECT COUNT(*) FROM payments'),
    'pharmacy_sales' => scalar($conn, 'SELECT COUNT(*) FROM pharmacy_sales'),
    'prescriptions' => scalar($conn, 'SELECT COUNT(*) FROM prescriptions'),
    'expenses' => scalar($conn, 'SELECT COUNT(*) FROM expenses'),
    'inventory_orders' => scalar($conn, 'SELECT COUNT(*) FROM inventory_orders'),
    'audit_logs' => scalar($conn, 'SELECT COUNT(*) FROM audit_logs'),
];

foreach ($summary as $table => $count) {
    echo str_pad($table, 18) . $count . PHP_EOL;
}
