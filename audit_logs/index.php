<?php
require_once __DIR__ . '/../includes/auth.php';
require_access('audit_logs');

$search = trim($_GET['search'] ?? '');
$role = trim($_GET['role'] ?? '');
$module = trim($_GET['module'] ?? '');
$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : date('Y-m-d', strtotime('-30 days'));
$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : date('Y-m-d');
$export = $_GET['export'] ?? '';

$where = 'WHERE DATE(created_at) BETWEEN ? AND ?';
$types = 'ss';
$params = [$from, $to];

if ($search !== '') {
    $where .= ' AND (user_name LIKE ? OR action LIKE ? OR record_id LIKE ? OR ip_address LIKE ?)';
    $like = '%' . $search . '%';
    $types .= 'ssss';
    array_push($params, $like, $like, $like, $like);
}

if ($role !== '') {
    $where .= ' AND user_role = ?';
    $types .= 's';
    $params[] = $role;
}

if ($module !== '') {
    $where .= ' AND module_name = ?';
    $types .= 's';
    $params[] = $module;
}

$stmt = $conn->prepare("SELECT * FROM audit_logs $where ORDER BY created_at DESC LIMIT 500");
bind_params($stmt, $types, $params);
$logs = fetch_all($stmt);

$roles = $conn->query('SELECT DISTINCT user_role FROM audit_logs ORDER BY user_role')->fetch_all(MYSQLI_ASSOC);
$modules = $conn->query('SELECT DISTINCT module_name FROM audit_logs ORDER BY module_name')->fetch_all(MYSQLI_ASSOC);
$today_total = count_table($conn, 'SELECT COUNT(*) FROM audit_logs WHERE DATE(created_at) = CURDATE()');
$recent_total = count($logs);
$most_active = $conn->query('SELECT user_name, COUNT(*) total FROM audit_logs GROUP BY user_name ORDER BY total DESC LIMIT 1')->fetch_assoc();
$most_module = $conn->query('SELECT module_name, COUNT(*) total FROM audit_logs GROUP BY module_name ORDER BY total DESC LIMIT 1')->fetch_assoc();
$recent_activities = $conn->query('SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 8')->fetch_all(MYSQLI_ASSOC);

if ($export === 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="audit-logs-' . $from . '-to-' . $to . '.xls"');
    echo "Date & Time\tUser\tRole\tAction\tModule\tRecord ID\tIP Address\n";
    foreach ($logs as $log) {
        echo $log['created_at'] . "\t" . $log['user_name'] . "\t" . $log['user_role'] . "\t" . $log['action'] . "\t" . $log['module_name'] . "\t" . $log['record_id'] . "\t" . $log['ip_address'] . "\n";
    }
    exit;
}

$page_title = 'Audit Logs';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="patient-head">
    <div>
        <h1>Audit Logs & Activity Tracking</h1>
        <p>Monitor user actions, accountability records, and security events. Logs are append-only.</p>
    </div>
    <div class="d-flex gap-2">
        <a class="add-patient-btn" href="?<?= e(http_build_query(array_merge($_GET, ['export' => 'excel']))) ?>"><i class="bi bi-file-earmark-spreadsheet"></i>Export Excel</a>
        <button class="add-patient-btn" type="button" onclick="window.print()"><i class="bi bi-filetype-pdf"></i>Export PDF</button>
    </div>
</div>

<div class="patient-metrics mb-4">
    <article class="patient-metric"><div><p>Total User Activities Today</p><strong><?= number_format($today_total) ?></strong></div><span class="metric-icon blue"><i class="bi bi-activity"></i></span></article>
    <article class="patient-metric"><div><p>Filtered Activities</p><strong><?= number_format($recent_total) ?></strong></div><span class="metric-icon mint"><i class="bi bi-filter-circle"></i></span></article>
    <article class="patient-metric"><div><p>Most Active User</p><strong><?= e($most_active['user_name'] ?? 'N/A') ?></strong></div><span class="metric-icon blue"><i class="bi bi-person-check"></i></span></article>
    <article class="patient-metric"><div><p>Most Accessed Module</p><strong><?= e($most_module['module_name'] ?? 'N/A') ?></strong></div><span class="metric-icon mint"><i class="bi bi-box-arrow-in-right"></i></span></article>
</div>

<form class="form-panel mb-4" method="get">
    <div class="row g-3 align-items-end">
        <div class="col-md-3"><label class="form-label">Search</label><input class="form-control" name="search" value="<?= e($search) ?>" placeholder="User, action, record, IP"></div>
        <div class="col-md-2"><label class="form-label">Role</label><select class="form-select" name="role"><option value="">All Roles</option><?php foreach ($roles as $item): ?><option value="<?= e($item['user_role']) ?>" <?= $role === $item['user_role'] ? 'selected' : '' ?>><?= e($item['user_role']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><label class="form-label">Module</label><select class="form-select" name="module"><option value="">All Modules</option><?php foreach ($modules as $item): ?><option value="<?= e($item['module_name']) ?>" <?= $module === $item['module_name'] ? 'selected' : '' ?>><?= e($item['module_name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><label class="form-label">From</label><input class="form-control" type="date" name="from" value="<?= e($from) ?>"></div>
        <div class="col-md-2"><label class="form-label">To</label><input class="form-control" type="date" name="to" value="<?= e($to) ?>"></div>
        <div class="col-md-1"><button class="btn btn-primary w-100"><i class="bi bi-search"></i></button></div>
    </div>
</form>

<div class="row g-4">
    <div class="col-xl-8">
        <section class="patient-management-card">
            <div class="patient-tabs"><div class="tab-links"><a class="active" href="#">Activity Log</a></div></div>
            <div class="table-responsive p-4">
                <table class="table align-middle">
                    <thead><tr><th>Date & Time</th><th>User</th><th>Action</th><th>Module</th><th>Record</th><th>IP</th></tr></thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?= e(date('Y-m-d h:i A', strtotime($log['created_at']))) ?></td>
                                <td><strong><?= e($log['user_name']) ?></strong><small class="d-block text-muted"><?= e($log['user_role']) ?></small></td>
                                <td><?= e($log['action']) ?></td>
                                <td><span class="status-pill active"><?= e($log['module_name']) ?></span></td>
                                <td><?= e($log['record_id'] ?: '-') ?></td>
                                <td><?= e($log['ip_address']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$logs): ?><tr><td colspan="6"><div class="empty-state">No audit logs match the filters.</div></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
    <div class="col-xl-4">
        <section class="form-panel h-100">
            <h2 class="h5 mb-3">Recent Activities</h2>
            <?php foreach ($recent_activities as $activity): ?>
                <div class="border-bottom py-2">
                    <strong><?= e($activity['action']) ?></strong>
                    <small class="d-block text-muted"><?= e($activity['user_name']) ?> - <?= e($activity['module_name']) ?> - <?= e(date('M j, h:i A', strtotime($activity['created_at']))) ?></small>
                </div>
            <?php endforeach; ?>
            <?php if (!$recent_activities): ?><div class="empty-state">No activity logged yet.</div><?php endif; ?>
        </section>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
