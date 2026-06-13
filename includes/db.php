<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'hair_clinic_system';

if (!function_exists('mysqli_report') || !class_exists('mysqli')) {
    die('The mysqli PHP extension is required. Enable mysqli in php.ini and restart Apache/MySQL in XAMPP.');
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($host, $user, $pass, $dbname);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    die('Database connection failed. Import database.sql and confirm MySQL is running in XAMPP.');
}

function ensure_column($conn, $table, $column, $definition)
{
    $stmt = $conn->prepare(
        'SELECT COUNT(*) total FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if ((int) $row['total'] === 0) {
        $conn->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}

function ensure_index($conn, $table, $index, $definition)
{
    $stmt = $conn->prepare(
        'SELECT COUNT(*) total FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
    );
    $stmt->bind_param('ss', $table, $index);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if ((int) $row['total'] === 0) {
        $conn->query("ALTER TABLE `$table` ADD INDEX `$index` ($definition)");
    }
}

function table_exists($conn, $table)
{
    $stmt = $conn->prepare(
        'SELECT COUNT(*) total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    return (int) $row['total'] > 0;
}

if (table_exists($conn, 'admins') && !table_exists($conn, 'users')) {
    $conn->query('RENAME TABLE admins TO users');
}

$conn->query("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    role ENUM('Administrator','Receptionist','Doctor','Inventory Officer','Pharmacy User') NOT NULL DEFAULT 'Administrator',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("ALTER TABLE users MODIFY role ENUM('Administrator','Receptionist','Doctor','Inventory Officer','Pharmacy User') NOT NULL DEFAULT 'Administrator'");
ensure_column($conn, 'users', 'status', "ENUM('Active','Inactive') NOT NULL DEFAULT 'Active'");
ensure_column($conn, 'patients', 'assigned_doctor_id', 'INT NULL');
ensure_column($conn, 'appointments', 'doctor_id', 'INT NULL');
ensure_column($conn, 'appointments', 'remarks', 'TEXT NULL');
ensure_column($conn, 'appointments', 'approved_at', 'DATETIME NULL');
ensure_column($conn, 'appointments', 'rejected_at', 'DATETIME NULL');
ensure_column($conn, 'appointments', 'completed_at', 'DATETIME NULL');

$conn->query("ALTER TABLE appointments MODIFY status ENUM('Pending','Approved','Rejected','Completed','Cancelled') NOT NULL DEFAULT 'Pending'");

$conn->query("CREATE TABLE IF NOT EXISTS doctors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    full_name VARCHAR(150) NOT NULL,
    specialization VARCHAR(120) NOT NULL,
    qualification VARCHAR(180),
    phone VARCHAR(30) NOT NULL,
    email VARCHAR(150),
    license_number VARCHAR(80) NOT NULL UNIQUE,
    photo VARCHAR(255),
    experience_years INT NOT NULL DEFAULT 0,
    availability_schedule TEXT,
    bio TEXT,
    status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
)");

ensure_column($conn, 'doctors', 'qualification', 'VARCHAR(180) NULL');
ensure_column($conn, 'doctors', 'photo', 'VARCHAR(255) NULL');
ensure_column($conn, 'doctors', 'experience_years', 'INT NOT NULL DEFAULT 0');
ensure_column($conn, 'doctors', 'availability_schedule', 'TEXT NULL');
ensure_column($conn, 'doctors', 'bio', 'TEXT NULL');

$conn->query("CREATE TABLE IF NOT EXISTS doctor_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT NOT NULL,
    day_of_week ENUM('Saturday','Sunday','Monday','Tuesday','Wednesday','Thursday','Friday') NOT NULL,
    start_time TIME NOT NULL DEFAULT '08:00:00',
    end_time TIME NOT NULL DEFAULT '16:00:00',
    slot_minutes INT NOT NULL DEFAULT 30,
    is_working TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_doctor_day (doctor_id, day_of_week),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
)");

$conn->query("CREATE TABLE IF NOT EXISTS doctor_blocked_dates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT NOT NULL,
    block_date DATE NOT NULL,
    block_type ENUM('Leave','Blocked') NOT NULL DEFAULT 'Blocked',
    reason VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_doctor_block_date (doctor_id, block_date),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
)");

$conn->query("CREATE TABLE IF NOT EXISTS reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_type VARCHAR(80) NOT NULL,
    period_type ENUM('Daily','Weekly','Monthly','Custom') NOT NULL DEFAULT 'Daily',
    title VARCHAR(180) NOT NULL,
    generated_by INT NULL,
    date_from DATE NOT NULL,
    date_to DATE NOT NULL,
    summary TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE SET NULL
)");

$conn->query("CREATE TABLE IF NOT EXISTS expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category ENUM('Staff Salaries','Medical Supplies','Medicine Purchases','Rent','Electricity','Water','Internet','Equipment Maintenance','Other Expenses') NOT NULL,
    expense_date DATE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    vendor VARCHAR(150),
    description TEXT,
    receipt_path VARCHAR(255),
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
)");
ensure_index($conn, 'expenses', 'expense_date_idx', '`expense_date`');

$conn->query("CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    user_name VARCHAR(150) NOT NULL,
    user_role VARCHAR(80) NOT NULL,
    action VARCHAR(150) NOT NULL,
    module_name VARCHAR(80) NOT NULL,
    record_id VARCHAR(80),
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX audit_created_at_idx (created_at),
    INDEX audit_module_idx (module_name),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
)");

$conn->query("CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    appointment_id INT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('Cash','EVC Plus','Sahal','Bank Transfer') NOT NULL,
    payment_status ENUM('Paid','Partial','Outstanding') NOT NULL DEFAULT 'Paid',
    reference_number VARCHAR(100),
    notes TEXT,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
)");

$conn->query("CREATE TABLE IF NOT EXISTS receipts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_id INT NOT NULL,
    receipt_number VARCHAR(40) NOT NULL UNIQUE,
    clinic_name VARCHAR(150) NOT NULL DEFAULT 'Hair Clinic Pro',
    clinic_phone VARCHAR(40) NOT NULL DEFAULT '+252 61 000 0000',
    clinic_address VARCHAR(255) NOT NULL DEFAULT 'Mogadishu, Somalia',
    issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE
)");

$conn->query("CREATE TABLE IF NOT EXISTS medicines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    medicine_name VARCHAR(150) NOT NULL,
    generic_name VARCHAR(150) NULL,
    category VARCHAR(100) NOT NULL,
    batch_number VARCHAR(100) NULL,
    barcode VARCHAR(100) NULL,
    quantity INT NOT NULL DEFAULT 0,
    unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
    supplier_id INT NULL,
    expiry_date DATE NOT NULL,
    manufacturing_date DATE NULL,
    reorder_level INT NOT NULL DEFAULT 10,
    supplier VARCHAR(150) NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
ensure_column($conn, 'medicines', 'generic_name', 'VARCHAR(150) NULL');
ensure_column($conn, 'medicines', 'batch_number', 'VARCHAR(100) NULL');
ensure_column($conn, 'medicines', 'barcode', 'VARCHAR(100) NULL');
ensure_column($conn, 'medicines', 'supplier_id', 'INT NULL');
ensure_column($conn, 'medicines', 'manufacturing_date', 'DATE NULL');
ensure_column($conn, 'medicines', 'reorder_level', 'INT NOT NULL DEFAULT 10');
ensure_column($conn, 'medicines', 'description', 'TEXT NULL');
ensure_index($conn, 'medicines', 'medicine_expiry_idx', '`expiry_date`');
ensure_index($conn, 'medicines', 'medicine_reorder_idx', '`quantity`, `reorder_level`');

$conn->query("CREATE TABLE IF NOT EXISTS suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(150) NOT NULL,
    contact_person VARCHAR(150),
    phone VARCHAR(40) NOT NULL,
    email VARCHAR(150),
    address VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS inventory_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_number VARCHAR(50) NOT NULL,
    medicine_id INT NOT NULL,
    movement_type ENUM('Stock In','Stock Out','Pharmacy Sales','Treatment Consumption','Inventory Adjustment') NOT NULL,
    quantity INT NOT NULL,
    unit_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
    supplier_id INT NULL,
    department VARCHAR(120),
    purpose VARCHAR(255),
    reference_type VARCHAR(80),
    reference_id INT NULL,
    invoice_path VARCHAR(255),
    issued_by INT NULL,
    movement_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX movement_date_idx (movement_date),
    INDEX movement_type_idx (movement_type),
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE SET NULL
)");

$conn->query("CREATE TABLE IF NOT EXISTS pharmacy_invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(40) NOT NULL UNIQUE,
    patient_id INT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    payment_method ENUM('Cash','Mobile Money','Bank Transfer') NOT NULL,
    payment_status ENUM('Paid','Partial','Outstanding') NOT NULL DEFAULT 'Paid',
    notes TEXT,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
)");

$conn->query("CREATE TABLE IF NOT EXISTS pharmacy_invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    medicine_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    line_total DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (invoice_id) REFERENCES pharmacy_invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE
)");

$conn->query("CREATE TABLE IF NOT EXISTS pharmacy_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('Cash','Mobile Money','Bank Transfer') NOT NULL,
    reference_number VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES pharmacy_invoices(id) ON DELETE CASCADE
)");

$conn->query("CREATE TABLE IF NOT EXISTS prescriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prescription_number VARCHAR(40) NOT NULL UNIQUE,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    prescription_date DATE NOT NULL,
    status ENUM('Pending','Dispensed','Completed') NOT NULL DEFAULT 'Pending',
    instructions TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE
)");

$conn->query("CREATE TABLE IF NOT EXISTS prescription_medicines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prescription_id INT NOT NULL,
    medicine_id INT NOT NULL,
    quantity INT NOT NULL,
    instructions VARCHAR(255),
    FOREIGN KEY (prescription_id) REFERENCES prescriptions(id) ON DELETE CASCADE,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE
)");

$conn->query("CREATE TABLE IF NOT EXISTS pharmacy_sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_number VARCHAR(40) NOT NULL UNIQUE,
    customer_name VARCHAR(150),
    patient_id INT NULL,
    prescription_id INT NULL,
    medicine_count INT NOT NULL DEFAULT 0,
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
    discount_type ENUM('None','Percentage','Fixed') NOT NULL DEFAULT 'None',
    discount_value DECIMAL(10,2) NOT NULL DEFAULT 0,
    discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    tax_percent DECIMAL(10,2) NOT NULL DEFAULT 0,
    tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    payment_method ENUM('Cash','EVC Plus','Sahal','Bank Transfer') NOT NULL,
    payment_status ENUM('Paid','Partial','Outstanding') NOT NULL DEFAULT 'Paid',
    status ENUM('Paid','Pending','Cancelled','Returned') NOT NULL DEFAULT 'Paid',
    returned_at DATETIME NULL,
    return_reason TEXT NULL,
    notes TEXT,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE SET NULL,
    FOREIGN KEY (prescription_id) REFERENCES prescriptions(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
)");
ensure_column($conn, 'pharmacy_sales', 'returned_at', 'DATETIME NULL');
ensure_column($conn, 'pharmacy_sales', 'return_reason', 'TEXT NULL');
$conn->query("ALTER TABLE pharmacy_sales MODIFY status ENUM('Paid','Pending','Cancelled','Returned') NOT NULL DEFAULT 'Paid'");

$conn->query("CREATE TABLE IF NOT EXISTS pharmacy_sale_medicines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    medicine_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (sale_id) REFERENCES pharmacy_sales(id) ON DELETE CASCADE,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE
)");

$conn->query("CREATE TABLE IF NOT EXISTS inventory_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(150) NOT NULL,
    category VARCHAR(80) NOT NULL,
    stock_level INT NOT NULL DEFAULT 0,
    unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
    vendor VARCHAR(150) NOT NULL,
    status ENUM('In Stock', 'Low Stock', 'Out of Stock') NOT NULL DEFAULT 'In Stock',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS inventory_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    order_code VARCHAR(30) NOT NULL UNIQUE,
    quantity INT NOT NULL,
    priority VARCHAR(40) NOT NULL DEFAULT 'Normal',
    shipping_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_estimate DECIMAL(10,2) NOT NULL DEFAULT 0,
    order_status ENUM('Pending', 'Shipped', 'Delivered', 'Cancelled') NOT NULL DEFAULT 'Pending',
    note VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE
)");

$default_password_hash = '$2y$10$we9pFfF6WmRthWYXJNIP7OIk0Y5/gxDfEagOyGw8b501ow4P5RS4q';
$stmt = $conn->prepare(
    'INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE username = username'
);
$default_users = [
    ['admin', $default_password_hash, 'System Administrator', 'Administrator'],
    ['receptionist', $default_password_hash, 'Amina Receptionist', 'Receptionist'],
    ['doctor', $default_password_hash, 'Dr. Sarah Jenkins', 'Doctor'],
    ['inventory', $default_password_hash, 'Yusuf Inventory', 'Inventory Officer'],
    ['pharmacy', $default_password_hash, 'Nadia Pharmacy', 'Pharmacy User'],
];
foreach ($default_users as $default_user) {
    $stmt->bind_param('ssss', $default_user[0], $default_user[1], $default_user[2], $default_user[3]);
    $stmt->execute();
}

$conn->query("INSERT INTO doctors (user_id, full_name, specialization, phone, email, license_number, status)
    SELECT u.id, u.full_name, 'Hair Restoration Specialist', '+1 (555) 010-8821', 'doctor@hairclinic.test', 'HC-MD-1001', 'Active'
    FROM users u
    WHERE u.username = 'doctor'
    AND NOT EXISTS (SELECT 1 FROM doctors d WHERE d.license_number = 'HC-MD-1001')");

$conn->query("UPDATE patients
    SET assigned_doctor_id = (SELECT id FROM users WHERE username = 'doctor' LIMIT 1)
    WHERE assigned_doctor_id IS NULL");

$conn->query("UPDATE appointments
    SET doctor_id = (SELECT id FROM doctors WHERE license_number = 'HC-MD-1001' LIMIT 1)
    WHERE doctor_id IS NULL");

$conn->query("INSERT INTO doctor_schedules (doctor_id, day_of_week, start_time, end_time, slot_minutes, is_working)
    SELECT d.id, days.day_name, '08:00:00', '16:00:00', 30, IF(days.day_name IN ('Saturday','Sunday','Monday','Tuesday','Wednesday'), 1, 0)
    FROM doctors d
    JOIN (
        SELECT 'Saturday' day_name UNION ALL SELECT 'Sunday' UNION ALL SELECT 'Monday' UNION ALL SELECT 'Tuesday'
        UNION ALL SELECT 'Wednesday' UNION ALL SELECT 'Thursday' UNION ALL SELECT 'Friday'
    ) days
    WHERE NOT EXISTS (
        SELECT 1 FROM doctor_schedules ds WHERE ds.doctor_id = d.id AND ds.day_of_week = days.day_name
    )");

if (count_table($conn, 'SELECT COUNT(*) FROM medicines') === 0) {
    $conn->query("INSERT INTO medicines (medicine_name, category, quantity, unit_price, expiry_date, supplier) VALUES
        ('Minoxidil 5% Topical Solution', 'Hair Growth', 72, 38.00, '2027-12-31', 'ClinicSupplies Co.'),
        ('Finasteride 1mg Tablets', 'Prescription', 120, 24.50, '2027-08-30', 'MediCare Pharma'),
        ('Post-op Antibiotics', 'Post-Op', 35, 18.75, '2026-10-20', 'SurgiTech Global'),
        ('Biotin Hair Support', 'Supplement', 15, 12.00, '2026-07-15', 'BioMed Logistics')");
}

$conn->query("INSERT INTO suppliers (company_name, contact_person, phone, email, address)
    SELECT 'ClinicSupplies Co.', 'Supply Desk', '+252 61 220 1000', 'supplies@clinic.test', 'Mogadishu Medical Market'
    WHERE NOT EXISTS (SELECT 1 FROM suppliers WHERE company_name = 'ClinicSupplies Co.')");
$conn->query("INSERT INTO suppliers (company_name, contact_person, phone, email, address)
    SELECT 'MediCare Pharma', 'Pharma Account', '+252 61 220 2000', 'orders@medicare.test', 'KM4, Mogadishu'
    WHERE NOT EXISTS (SELECT 1 FROM suppliers WHERE company_name = 'MediCare Pharma')");

$conn->query("UPDATE medicines m
    LEFT JOIN suppliers s ON s.company_name = m.supplier
    SET m.supplier_id = s.id
    WHERE m.supplier_id IS NULL AND s.id IS NOT NULL");

if (count_table($conn, 'SELECT COUNT(*) FROM inventory_movements') === 0 && count_table($conn, 'SELECT COUNT(*) FROM medicines') > 0) {
    $conn->query("INSERT INTO inventory_movements (transaction_number, medicine_id, movement_type, quantity, unit_cost, total_cost, supplier_id, department, purpose, reference_type, reference_id, movement_date)
        SELECT CONCAT('OPEN-', m.id), m.id, 'Inventory Adjustment', m.quantity, m.unit_price, m.quantity * m.unit_price, m.supplier_id, 'Inventory', 'Opening stock balance', 'Medicine', m.id, m.created_at
        FROM medicines m
        WHERE m.quantity > 0");

    if (table_exists($conn, 'pharmacy_sale_medicines')) {
        $conn->query("INSERT INTO inventory_movements (transaction_number, medicine_id, movement_type, quantity, unit_cost, total_cost, department, purpose, reference_type, reference_id, issued_by, movement_date)
            SELECT CONCAT('SALE-BF-', psm.sale_id, '-', psm.medicine_id), psm.medicine_id, 'Pharmacy Sales', psm.quantity, psm.unit_price, psm.subtotal, 'Pharmacy', 'Backfilled pharmacy sale deduction', 'Pharmacy Sale', psm.sale_id, ps.created_by, ps.created_at
            FROM pharmacy_sale_medicines psm
            JOIN pharmacy_sales ps ON ps.id = psm.sale_id
            WHERE ps.status <> 'Returned'");
    }
}

if (
    count_table($conn, 'SELECT COUNT(*) FROM prescriptions') === 0
    && count_table($conn, 'SELECT COUNT(*) FROM patients') > 0
    && count_table($conn, 'SELECT COUNT(*) FROM doctors') > 0
    && count_table($conn, 'SELECT COUNT(*) FROM medicines') > 0
) {
    $conn->query("INSERT INTO prescriptions (prescription_number, patient_id, doctor_id, prescription_date, instructions)
        SELECT 'RX-20231106-1001', p.id, d.id, CURDATE(), 'Sample pending pharmacy prescription for post-treatment medication.'
        FROM patients p
        JOIN doctors d
        ORDER BY p.id, d.id
        LIMIT 1");
    $sample_prescription_id = (int) $conn->insert_id;
    if ($sample_prescription_id > 0) {
        $conn->query("INSERT INTO prescription_medicines (prescription_id, medicine_id, quantity, instructions)
            SELECT $sample_prescription_id, id, 1, 'Use as directed by doctor.'
            FROM medicines
            ORDER BY id
            LIMIT 2");
    }
}

if (!defined('BASE_URL')) {
    define('BASE_URL', '/hair-clinic-system');
}

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect($path)
{
    header('Location: ' . BASE_URL . $path);
    exit;
}

function flash($type, $message)
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function show_flash()
{
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        echo '<div class="alert alert-' . e($flash['type']) . ' alert-dismissible fade show" role="alert">'
            . e($flash['message'])
            . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
}

function fetch_all($stmt)
{
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function bind_params($stmt, $types, array &$params)
{
    if ($types === '') {
        return;
    }

    $bind = [$types];
    foreach ($params as $key => $value) {
        $bind[] = &$params[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $bind);
}

function fetch_one($stmt)
{
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function count_table($conn, $sql)
{
    return (int) $conn->query($sql)->fetch_row()[0];
}

function current_ip_address()
{
    foreach (['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            return substr(explode(',', $_SERVER[$key])[0], 0, 45);
        }
    }

    return 'CLI';
}

function log_activity($action, $module, $record_id = null)
{
    global $conn;

    if (!table_exists($conn, 'audit_logs')) {
        return;
    }

    $user_id = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
    $user_name = $_SESSION['admin_name'] ?? 'System';
    $user_role = $_SESSION['admin_role'] ?? 'System';
    $ip_address = current_ip_address();
    $record = $record_id === null ? null : (string) $record_id;

    $stmt = $conn->prepare('INSERT INTO audit_logs (user_id, user_name, user_role, action, module_name, record_id, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('issssss', $user_id, $user_name, $user_role, $action, $module, $record, $ip_address);
    $stmt->execute();
}

function doctor_schedule_for_date($doctor_id, $date)
{
    global $conn;

    $day = date('l', strtotime($date));
    $stmt = $conn->prepare('SELECT * FROM doctor_schedules WHERE doctor_id = ? AND day_of_week = ? LIMIT 1');
    $stmt->bind_param('is', $doctor_id, $day);
    return fetch_one($stmt);
}

function doctor_date_blocked($doctor_id, $date)
{
    global $conn;

    $stmt = $conn->prepare('SELECT * FROM doctor_blocked_dates WHERE doctor_id = ? AND block_date = ? LIMIT 1');
    $stmt->bind_param('is', $doctor_id, $date);
    return fetch_one($stmt);
}

function doctor_slot_available($doctor_id, $date, $time, $exclude_appointment_id = 0)
{
    global $conn;

    $schedule = doctor_schedule_for_date($doctor_id, $date);
    if (!$schedule || (int) $schedule['is_working'] !== 1) {
        return [false, 'Doctor is not working on the selected day.'];
    }

    if (doctor_date_blocked($doctor_id, $date)) {
        return [false, 'Doctor is unavailable on the selected date.'];
    }

    $selected = strtotime($time);
    $start = strtotime($schedule['start_time']);
    $end = strtotime($schedule['end_time']);
    if ($selected < $start || $selected >= $end) {
        return [false, 'Selected time is outside the doctor working hours.'];
    }

    $stmt = $conn->prepare("SELECT COUNT(*) total FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND id <> ? AND status NOT IN ('Rejected','Cancelled')");
    $stmt->bind_param('issi', $doctor_id, $date, $time, $exclude_appointment_id);
    $row = fetch_one($stmt);
    if ((int) ($row['total'] ?? 0) > 0) {
        return [false, 'This appointment slot is already booked.'];
    }

    return [true, 'Slot available.'];
}

function upload_inventory_file($field, $folder = 'uploads/inventory_invoices')
{
    if (empty($_FILES[$field]['name']) || !is_uploaded_file($_FILES[$field]['tmp_name'])) {
        return null;
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
    ];
    $mime = mime_content_type($_FILES[$field]['tmp_name']);
    if (!isset($allowed[$mime])) {
        throw new Exception('Invoice must be a PDF, JPG, PNG, or WEBP file.');
    }

    $target_dir = __DIR__ . '/../' . $folder;
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0775, true);
    }
    $filename = 'inventory-' . date('YmdHis') . '-' . random_int(1000, 9999) . '.' . $allowed[$mime];
    move_uploaded_file($_FILES[$field]['tmp_name'], $target_dir . '/' . $filename);
    return $folder . '/' . $filename;
}

function record_inventory_movement($medicine_id, $movement_type, $quantity, $unit_cost = 0, $options = [])
{
    global $conn;

    $quantity = max(1, (int) $quantity);
    $unit_cost = max(0, (float) $unit_cost);
    $total_cost = $quantity * $unit_cost;
    $transaction_number = $options['transaction_number'] ?? strtoupper(str_replace(' ', '-', $movement_type)) . '-' . date('Ymd') . '-' . random_int(1000, 9999);
    $supplier_id = $options['supplier_id'] ?? null;
    $department = $options['department'] ?? null;
    $purpose = $options['purpose'] ?? null;
    $reference_type = $options['reference_type'] ?? null;
    $reference_id = $options['reference_id'] ?? null;
    $invoice_path = $options['invoice_path'] ?? null;
    $issued_by = $options['issued_by'] ?? (isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null);
    $movement_date = $options['movement_date'] ?? date('Y-m-d H:i:s');

    $stmt = $conn->prepare('INSERT INTO inventory_movements (transaction_number, medicine_id, movement_type, quantity, unit_cost, total_cost, supplier_id, department, purpose, reference_type, reference_id, invoice_path, issued_by, movement_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('sisiddisssisis', $transaction_number, $medicine_id, $movement_type, $quantity, $unit_cost, $total_cost, $supplier_id, $department, $purpose, $reference_type, $reference_id, $invoice_path, $issued_by, $movement_date);
    $stmt->execute();

    return (int) $conn->insert_id;
}

function ensure_medicine_can_issue($medicine, $quantity)
{
    if (!$medicine) {
        throw new Exception('Selected inventory item was not found.');
    }
    if (strtotime($medicine['expiry_date']) < strtotime(date('Y-m-d'))) {
        throw new Exception($medicine['medicine_name'] . ' is expired and cannot be issued.');
    }
    if ((int) $medicine['quantity'] < (int) $quantity) {
        throw new Exception($medicine['medicine_name'] . ' has only ' . (int) $medicine['quantity'] . ' units available.');
    }
}
