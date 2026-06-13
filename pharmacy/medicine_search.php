<?php
require_once __DIR__ . '/../includes/auth.php';
require_access('pharmacy');

header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $like = '%' . $q . '%';
    $stmt = $conn->prepare('SELECT id, medicine_name, quantity, unit_price FROM medicines WHERE medicine_name LIKE ? OR category LIKE ? OR supplier LIKE ? ORDER BY medicine_name LIMIT 20');
    $stmt->bind_param('sss', $like, $like, $like);
    $rows = fetch_all($stmt);
} else {
    $rows = $conn->query('SELECT id, medicine_name, quantity, unit_price FROM medicines ORDER BY medicine_name LIMIT 20')->fetch_all(MYSQLI_ASSOC);
}

$results = array_map(function ($medicine) {
    return [
        'id' => (int) $medicine['id'],
        'text' => $medicine['medicine_name'] . ' - Stock ' . (int) $medicine['quantity'] . ' - $' . number_format((float) $medicine['unit_price'], 2),
        'unit_price' => (float) $medicine['unit_price'],
        'quantity' => (int) $medicine['quantity'],
    ];
}, $rows);

echo json_encode(['results' => $results]);
