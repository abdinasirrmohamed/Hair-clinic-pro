<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

// ── Fetch user BEFORE header (POST handler needs it) ─────────────────
$uid  = (int) ($_SESSION['admin_id'] ?? 0);
$stmt = $conn->prepare('SELECT id, username, full_name, role, status, phone, bio, avatar_path, created_at FROM users WHERE id = ?');
$stmt->bind_param('i', $uid);
$user = fetch_one($stmt);

if (!$user) {
    flash('danger', 'User account not found.');
    redirect('/dashboard.php');
}

$uploads_dir = __DIR__ . '/../uploads/avatars/';
if (!is_dir($uploads_dir)) {
    mkdir($uploads_dir, 0775, true);
}

// ── Handle POST BEFORE header.php outputs any HTML ───────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $full_name = trim($_POST['full_name'] ?? '');
        $phone     = trim($_POST['phone']     ?? '');
        $bio       = trim($_POST['bio']       ?? '');

        if ($full_name === '') {
            flash('danger', 'Full name is required.');
            redirect('/users/profile.php');
        }

        $avatar_path = $user['avatar_path'];
        if (!empty($_FILES['avatar']['name'])) {
            $file    = $_FILES['avatar'];
            $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

            if (!in_array($ext, $allowed, true)) {
                flash('danger', 'Avatar must be JPG, PNG, WEBP, or GIF.');
                redirect('/users/profile.php');
            }
            if ($file['size'] > 2 * 1024 * 1024) {
                flash('danger', 'Avatar must be under 2 MB.');
                redirect('/users/profile.php');
            }

            $filename = 'avatar_' . $uid . '_' . time() . '.' . $ext;
            if (!move_uploaded_file($file['tmp_name'], $uploads_dir . $filename)) {
                flash('danger', 'Failed to save avatar. Check uploads/ folder permissions.');
                redirect('/users/profile.php');
            }

            if ($avatar_path && str_starts_with($avatar_path, 'uploads/avatars/')) {
                $old = __DIR__ . '/../' . $avatar_path;
                if (file_exists($old)) {
                    unlink($old);
                }
            }
            $avatar_path = 'uploads/avatars/' . $filename;
        }

        $stmt = $conn->prepare('UPDATE users SET full_name=?, phone=?, bio=?, avatar_path=? WHERE id=?');
        $stmt->bind_param('ssssi', $full_name, $phone, $bio, $avatar_path, $uid);
        $stmt->execute();
        $_SESSION['admin_name'] = $full_name;
        log_activity('Updated profile', 'Users', $uid);
        flash('success', 'Profile updated successfully.');
        redirect('/users/profile.php');
    }

    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new_pw  = $_POST['new_password']      ?? '';
        $confirm = $_POST['confirm_password']  ?? '';

        $stmt = $conn->prepare('SELECT password_hash FROM users WHERE id=?');
        $stmt->bind_param('i', $uid);
        $row  = fetch_one($stmt);

        if (!password_verify($current, $row['password_hash'] ?? '')) {
            flash('danger', 'Current password is incorrect.');
            redirect('/users/profile.php');
        }
        if (strlen($new_pw) < 6) {
            flash('danger', 'New password must be at least 6 characters.');
            redirect('/users/profile.php');
        }
        if ($new_pw !== $confirm) {
            flash('danger', 'Passwords do not match.');
            redirect('/users/profile.php');
        }

        $hash = password_hash($new_pw, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('UPDATE users SET password_hash=? WHERE id=?');
        $stmt->bind_param('si', $hash, $uid);
        $stmt->execute();
        log_activity('Changed password', 'Users', $uid);
        flash('success', 'Password changed successfully.');
        redirect('/users/profile.php');
    }

    if ($action === 'remove_avatar') {
        if ($user['avatar_path'] && str_starts_with($user['avatar_path'], 'uploads/avatars/')) {
            $old = __DIR__ . '/../' . $user['avatar_path'];
            if (file_exists($old)) {
                unlink($old);
            }
        }
        $stmt = $conn->prepare('UPDATE users SET avatar_path=NULL WHERE id=?');
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        flash('success', 'Avatar removed.');
        redirect('/users/profile.php');
    }
}

// ── Only NOW output HTML ─────────────────────────────────────────────
$page_title = 'My Profile';
require_once __DIR__ . '/../includes/header.php';

// Refresh user data after possible update
$stmt = $conn->prepare('SELECT id, username, full_name, role, status, phone, bio, avatar_path, created_at FROM users WHERE id = ?');
$stmt->bind_param('i', $uid);
$user = fetch_one($stmt);

$role_styles = [
    'Administrator'     => 'background:#dce7f8;color:#063c94',
    'Doctor'            => 'background:#d0f5f2;color:#005c55',
    'Receptionist'      => 'background:#ede9fe;color:#6b21a8',
    'Inventory Officer' => 'background:#ffedd5;color:#c25003',
    'Pharmacy User'     => 'background:#e0e7ff;color:#4338ca',
];
$role_style = $role_styles[$user['role']] ?? 'background:#eef2f8;color:#314666';

$parts    = preg_split('/\s+/', trim($user['full_name']));
$initials = strtoupper(substr($parts[0] ?? '', 0, 1) . substr($parts[count($parts) - 1] ?? '', 0, 1));
if (strlen($initials) < 2) {
    $initials = strtoupper(substr($user['full_name'], 0, 2));
}
?>

<div class="page-head">
    <div><h1>My Profile</h1><p>Manage your personal information, avatar, and password.</p></div>
    <a class="add-patient-btn" href="<?= BASE_URL ?>/dashboard.php"><i class="bi bi-grid"></i> Dashboard</a>
</div>

<div class="row g-4">
    <!-- Left: Profile card -->
    <div class="col-lg-4">
        <div class="profile-page-card">
            <div class="profile-banner"></div>
            <div class="profile-body">
                <div class="profile-avatar-wrap">
                    <?php if ($user['avatar_path'] && file_exists(__DIR__ . '/../' . $user['avatar_path'])): ?>
                        <img class="profile-avatar-img" src="<?= BASE_URL . '/' . e($user['avatar_path']) ?>?v=<?= time() ?>" alt="Avatar">
                    <?php else: ?>
                        <div class="profile-avatar-initials"><?= e($initials) ?></div>
                    <?php endif; ?>
                </div>
                <div class="mt-3 profile-name"><?= e($user['full_name']) ?></div>
                <span class="profile-role-pill" style="<?= $role_style ?>"><?= e($user['role']) ?></span>
                <div class="profile-meta mt-2">
                    <i class="bi bi-person me-1"></i><?= e($user['username']) ?>
                    <?php if ($user['phone']): ?>
                        <span class="ms-3"><i class="bi bi-phone me-1"></i><?= e($user['phone']) ?></span>
                    <?php endif; ?>
                </div>
                <?php if ($user['bio']): ?>
                    <p class="mt-3 small"><?= nl2br(e($user['bio'])) ?></p>
                <?php endif; ?>
                <div class="mt-3 profile-meta">
                    <i class="bi bi-calendar3 me-1"></i>Member since <?= e(date('M j, Y', strtotime($user['created_at']))) ?>
                </div>
                <?php if ($user['avatar_path']): ?>
                    <form method="post" class="mt-3" onsubmit="return confirm('Remove your avatar?')">
                        <input type="hidden" name="action" value="remove_avatar">
                        <button class="btn btn-sm btn-outline-danger w-100" type="submit">
                            <i class="bi bi-trash"></i> Remove Avatar
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right: Edit forms -->
    <div class="col-lg-8">
        <!-- Update profile -->
        <div class="form-panel mb-4">
            <h5 class="fw-bold mb-3"><i class="bi bi-person-gear me-2 text-primary"></i>Edit Profile</h5>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_profile">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                        <input class="form-control" type="text" name="full_name" value="<?= e($user['full_name']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Phone</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-phone"></i></span>
                            <input class="form-control" type="tel" name="phone" value="<?= e($user['phone'] ?? '') ?>" placeholder="+252 61 000 0000">
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Bio</label>
                        <textarea class="form-control" name="bio" rows="3" placeholder="A short bio..."><?= e($user['bio'] ?? '') ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Profile Picture</label>
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <?php if ($user['avatar_path'] && file_exists(__DIR__ . '/../' . $user['avatar_path'])): ?>
                                <img src="<?= BASE_URL . '/' . e($user['avatar_path']) ?>?v=<?= time() ?>" alt="Current" style="height:52px;width:52px;border-radius:999px;object-fit:cover;border:2px solid #e5e9f2;">
                            <?php else: ?>
                                <div style="height:52px;width:52px;border-radius:999px;background:#dce7ff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:1.1rem;color:#06469f;"><?= e($initials) ?></div>
                            <?php endif; ?>
                            <div>
                                <input class="form-control form-control-sm" type="file" name="avatar" id="avatarInput" accept="image/jpeg,image/png,image/webp,image/gif">
                                <div class="form-text">JPG, PNG, WEBP or GIF &mdash; max 2 MB</div>
                            </div>
                        </div>
                        <div id="avatarPreviewWrap" class="mt-2" style="display:none;">
                            <img id="avatarPreview" src="" alt="Preview" style="height:80px;width:80px;border-radius:999px;object-fit:cover;border:3px solid #063c94;">
                            <small class="d-block text-muted mt-1">Preview — saved on submit.</small>
                        </div>
                    </div>
                </div>
                <div class="mt-4">
                    <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Save Changes</button>
                </div>
            </form>
        </div>

        <!-- Change password -->
        <div class="form-panel">
            <h5 class="fw-bold mb-3"><i class="bi bi-shield-lock me-2 text-warning"></i>Change Password</h5>
            <form method="post">
                <input type="hidden" name="action" value="change_password">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Current Password</label>
                        <input class="form-control" type="password" name="current_password" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">New Password</label>
                        <input class="form-control" type="password" name="new_password" id="newPw" minlength="6" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Confirm New</label>
                        <input class="form-control" type="password" name="confirm_password" id="confirmPw" required>
                        <div class="form-text" id="pwMatch"></div>
                    </div>
                </div>
                <div class="mt-4">
                    <button class="btn btn-warning" type="submit"><i class="bi bi-key"></i> Update Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('avatarInput').addEventListener('change', function () {
    var file = this.files[0];
    if (!file) return;
    var reader = new FileReader();
    reader.onload = function (e) {
        document.getElementById('avatarPreview').src = e.target.result;
        document.getElementById('avatarPreviewWrap').style.display = '';
    };
    reader.readAsDataURL(file);
});

document.getElementById('confirmPw').addEventListener('input', function () {
    var match = document.getElementById('newPw').value === this.value;
    var hint  = document.getElementById('pwMatch');
    hint.textContent = match ? 'Passwords match' : 'Passwords do not match';
    hint.style.color = match ? '#07933d' : '#d71920';
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
