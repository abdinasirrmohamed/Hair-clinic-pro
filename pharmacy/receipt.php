<?php
require_once __DIR__ . '/../includes/auth.php';
require_access('pharmacy');
$page_title = 'Pharmacy Receipt';

$id = (int) ($_GET['id'] ?? 0);
$stmt = $conn->prepare('SELECT s.*, p.full_name patient_name, p.phone patient_phone, u.full_name cashier_name
    FROM pharmacy_sales s
    LEFT JOIN patients p ON p.id = s.patient_id
    LEFT JOIN users u ON u.id = s.created_by
    WHERE s.id = ?');
$stmt->bind_param('i', $id);
$sale = fetch_one($stmt);
if (!$sale) {
    redirect('/pharmacy/reports.php');
}

$stmt = $conn->prepare('SELECT sm.*, m.medicine_name
    FROM pharmacy_sale_medicines sm
    JOIN medicines m ON m.id = sm.medicine_id
    WHERE sm.sale_id = ?
    ORDER BY sm.id');
$stmt->bind_param('i', $id);
$items = fetch_all($stmt);

require_once __DIR__ . '/../includes/header.php';
?>
<div class="patient-head">
    <div><h1>Pharmacy Receipt</h1><p><?= e($sale['sale_number']) ?> generated after successful payment.</p></div>
    <button class="add-patient-btn" onclick="window.print()"><i class="bi bi-printer"></i>Print Receipt</button>
</div>

<section class="patient-management-card invoice-print">
    <div class="p-4">
        <div class="d-flex justify-content-between gap-3 flex-wrap border-bottom pb-3 mb-4">
            <div>
                <h2 class="h4 mb-1 text-primary">Hair Clinic Pro Pharmacy</h2>
                <p class="mb-0 text-muted">Mogadishu, Somalia</p>
                <p class="mb-0 text-muted">+252 61 000 0000</p>
            </div>
            <div class="text-end">
                <span class="status-pill active">Paid</span>
                <h3 class="h5 mt-2 mb-1"><?= e($sale['sale_number']) ?></h3>
                <p class="mb-0 text-muted"><?= e(date('M j, Y h:i A', strtotime($sale['created_at']))) ?></p>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-4"><strong>Customer</strong><p class="mb-0"><?= e($sale['customer_name'] ?: ($sale['patient_name'] ?: 'Walk-in Customer')) ?></p></div>
            <div class="col-md-4">
                <strong>Payment Method</strong>
                <p class="mb-0"><?= e($sale['payment_method']) ?>
                <?php if (str_contains((string) $sale['notes'], 'WAAFI TX:')): ?>
                    <br><small class="text-success"><i class="bi bi-check-circle-fill"></i>
                    <?= preg_match('/WAAFI TX: ([^\s]+)/', (string) $sale['notes'], $m) ? 'TX: ' . e($m[1]) : '' ?>
                    <?= str_starts_with($m[1] ?? '', 'TEST-') ? '<span class="badge bg-warning text-dark">TEST MODE</span>' : '' ?>
                    </small>
                <?php endif; ?>
                </p>
            </div>
            <div class="col-md-4"><strong>Cashier</strong><p class="mb-0"><?= e($sale['cashier_name'] ?: 'System User') ?></p></div>
        </div>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Medicine</th><th>Qty</th><th>Unit Price</th><th class="text-end">Subtotal</th></tr></thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= e($item['medicine_name']) ?></td>
                            <td><?= number_format((int) $item['quantity']) ?></td>
                            <td>$<?= number_format((float) $item['unit_price'], 2) ?></td>
                            <td class="text-end">$<?= number_format((float) $item['subtotal'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="row justify-content-end">
            <div class="col-md-5">
                <div class="invoice-total-line"><span>Subtotal</span><strong>$<?= number_format((float) $sale['subtotal'], 2) ?></strong></div>
                <div class="invoice-total-line"><span>Discount</span><strong>$<?= number_format((float) $sale['discount_amount'], 2) ?></strong></div>
                <div class="invoice-total-line"><span>Tax</span><strong>$<?= number_format((float) $sale['tax_amount'], 2) ?></strong></div>
                <div class="invoice-total-line total"><span>Total Paid</span><strong>$<?= number_format((float) $sale['total_amount'], 2) ?></strong></div>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
