<?php
require_once __DIR__ . '/../includes/auth.php';
require_access('inventory');
$id = (int) ($_GET['id'] ?? 0);
$stmt = $conn->prepare('SELECT m.*, s.company_name supplier_company, s.contact_person, s.phone supplier_phone FROM medicines m LEFT JOIN suppliers s ON s.id = m.supplier_id WHERE m.id = ?');
$stmt->bind_param('i', $id);
$medicine = fetch_one($stmt);
if (!$medicine) {
    flash('danger', 'Inventory item not found.');
    redirect('/inventory/medicines.php');
}
$stmt = $conn->prepare('SELECT im.*, u.full_name user_name FROM inventory_movements im LEFT JOIN users u ON u.id = im.issued_by WHERE im.medicine_id = ? ORDER BY im.movement_date DESC LIMIT 20');
$stmt->bind_param('i', $id);
$movements = fetch_all($stmt);
$page_title = 'Inventory Item Details';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="patient-head"><div><h1><?= e($medicine['medicine_name']) ?></h1><p><?= e($medicine['generic_name'] ?: $medicine['category']) ?></p></div><a class="add-patient-btn" href="medicine_form.php?id=<?= $id ?>"><i class="bi bi-pencil-square"></i>Edit</a></div>
<div class="row g-4">
    <div class="col-lg-5"><section class="form-panel h-100">
        <h2 class="h5 mb-3">Item Information</h2>
        <?php foreach ([
            'Category' => $medicine['category'],
            'Batch Number' => $medicine['batch_number'] ?: '-',
            'Barcode' => $medicine['barcode'] ?: '-',
            'Quantity' => number_format((int) $medicine['quantity']),
            'Unit Price' => '$' . number_format((float) $medicine['unit_price'], 2),
            'Inventory Value' => '$' . number_format((float) $medicine['quantity'] * (float) $medicine['unit_price'], 2),
            'Reorder Level' => number_format((int) $medicine['reorder_level']),
            'Manufacturing Date' => $medicine['manufacturing_date'] ?: '-',
            'Expiry Date' => $medicine['expiry_date'],
            'Supplier' => $medicine['supplier_company'] ?: $medicine['supplier'],
        ] as $label => $value): ?>
            <div class="d-flex justify-content-between border-bottom py-2"><span class="text-muted"><?= e($label) ?></span><strong><?= e($value) ?></strong></div>
        <?php endforeach; ?>
        <p class="mt-3"><?= e($medicine['description'] ?: 'No description recorded.') ?></p>
    </section></div>
    <div class="col-lg-7"><section class="form-panel h-100">
        <h2 class="h5 mb-3">Movement History</h2>
        <?php foreach ($movements as $movement): ?>
            <div class="d-flex justify-content-between border-bottom py-2"><span><strong><?= e($movement['movement_type']) ?></strong><small class="d-block text-muted"><?= e($movement['purpose'] ?: 'No purpose') ?> - <?= e($movement['user_name'] ?: 'System') ?></small></span><span class="text-end"><?= number_format((int) $movement['quantity']) ?><small class="d-block text-muted"><?= e(date('M j, h:i A', strtotime($movement['movement_date']))) ?></small></span></div>
        <?php endforeach; ?>
        <?php if (!$movements): ?><div class="empty-state">No movement history for this item.</div><?php endif; ?>
    </section></div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
