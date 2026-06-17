<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/waafi_payment.php';
require_access('pharmacy');
$page_title = 'Pharmacy POS Sale';
require_once __DIR__ . '/../includes/header.php';

$patients = $conn->query('SELECT id, full_name, phone FROM patients ORDER BY full_name')->fetch_all(MYSQLI_ASSOC);
$medicines = $conn->query('SELECT id, medicine_name, quantity, unit_price FROM medicines ORDER BY medicine_name')->fetch_all(MYSQLI_ASSOC);
$payment_methods = ['Cash', 'EVC Plus', 'Sahal', 'Bank Transfer'];
$discount_types = ['None', 'Fixed', 'Percentage'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_name = trim($_POST['customer_name'] ?? '');
    $customer_name = $customer_name !== '' ? $customer_name : null;
    $patient_id = (int) ($_POST['patient_id'] ?? 0);
    $patient_id = $patient_id > 0 ? $patient_id : null;
    $method         = $_POST['payment_method'] ?? 'Cash';
    $discount_type  = $_POST['discount_type'] ?? 'None';
    $discount_value = max(0, (float) ($_POST['discount_value'] ?? 0));
    $tax_percent    = max(0, (float) ($_POST['tax_percent'] ?? 0));
    $notes          = trim($_POST['notes'] ?? '');
    $account_no     = trim($_POST['account_no'] ?? '');
    $medicine_ids   = $_POST['medicine_id'] ?? [];
    $quantities     = $_POST['quantity'] ?? [];
    $created_by     = (int) ($_SESSION['admin_id'] ?? 0);

    if (!in_array($method, $payment_methods, true)) {
        $method = 'Cash';
    }
    if (!in_array($discount_type, $discount_types, true)) {
        $discount_type = 'None';
    }

    try {
        $conn->begin_transaction();
        $items = [];
        $subtotal = 0;

        foreach ($medicine_ids as $index => $medicine_id) {
            $medicine_id = (int) $medicine_id;
            $quantity = max(0, (int) ($quantities[$index] ?? 0));
            if ($medicine_id <= 0 || $quantity <= 0) {
                continue;
            }

            $stmt = $conn->prepare('SELECT id, medicine_name, quantity, unit_price, expiry_date FROM medicines WHERE id = ? FOR UPDATE');
            $stmt->bind_param('i', $medicine_id);
            $medicine = fetch_one($stmt);
            ensure_medicine_can_issue($medicine, $quantity);

            $unit_price = (float) $medicine['unit_price'];
            $line_total = $unit_price * $quantity;
            $subtotal += $line_total;
            $items[] = [
                'medicine_id' => $medicine_id,
                'quantity' => $quantity,
                'unit_price' => $unit_price,
                'subtotal' => $line_total,
            ];
        }

        if (!$items) {
            throw new Exception('Add at least one medicine before confirming the sale.');
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
        $sale_number     = 'SALE-' . date('Ymd') . '-' . random_int(1000, 9999);
        $medicine_count  = count($items);
        $prescription_id = null;
        $payment_status  = 'Paid';
        $status          = 'Paid';
        $waafi_tx_id     = '';

        $mobile_methods = ['EVC Plus', 'Sahal'];
        if (in_array($method, $mobile_methods, true)) {
            if ($account_no === '') {
                throw new Exception("$method payment requires a valid phone number.");
            }
            /** @var WaafiPayment $waafi */
            $waafi  = new WaafiPayment();
            $result = $waafi->charge($total_amount, $account_no, $sale_number, $sale_number, 'Pharmacy Sale');
            if (!$result['success']) {
                throw new Exception("$method payment failed: {$result['message']}");
            }
            $waafi_tx_id = $result['transaction_id'];
            $notes       = trim(($notes !== '' ? $notes . ' | ' : '') . "WAAFI TX: $waafi_tx_id");
        }

        $stmt = $conn->prepare('INSERT INTO pharmacy_sales (sale_number, customer_name, patient_id, prescription_id, medicine_count, subtotal, discount_type, discount_value, discount_amount, tax_percent, tax_amount, total_amount, payment_method, payment_status, status, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('ssiiidsdddddssssi', $sale_number, $customer_name, $patient_id, $prescription_id, $medicine_count, $subtotal, $discount_type, $discount_value, $discount_amount, $tax_percent, $tax_amount, $total_amount, $method, $payment_status, $status, $notes, $created_by);
        $stmt->execute();
        $sale_id = $conn->insert_id;

        foreach ($items as $item) {
            $stmt = $conn->prepare('INSERT INTO pharmacy_sale_medicines (sale_id, medicine_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)');
            $stmt->bind_param('iiidd', $sale_id, $item['medicine_id'], $item['quantity'], $item['unit_price'], $item['subtotal']);
            $stmt->execute();

            $stmt = $conn->prepare('UPDATE medicines SET quantity = quantity - ? WHERE id = ?');
            $stmt->bind_param('ii', $item['quantity'], $item['medicine_id']);
            $stmt->execute();

            record_inventory_movement($item['medicine_id'], 'Pharmacy Sales', $item['quantity'], $item['unit_price'], [
                'department' => 'Pharmacy',
                'purpose' => 'POS sale stock deduction',
                'reference_type' => 'Pharmacy Sale',
                'reference_id' => $sale_id,
            ]);
        }

        $conn->commit();
        log_activity('Processed pharmacy sale', 'Pharmacy', $sale_id);
        flash('success', 'Sale completed, stock updated, and receipt generated.');
        redirect("/pharmacy/receipt.php?id=$sale_id");
    } catch (Throwable $e) {
        $conn->rollback();
        flash('danger', $e->getMessage());
        redirect('/pharmacy/sale.php');
    }
}
?>
<div class="patient-head">
    <div><h1>Pharmacy POS</h1><p>Create direct walk-in medicine sales with live totals and stock checks.</p></div>
    <a class="add-patient-btn" href="sales_history.php"><i class="bi bi-clock-history"></i>Sales History</a>
</div>

<section class="patient-management-card">
    <div class="patient-tabs"><div class="tab-links"><a href="medicines.php">Medicines</a><a class="active" href="sale.php">New Sale</a><a href="prescriptions.php">Prescriptions</a><a href="sales_history.php">Sales History</a><a href="reports.php">Reports</a></div></div>
    <form method="post" id="posForm" class="p-4">
        <div class="row g-3 mb-2">
            <div class="col-md-3">
                <label class="form-label">Customer Name <span class="text-muted">(optional)</span></label>
                <input class="form-control" name="customer_name" placeholder="Walk-in customer">
            </div>
            <div class="col-md-3">
                <label class="form-label">Link Patient <span class="text-muted">(optional)</span></label>
                <select class="form-select" name="patient_id" id="posPatientSelect">
                    <option value="" data-phone="">No patient record</option>
                    <?php foreach ($patients as $patient): ?>
                        <option value="<?= (int) $patient['id'] ?>" data-phone="<?= e(preg_replace('/\D/', '', $patient['phone'])) ?>"><?= e($patient['full_name']) ?> - <?= e($patient['phone']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Payment Method</label>
                <select class="form-select" name="payment_method" id="posPaymentMethod" onchange="onMethodChange(this.value)">
                    <?php foreach ($payment_methods as $m): ?><option><?= e($m) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3" id="phoneCol">
                <label class="form-label" id="phoneLbl">EVC / Sahal Phone</label>
                <div class="input-group">
                    <input class="form-control" type="tel" name="account_no" id="posAccountNo"
                           placeholder="e.g. 252618827482"
                           oninput="onPhoneInput(this.value)">
                    <button class="btn btn-outline-secondary" type="button"
                            onclick="fillFromPatient()" title="Copy phone from linked patient">
                        <i class="bi bi-person-fill"></i>
                    </button>
                </div>
                <div class="form-text text-muted" id="phoneNote">Enter to auto-switch to EVC Plus.</div>
            </div>
        </div>

        <div id="walletBanner" class="alert alert-success py-2 mb-3 d-flex align-items-center gap-2" style="display:none!important;">
            <i class="bi bi-phone-fill fs-5"></i>
            <span>
                Charging <strong id="posAlertTotal">$0.00</strong> to
                <strong id="posAlertPhone">—</strong> via
                <strong id="posAlertMethod">—</strong>
                &mdash; customer receives a payment prompt on their phone.
            </span>
        </div>

        <div class="table-responsive">
            <table class="table align-middle pos-table">
                <thead>
                    <tr>
                        <th>Medicine</th>
                        <th style="width:120px;">Stock</th>
                        <th style="width:120px;">Qty</th>
                        <th style="width:140px;">Unit Price</th>
                        <th style="width:140px;">Subtotal</th>
                        <th style="width:60px;"></th>
                    </tr>
                </thead>
                <tbody id="medicineRows"></tbody>
            </table>
        </div>
        <button class="btn btn-outline-primary" type="button" id="addMedicine"><i class="bi bi-plus-lg"></i> Add Medicine Row</button>

        <div class="row g-4 mt-2">
            <div class="col-lg-7">
                <label class="form-label">Sale Notes</label>
                <textarea class="form-control" name="notes" rows="4" placeholder="Optional notes for this sale"></textarea>
            </div>
            <div class="col-lg-5">
                <div class="form-panel m-0">
                    <div class="row g-3">
                        <div class="col-6"><label class="form-label">Discount Type</label><select class="form-select" name="discount_type" id="discountType"><option value="None">No Discount</option><option value="Fixed">Fixed Amount ($)</option><option value="Percentage">Percentage (%)</option></select></div>
                        <div class="col-6"><label class="form-label" id="discountLbl">Discount</label><input class="form-control" type="number" step="0.01" min="0" name="discount_value" id="discountValue" value="0" placeholder="0"></div>
                        <div class="col-6"><label class="form-label">Tax %</label><input class="form-control" type="number" step="0.01" min="0" name="tax_percent" id="taxPercent" value="0"></div>
                        <div class="col-6 d-flex align-items-end justify-content-end"><button class="btn btn-primary w-100" type="submit" id="posSubmitBtn"><i class="bi bi-check2-circle"></i> <span id="posSubmitLabel">Confirm Payment</span></button></div>
                    </div>
                    <hr>
                    <div class="invoice-total-line"><span>Subtotal</span><strong id="subtotalText">$0.00</strong></div>
                    <div class="invoice-total-line"><span>Discount</span><strong id="discountText">$0.00</strong></div>
                    <div class="invoice-total-line"><span>Tax</span><strong id="taxText">$0.00</strong></div>
                    <div class="invoice-total-line total"><span>Total</span><strong id="totalText">$0.00</strong></div>
                </div>
            </div>
        </div>
    </form>
</section>

<template id="medicineRowTemplate">
    <tr>
        <td>
            <select class="form-select medicine-select" name="medicine_id[]" required>
                <option value="">Search medicine...</option>
                <?php foreach ($medicines as $medicine): ?>
                    <option value="<?= (int) $medicine['id'] ?>" data-price="<?= (float) $medicine['unit_price'] ?>" data-stock="<?= (int) $medicine['quantity'] ?>"><?= e($medicine['medicine_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <td><span class="stock-badge">0 available</span></td>
        <td><input class="form-control quantity-input" type="number" name="quantity[]" min="1" value="1"></td>
        <td><input class="form-control unit-price" type="text" value="$0.00" readonly></td>
        <td><input class="form-control line-total" type="text" value="$0.00" readonly></td>
        <td><button class="btn btn-outline-danger remove-row" type="button"><i class="bi bi-x-lg"></i></button></td>
    </tr>
</template>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
const money = value => '$' + Number(value || 0).toFixed(2);
const rows = document.getElementById('medicineRows');
const template = document.getElementById('medicineRowTemplate');

function initSelect(row) {
    const select = $(row).find('.medicine-select');
    select.select2({
        width: '100%',
        ajax: {
            url: 'medicine_search.php',
            dataType: 'json',
            delay: 200,
            data: params => ({ q: params.term || '' }),
            processResults: data => ({ results: data.results })
        }
    });
    select.on('select2:select', event => {
        const data = event.params.data;
        const option = row.querySelector('.medicine-select option:checked');
        option.dataset.price = data.unit_price;
        option.dataset.stock = data.quantity;
        updateRow(row);
    });
}

function addRow() {
    const clone = template.content.cloneNode(true);
    rows.appendChild(clone);
    const row = rows.lastElementChild;
    initSelect(row);
    updateRow(row);
}

function selectedData(row) {
    const option = row.querySelector('.medicine-select option:checked');
    return {
        price: Number(option?.dataset.price || 0),
        stock: Number(option?.dataset.stock || 0)
    };
}

function updateRow(row) {
    const data = selectedData(row);
    const qtyInput = row.querySelector('.quantity-input');
    const qty = Math.max(0, Number(qtyInput.value || 0));
    row.querySelector('.stock-badge').textContent = data.stock + ' available';
    row.querySelector('.unit-price').value = money(data.price);
    row.querySelector('.line-total').value = money(data.price * qty);
    row.classList.toggle('table-warning', data.stock > 0 && qty > data.stock);
    updateTotals();
}

function updateTotals() {
    let subtotal = 0;
    rows.querySelectorAll('tr').forEach(row => {
        const data = selectedData(row);
        const qty = Math.max(0, Number(row.querySelector('.quantity-input').value || 0));
        subtotal += data.price * qty;
    });
    const discountType = document.getElementById('discountType').value;
    const discountValue = Math.max(0, Number(document.getElementById('discountValue').value || 0));
    let discount = 0;
    if (discountType === 'Percentage') discount = subtotal * Math.min(discountValue, 100) / 100;
    if (discountType === 'Fixed') discount = Math.min(discountValue, subtotal);
    const taxable = Math.max(0, subtotal - discount);
    const tax = taxable * Math.max(0, Number(document.getElementById('taxPercent').value || 0)) / 100;
    document.getElementById('subtotalText').textContent = money(subtotal);
    document.getElementById('discountText').textContent = money(discount);
    document.getElementById('taxText').textContent = money(tax);
    document.getElementById('totalText').textContent = money(taxable + tax);
    if (typeof refreshBanner === 'function') refreshBanner();
}

document.getElementById('addMedicine').addEventListener('click', addRow);

document.getElementById('discountType').addEventListener('change', function () {
    var lbl = document.getElementById('discountLbl');
    if (this.value === 'Percentage') lbl.textContent = 'Discount (%)';
    else if (this.value === 'Fixed')  lbl.textContent = 'Discount ($)';
    else                              lbl.textContent = 'Discount';
    updateTotals();
});
document.getElementById('discountValue').addEventListener('input',  updateTotals);
document.getElementById('discountValue').addEventListener('change', updateTotals);
document.getElementById('taxPercent').addEventListener('input',  updateTotals);
document.getElementById('taxPercent').addEventListener('change', updateTotals);
rows.addEventListener('input', event => {
    if (event.target.classList.contains('quantity-input')) updateRow(event.target.closest('tr'));
});
rows.addEventListener('click', event => {
    const button = event.target.closest('.remove-row');
    if (button) {
        button.closest('tr').remove();
        updateTotals();
    }
});
addRow();

// ── Mobile wallet ──────────────────────────────────────────────────────────
function isMobileMethod() {
    return ['EVC Plus','Sahal'].indexOf(document.getElementById('posPaymentMethod').value) !== -1;
}

function onMethodChange(val) {
    var mobile = ['EVC Plus','Sahal'].indexOf(val) !== -1;
    var phoneLbl  = document.getElementById('phoneLbl');
    var submitLbl = document.getElementById('posSubmitLabel');
    var submitBtn = document.getElementById('posSubmitBtn');
    var phoneNote = document.getElementById('phoneNote');
    document.getElementById('posAccountNo').required = mobile;
    if (mobile) {
        phoneLbl.innerHTML  = '<strong class="text-primary"><i class="bi bi-phone-fill"></i> ' + val + ' Phone <span class="text-danger">*</span></strong>';
        phoneNote.innerHTML = 'Enter number or <strong>tap <i class="bi bi-person-fill"></i></strong> to copy from patient.';
        submitLbl.textContent = 'Send Payment Request';
        submitBtn.classList.remove('btn-primary');
        submitBtn.classList.add('btn-success');
    } else {
        phoneLbl.innerHTML  = 'Phone <small class="text-muted">(EVC/Sahal only)</small>';
        phoneNote.textContent = 'Required for EVC Plus / Sahal.';
        submitLbl.textContent = 'Confirm Payment';
        submitBtn.classList.remove('btn-success');
        submitBtn.classList.add('btn-primary');
        document.getElementById('walletBanner').style.setProperty('display', 'none', 'important');
    }
    refreshBanner();
}

function refreshBanner() {
    var phone   = document.getElementById('posAccountNo').value.trim();
    var total   = document.getElementById('totalText').textContent;
    var method  = document.getElementById('posPaymentMethod').value;
    var banner  = document.getElementById('walletBanner');
    var mobile  = isMobileMethod();
    document.getElementById('posAlertTotal').textContent  = total;
    document.getElementById('posAlertPhone').textContent  = phone || '—';
    document.getElementById('posAlertMethod').textContent = method;
    banner.style.setProperty('display', (mobile && phone && total !== '$0.00') ? 'flex' : 'none', 'important');
}

function onPhoneInput(val) {
    // Auto-switch payment method to EVC Plus as soon as a phone is typed
    if (val.trim().length > 0) {
        var sel = document.getElementById('posPaymentMethod');
        if (!isMobileMethod()) {
            sel.value = 'EVC Plus';
            onMethodChange('EVC Plus');
        }
    }
    refreshBanner();
}

function fillFromPatient() {
    var sel   = document.getElementById('posPatientSelect');
    var phone = sel.options[sel.selectedIndex].dataset.phone || '';
    if (phone) {
        document.getElementById('posAccountNo').value = phone;
        onPhoneInput(phone);
    } else {
        alert('Select a linked patient first, or type the phone number manually.');
    }
}

// phone input is handled by oninput="onPhoneInput()" on the element

document.getElementById('posForm').addEventListener('submit', function (e) {
    var phone  = document.getElementById('posAccountNo').value.trim();
    var method = document.getElementById('posPaymentMethod').value;

    // If a phone number is present but method is not mobile, force EVC Plus
    if (phone.length > 0 && !isMobileMethod()) {
        document.getElementById('posPaymentMethod').value = 'EVC Plus';
        onMethodChange('EVC Plus');
    }

    // Require phone if mobile method selected
    if (isMobileMethod() && phone === '') {
        e.preventDefault();
        alert('Please enter the customer\'s EVC Plus / Sahal phone number before submitting.');
        document.getElementById('posAccountNo').focus();
        return false;
    }

    if (isMobileMethod()) {
        var btn = document.getElementById('posSubmitBtn');
        btn.disabled  = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Sending Payment Request…';
    }
});

// initialise on load (in case browser restores a previous selection)
onMethodChange(document.getElementById('posPaymentMethod').value);
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
