<?php
require_once __DIR__ . '/../includes/auth.php';
require_access('prescriptions');
$page_title = 'Prescription History';

$where = '';
if (current_role() === 'Doctor') {
    $where = 'WHERE d.user_id = ' . (int) ($_SESSION['admin_id'] ?? 0);
}

$prescriptions = $conn->query("SELECT rx.*, p.full_name patient_name, d.full_name doctor_name,
        COUNT(pm.id) medicine_count
    FROM prescriptions rx
    JOIN patients p ON p.id = rx.patient_id
    JOIN doctors d ON d.id = rx.doctor_id
    LEFT JOIN prescription_medicines pm ON pm.prescription_id = rx.id
    $where
    GROUP BY rx.id
    ORDER BY rx.created_at DESC")->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>
<div class="patient-head">
    <div><h1>Prescription History</h1><p>Prescriptions created by doctors and sent to pharmacy.</p></div>
    <a class="add-patient-btn" href="add.php"><i class="bi bi-plus-lg"></i>New Prescription</a>
</div>

<section class="patient-management-card">
    <div class="patient-list-grid patient-list-head" style="grid-template-columns: 1fr 1.4fr 1.4fr .8fr .8fr .9fr;">
        <span>RX Number</span><span>Patient</span><span>Doctor</span><span>Medicines</span><span>Date</span><span>Status</span>
    </div>
    <?php foreach ($prescriptions as $rx): ?>
        <div class="patient-list-grid patient-list-row" style="grid-template-columns: 1fr 1.4fr 1.4fr .8fr .8fr .9fr;">
            <span><strong><?= e($rx['prescription_number']) ?></strong></span>
            <span><?= e($rx['patient_name']) ?></span>
            <span><?= e($rx['doctor_name']) ?></span>
            <span><?= number_format((int) $rx['medicine_count']) ?></span>
            <span><?= e(date('M j, Y', strtotime($rx['prescription_date']))) ?></span>
            <span><em class="status-pill <?= $rx['status'] === 'Pending' ? 'upcoming' : 'active' ?>"><?= e($rx['status']) ?></em></span>
        </div>
    <?php endforeach; ?>
    <?php if (!$prescriptions): ?><div class="empty-state">No prescriptions found.</div><?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
