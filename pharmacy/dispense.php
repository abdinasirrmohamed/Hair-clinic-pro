<?php
require_once __DIR__ . '/../includes/auth.php';
require_access('pharmacy');
$page_title = 'Dispense Prescription';

$id = (int) ($_GET['id'] ?? $_POST['prescription_id'] ?? 0);
$stmt = $conn->prepare('SELECT rx.*, p.full_name patient_name, p.phone, d.full_name doctor_name
    FROM prescriptions rx
    JOIN patients p ON p.id = rx.patient_id
    JOIN doctors d ON d.id = rx.doctor_id
    WHERE rx.id = ?');
$stmt->bind_param('i', $id);
$rx = fetch_one($stmt);
if (!$rx) {
    redirect('/pharmacy/prescriptions.php');
}

$stmt = $conn->prepare('SELECT pm.*, m.medicine_name, m.quantity stock_quantity, m.unit_price
    FROM prescription_medicines pm
    JOIN medicines m ON m.id = pm.medicine_id
    WHERE pm.prescription_id = ?
    ORDER BY pm.id');
$stmt->bind_param('i', $id);
$items = fetch_all($stmt);
$payment_methods = ['Cash', 'EVC Plus', 'Sahal', 'Bank Transfer'];
$discount_types = ['None', 'Fixed', 'Percentage'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $method = $_POST['payment_method'] ?? 'Cash';
    $discount_type = $_POST['discount_type'] ?? 'None';
    $discount_value = max(0, (float) ($_POST['discount_value'] ?? 0));
    $tax_percent = max(0, (float) ($_POST['tax_percent'] ?? 0));
    $created_by = (int) ($_SESSION['admin_id'] ?? 0);
    if (!in_array($method, $payment_methods, true)) {
        $method = 'Cash';
    }
    if (!in_array($discount_type, $discount_types, true)) {
        $discount_type = 'None';
    }

    try {
        if ($rx['status'] !== 'Pending') {
            throw new Exception('This prescription has already been processed.');
        }

        $conn->begin_transaction();
        $locked_items = [];
        $subtotal = 0;
        foreach ($items as $item) {
            $stmt = $conn->prepare('SELECT id, medicine_name, quantity, unit_price FROM medicines WHERE id = ? FOR UPDATE');
            $medicine_id = (int) $item['medicine_id'];
            $stmt->bind_param('i', $medicine_id);
            $medicine = fetch_one($stmt);
            $quantity = (int) $item['quantity'];
            if (!$medicine || (int) $medicine['quantity'] < $quantity) {
                throw new Exception(($medicine['medicine_name'] ?? 'A prescribed medicine') . ' does not have enough stock.');
            }
            $line_total = (float) $medicine['unit_price'] * $quantity;
            $subtotal += $line_total;
            $locked_items[] = [
                'medicine_id' => $medicine_id,
                'quantity' => $quantity,
                'unit_price' => (float) $medicine['unit_price'],
                'subtotal' => $line_total,
            ];
        }

        $discount_amount = 0;
        if ($discount_type === 'Percentage') {
            $discount_amount = $subtotal * min($discount_value, 100) / 100;
        } elseif ($discount_type === 'Fixed') {
            $discount_amount = min($discount_value, $subtotal);
        }
        $taxable_total = max(0, $subtotal - $discount_amount);
        $tax_amount = $taxable_total * $tax_percent / 100;
        $total_amount = $taxable_total + $tax_amount;
        $sale_number = 'RXSALE-' . date('Ymd') . '-' . random_int(1000, 9999);
        $medicine_count = count($locked_items);
        $customer_name = $rx['patient_name'];
        $patient_id = (int) $rx['patient_id'];
        $prescription_id = (int) $rx['id'];
        $payment_status = 'Paid';
        $status = 'Paid';
        $notes = 'Prescription ' . $rx['prescription_number'] . ' dispensed by pharmacy.';

        $stmt = $conn->prepare('INSERT INTO pharmacy_sales (sale_number, customer_name, patient_id, prescription_id, medicine_count, subtotal, discount_type, discount_value, discount_amount, tax_percent, tax_amount, total_amount, payment_method, payment_status, status, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('ssiiidsdddddssssi', $sale_number, $customer_name, $patient_id, $prescription_id, $medicine_count, $subtotal, $discount_type, $discount_value, $discount_amount, $tax_percent, $tax_amount, $total_amount, $method, $payment_status, $status, $notes, $created_by);
        $stmt->execute();
        $sale_id = $conn->insert_id;

        foreach ($locked_items as $item) {
            $stmt = $conn->prepare('INSERT INTO pharmacy_sale_medicines (sale_id, medicine_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)');
            $stmt->bind_param('iiidd', $sale_id, $item['medicine_id'], $item['quantity'], $item['unit_price'], $item['subtotal']);
            $stmt->execute();

            $stmt = $conn->prepare('UPDATE medicines SET quantity = quantity - ? WHERE id = ?');
            $stmt->bind_param('ii', $item['quantity'], $item['medicine_id']);
            $stmt->execute();
        }

        $stmt = $conn->prepare("UPDATE prescriptions SET status = 'Dispensed' WHERE id = ?");
        $stmt->bind_param('i', $prescription_id);
        $stmt->execute();

        $conn->commit();
        flash('success', 'Prescription dispensed and pharmacy receipt generated.');
        redirect('/pharmacy/receipt.php?id=' . $sale_id);
    } catch (Throwable $e) {
        $conn->rollback();
        flash('danger', $e->getMessage());
        redirect('/pharmacy/dispense.php?id=' . $id);
    }
}

$subtotal = 0;
foreach ($items as $item) {
    $subtotal += (float) $item['unit_price'] * (int) $item['quantity'];
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="patient-head">
    <div><h1>Dispense Prescription</h1><p><?= e($rx['prescription_number']) ?> for <?= e($rx['patient_name']) ?> by <?= e($rx['doctor_name']) ?>.</p></div>
    <a class="add-patient-btn" href="prescriptions.php"><i class="bi bi-arrow-left"></i>Back to Queue</a>
</div>

<section class="patient-management-card">
    <form method="post" class="p-4">
        <input type="hidden" name="prescription_id" value="<?= (int) $rx['id'] ?>">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Medicine</th><th>Prescribed Qty</th><th>Available</th><th>Unit Price</th><th class="text-end">Subtotal</th></tr></thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr class="<?= (int) $item['stock_quantity'] < (int) $item['quantity'] ? 'table-danger' : '' ?>">
                            <td><strong><?= e($item['medicine_name']) ?></strong><small class="d-block text-muted"><?= e($item['instructions'] ?: 'No special instructions') ?></small></td>
                            <td><?= number_format((int) $item['quantity']) ?></td>
                            <td><?= number_format((int) $item['stock_quantity']) ?></td>
                            <td>$<?= number_format((float) $item['unit_price'], 2) ?></td>
                            <td class="text-end">$<?= number_format((float) $item['unit_price'] * (int) $item['quantity'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="row g-4 justify-content-end">
            <div class="col-md-5">
                <div class="form-panel m-0">
                    <div class="mb-3"><label class="form-label">Payment Method</label><select class="form-select" name="payment_method"><?php foreach ($payment_methods as $method): ?><option><?= e($method) ?></option><?php endforeach; ?></select></div>
                    <div class="row g-3">
                        <div class="col-6"><label class="form-label">Discount Type</label><select class="form-select" name="discount_type" id="discountType"><option>None</option><option>Fixed</option><option>Percentage</option></select></div>
                        <div class="col-6"><label class="form-label">Discount</label><input class="form-control" type="number" step="0.01" min="0" name="discount_value" id="discountValue" value="0"></div>
                        <div class="col-12"><label class="form-label">Tax %</label><input class="form-control" type="number" step="0.01" min="0" name="tax_percent" id="taxPercent" value="0"></div>
                    </div>
                    <hr>
                    <div class="invoice-total-line"><span>Subtotal</span><strong id="subtotalText">$<?= number_format($subtotal, 2) ?></strong></div>
                    <div class="invoice-total-line"><span>Discount</span><strong id="discountText">$0.00</strong></div>
                    <div class="invoice-total-line"><span>Tax</span><strong id="taxText">$0.00</strong></div>
                    <div class="invoice-total-line total"><span>Total</span><strong id="totalText">$<?= number_format($subtotal, 2) ?></strong></div>
                    <button class="btn btn-primary w-100 mt-3" <?= $rx['status'] !== 'Pending' ? 'disabled' : '' ?>><i class="bi bi-check2-circle"></i> Confirm Payment & Dispense</button>
                </div>
            </div>
        </div>
    </form>
</section>
<script>
const subtotal = <?= json_encode((float) $subtotal) ?>;
const money = value => '$' + Number(value || 0).toFixed(2);
function recalc() {
    const type = document.getElementById('discountType').value;
    const value = Math.max(0, Number(document.getElementById('discountValue').value || 0));
    const taxPercent = Math.max(0, Number(document.getElementById('taxPercent').value || 0));
    let discount = 0;
    if (type === 'Percentage') discount = subtotal * Math.min(value, 100) / 100;
    if (type === 'Fixed') discount = Math.min(value, subtotal);
    const taxable = Math.max(0, subtotal - discount);
    const tax = taxable * taxPercent / 100;
    document.getElementById('discountText').textContent = money(discount);
    document.getElementById('taxText').textContent = money(tax);
    document.getElementById('totalText').textContent = money(taxable + tax);
}
document.getElementById('discountType').addEventListener('change', recalc);
document.getElementById('discountValue').addEventListener('input', recalc);
document.getElementById('taxPercent').addEventListener('input', recalc);
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
