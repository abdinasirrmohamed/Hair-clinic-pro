<?php
require_once __DIR__ . '/../includes/auth.php';
require_access('pharmacy');
$page_title = 'Pending Prescriptions';
require_once __DIR__ . '/../includes/header.php';

$status = $_GET['status'] ?? 'Pending';
if (!in_array($status, ['Pending', 'Dispensed', 'Completed', 'All'], true)) {
    $status = 'Pending';
}

$where = $status === 'All' ? '' : "WHERE rx.status = '" . $conn->real_escape_string($status) . "'";
$prescriptions = $conn->query("SELECT rx.*, p.full_name patient_name, p.phone, d.full_name doctor_name,
        COUNT(pm.id) medicine_count
    FROM prescriptions rx
    JOIN patients p ON p.id = rx.patient_id
    JOIN doctors d ON d.id = rx.doctor_id
    LEFT JOIN prescription_medicines pm ON pm.prescription_id = rx.id
    $where
    GROUP BY rx.id
    ORDER BY rx.created_at DESC")->fetch_all(MYSQLI_ASSOC);
?>
<div class="patient-head">
    <div><h1>Prescription Queue</h1><p>Review pending prescriptions and dispense medicines after payment.</p></div>
    <a class="add-patient-btn" href="sale.php"><i class="bi bi-cart-plus"></i>Direct Sale</a>
</div>

<section class="patient-management-card">
    <div class="patient-tabs"><div class="tab-links"><a href="medicines.php">Medicines</a><a href="sale.php">New Sale</a><a class="active" href="prescriptions.php">Prescriptions</a><a href="sales_history.php">Sales History</a><a href="reports.php">Reports</a></div></div>
    <form class="p-3 border-bottom" method="get">
        <div class="d-flex gap-2 flex-wrap">
            <?php foreach (['Pending', 'Dispensed', 'Completed', 'All'] as $option): ?>
                <button class="btn <?= $status === $option ? 'btn-primary' : 'btn-outline-secondary' ?>" name="status" value="<?= e($option) ?>"><?= e($option) ?></button>
            <?php endforeach; ?>
        </div>
    </form>
    <div class="patient-list-grid patient-list-head" style="grid-template-columns: 1fr 1.3fr 1.3fr .8fr .8fr 1fr;">
        <span>RX Number</span><span>Patient</span><span>Doctor</span><span>Medicines</span><span>Status</span><span>Action</span>
    </div>
    <?php foreach ($prescriptions as $rx): ?>
        <div class="patient-list-grid patient-list-row" style="grid-template-columns: 1fr 1.3fr 1.3fr .8fr .8fr 1fr;">
            <span><strong><?= e($rx['prescription_number']) ?></strong><small class="d-block text-muted"><?= e(date('M j, Y', strtotime($rx['prescription_date']))) ?></small></span>
            <span><?= e($rx['patient_name']) ?><small class="d-block text-muted"><?= e($rx['phone']) ?></small></span>
            <span><?= e($rx['doctor_name']) ?></span>
            <span><?= number_format((int) $rx['medicine_count']) ?></span>
            <span><em class="status-pill <?= $rx['status'] === 'Pending' ? 'upcoming' : 'active' ?>"><?= e($rx['status']) ?></em></span>
            <span>
                <?php if ($rx['status'] === 'Pending'): ?>
                    <a class="btn btn-sm btn-primary" href="dispense.php?id=<?= (int) $rx['id'] ?>">Dispense Medicine</a>
                <?php else: ?>
                    <span class="text-muted">Processed</span>
                <?php endif; ?>
            </span>
        </div>
    <?php endforeach; ?>
    <?php if (!$prescriptions): ?><div class="empty-state">No prescriptions found for this filter.</div><?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
