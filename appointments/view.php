<?php
require_once __DIR__ . '/../includes/auth.php';
require_access('appointments');

$search = trim($_GET['search'] ?? '');
$latest = $conn->query('SELECT appointment_date FROM appointments ORDER BY appointment_date DESC LIMIT 1')->fetch_assoc();
$default_month = $latest ? date('Y-m', strtotime($latest['appointment_date'])) : date('Y-m');
$month = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : $default_month;
$month_start = $month . '-01';
$month_label = date('F Y', strtotime($month_start));
$prev_month = date('Y-m', strtotime($month_start . ' -1 month'));
$next_month = date('Y-m', strtotime($month_start . ' +1 month'));
$today_month = date('Y-m');

$selected_date = $_GET['date'] ?? '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date) || date('Y-m', strtotime($selected_date)) !== $month) {
    $first_with_appt = $conn->query("SELECT appointment_date FROM appointments WHERE DATE_FORMAT(appointment_date, '%Y-%m') = '" . $conn->real_escape_string($month) . "' ORDER BY appointment_date ASC LIMIT 1")->fetch_assoc();
    $selected_date = $first_with_appt['appointment_date'] ?? $month_start;
}

$where_search = '';
if ($search !== '') {
    $where_search = ' AND (p.full_name LIKE ? OR a.reason LIKE ? OR a.status LIKE ?)';
}

$stmt = $conn->prepare(
    "SELECT a.*, p.full_name, p.id AS patient_code
     FROM appointments a
     JOIN patients p ON p.id = a.patient_id
     WHERE DATE_FORMAT(a.appointment_date, '%Y-%m') = ? $where_search
     ORDER BY a.appointment_date ASC, a.appointment_time ASC"
);
if ($search !== '') {
    $like = '%' . $search . '%';
    $stmt->bind_param('ssss', $month, $like, $like, $like);
} else {
    $stmt->bind_param('s', $month);
}
$month_appointments = fetch_all($stmt);

$appointments_by_date = [];
foreach ($month_appointments as $appointment) {
    $appointments_by_date[$appointment['appointment_date']][] = $appointment;
}

$selected_appointments = $appointments_by_date[$selected_date] ?? [];
$first_day_offset = (int) date('w', strtotime($month_start));
$days_in_month = (int) date('t', strtotime($month_start));
$calendar_start = date('Y-m-d', strtotime($month_start . ' -' . $first_day_offset . ' days'));

function calendar_initials($name)
{
    $parts = preg_split('/\s+/', trim($name));
    $first = strtoupper(substr($parts[0] ?? '', 0, 1));
    $last = strtoupper(substr($parts[count($parts) - 1] ?? '', 0, 1));
    return $first . ($last !== $first ? $last : '');
}

function calendar_status_class($status)
{
    if ($status === 'Completed') {
        return 'completed';
    }
    if ($status === 'Cancelled') {
        return 'cancelled';
    }
    return 'pending';
}

function calendar_event_label($appointment)
{
    return date('H:i', strtotime($appointment['appointment_time'])) . ' ' . $appointment['full_name'];
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Appointment Calendar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/css/style.css" rel="stylesheet">
</head>
<body class="dashboard-shell">
    <?php render_role_sidebar($_SERVER['SCRIPT_NAME'] ?? ''); ?>

    <section class="clinic-main">
        <header class="clinic-topbar calendar-topbar">
            <h1>Hair Clinic Management</h1>
            <form class="top-search calendar-search" method="get">
                <input type="hidden" name="month" value="<?= e($month) ?>">
                <i class="bi bi-search"></i>
                <input name="search" value="<?= e($search) ?>" placeholder="Search appointments, patients...">
            </form>
            <div class="top-actions admin-profile">
                <button type="button" aria-label="Notifications"><i class="bi bi-bell"></i></button>
                <button type="button" aria-label="Help"><i class="bi bi-question-circle"></i></button>
                <span class="profile-divider"></span>
                <div class="admin-avatar"><?= e(calendar_initials($_SESSION['admin_name'] ?? 'Admin User')) ?></div>
                <span class="admin-copy"><strong><?= e($_SESSION['admin_name'] ?? 'Admin User') ?></strong><small><?= e(current_role()) ?></small></span>
            </div>
        </header>

        <main class="clinic-content calendar-content">
            <?php show_flash(); ?>
            <div class="calendar-toolbar">
                <h2><?= e($month_label) ?></h2>
                <div class="calendar-nav">
                    <a href="view.php?month=<?= e($prev_month) ?>"><i class="bi bi-chevron-left"></i></a>
                    <a class="today" href="view.php?month=<?= e($today_month) ?>">Today</a>
                    <a href="view.php?month=<?= e($next_month) ?>"><i class="bi bi-chevron-right"></i></a>
                </div>
                <div class="calendar-view-tabs">
                    <button class="active" type="button">Month</button>
                    <button type="button">Week</button>
                    <button type="button">Day</button>
                </div>
            </div>

            <div class="calendar-layout">
                <section class="month-calendar">
                    <div class="calendar-weekdays">
                        <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday): ?>
                            <span><?= $weekday ?></span>
                        <?php endforeach; ?>
                    </div>
                    <div class="calendar-grid">
                        <?php for ($i = 0; $i < 42; $i++): ?>
                            <?php
                            $cell_date = date('Y-m-d', strtotime($calendar_start . " +$i days"));
                            $in_month = date('Y-m', strtotime($cell_date)) === $month;
                            $day_appointments = $appointments_by_date[$cell_date] ?? [];
                            $is_selected = $cell_date === $selected_date;
                            ?>
                            <a class="calendar-cell <?= !$in_month ? 'muted' : '' ?> <?= $is_selected ? 'selected' : '' ?>" href="view.php?month=<?= e($month) ?>&date=<?= e($cell_date) ?>">
                                <strong><?= e(date('j', strtotime($cell_date))) ?></strong>
                                <div class="calendar-events">
                                    <?php foreach (array_slice($day_appointments, 0, 3) as $appointment): ?>
                                        <span class="<?= e(calendar_status_class($appointment['status'])) ?>"><?= e(calendar_event_label($appointment)) ?></span>
                                    <?php endforeach; ?>
                                    <?php if (count($day_appointments) > 3): ?>
                                        <em>+<?= count($day_appointments) - 3 ?> more</em>
                                    <?php endif; ?>
                                </div>
                            </a>
                        <?php endfor; ?>
                    </div>
                </section>

                <aside class="calendar-day-panel">
                    <div class="day-panel-head">
                        <div>
                            <h2><?= e(date('l, M j', strtotime($selected_date))) ?></h2>
                            <p><?= count($selected_appointments) ?> appointments scheduled</p>
                        </div>
                        <i class="bi bi-three-dots-vertical"></i>
                    </div>
                    <div class="day-appointments">
                        <?php foreach ($selected_appointments as $appointment): ?>
                            <?php $class = calendar_status_class($appointment['status']); ?>
                            <article class="day-card <?= e($class) ?>">
                                <div class="day-card-meta">
                                    <span><i></i><?= e(strtoupper($appointment['status'])) ?></span>
                                    <time><?= e(date('h:i A', strtotime($appointment['appointment_time']))) ?></time>
                                </div>
                                <h3><?= e($appointment['full_name']) ?></h3>
                                <p><?= e($appointment['reason']) ?></p>
                                <div class="day-card-footer">
                                    <span class="mini-team"><b><?= e(calendar_initials($appointment['full_name'])) ?></b><b>DR</b></span>
                                    <a href="edit.php?id=<?= $appointment['id'] ?>"><?= $appointment['status'] === 'Pending' ? 'Check-in' : 'View Notes' ?></a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                        <?php if (!$selected_appointments): ?>
                            <div class="empty-state">No appointments for this day.</div>
                        <?php endif; ?>
                    </div>
                    <a class="schedule-selected-day" href="add.php?date=<?= e($selected_date) ?>"><i class="bi bi-plus-lg"></i>Schedule for <?= e(date('M j', strtotime($selected_date))) ?></a>
                </aside>
            </div>
        </main>
    </section>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
