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
    role ENUM('Administrator','Receptionist','Doctor','Inventory Officer') NOT NULL DEFAULT 'Administrator',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

ensure_column($conn, 'users', 'role', "ENUM('Administrator','Receptionist','Doctor','Inventory Officer') NOT NULL DEFAULT 'Administrator'");
ensure_column($conn, 'patients', 'assigned_doctor_id', 'INT NULL');

$conn->query("CREATE TABLE IF NOT EXISTS doctors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    full_name VARCHAR(150) NOT NULL,
    specialization VARCHAR(120) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    email VARCHAR(150),
    license_number VARCHAR(80) NOT NULL UNIQUE,
    status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
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

function fetch_one($stmt)
{
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function count_table($conn, $sql)
{
    return (int) $conn->query($sql)->fetch_row()[0];
}
