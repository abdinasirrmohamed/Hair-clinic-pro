<?php
require_once __DIR__ . '/../includes/auth.php';
require_roles(['Administrator']);

$roles = array_keys(role_permissions());
$id    = (int) ($_GET['id'] ?? 0);

$stmt = $conn->prepare('SELECT id, username, full_name, role, status FROM users WHERE id = ?');
$stmt->bind_param('i', $id);
$user = fetch_one($stmt);

if (!$user) {
    flash('danger', 'User not found.');
    redirect('/users/view.php');
}

$errors = [];

// ── POST handler BEFORE any HTML output ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username  = trim($_POST['username']  ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $role      = $_POST['role']           ?? '';
    $status    = $_POST['status']         ?? 'Active';
    $password  = $_POST['password']       ?? '';

    if ($username === '' || $full_name === '' || !in_array($role, $roles, true)) {
        $errors[] = 'Full name, username, and role are required.';
    }

    // If a new password is supplied, enforce strong rules
    if ($password !== '') {
        if (strlen($password) < 8)                    $errors[] = 'Password must be at least 8 characters.';
        if (!preg_match('/[A-Z]/', $password))         $errors[] = 'Password must contain at least one uppercase letter.';
        if (!preg_match('/[a-z]/', $password))         $errors[] = 'Password must contain at least one lowercase letter.';
        if (!preg_match('/[0-9]/', $password))         $errors[] = 'Password must contain at least one number.';
        if (!preg_match('/[^A-Za-z0-9]/', $password))  $errors[] = 'Password must contain at least one symbol (e.g. @, #, !).';
    }

    if (!$errors) {
        if ($password !== '') {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('UPDATE users SET username=?, full_name=?, role=?, status=?, password=? WHERE id=?');
            $stmt->bind_param('sssssi', $username, $full_name, $role, $status, $hash, $id);
        } else {
            $stmt = $conn->prepare('UPDATE users SET username=?, full_name=?, role=?, status=? WHERE id=?');
            $stmt->bind_param('ssssi', $username, $full_name, $role, $status, $id);
        }
        try {
            $stmt->execute();
            if ((int) ($_SESSION['admin_id'] ?? 0) === $id) {
                $_SESSION['admin_name'] = $full_name;
                $_SESSION['admin_role'] = $role;
            }
            log_activity('Updated user account', 'Users', $id);
            flash('success', 'User account updated successfully.');
            redirect('/users/view.php');
        } catch (mysqli_sql_exception $e) {
            $errors[] = 'That username is already taken.';
        }
    }
}

$page_title = 'Edit user account';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="patient-head">
    <div>
        <h1>Edit user account</h1>
        <p>Update login credentials and role. Leave password blank to keep the current one.</p>
    </div>
    <a class="add-patient-btn" href="view.php"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <strong>Please fix the following:</strong>
        <ul class="mb-0 mt-1">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<section class="form-panel">
    <form method="post" id="user-edit-form" class="row g-3" novalidate>

        <div class="col-md-6">
            <label class="form-label fw-semibold" for="full_name">Full name <span class="text-danger">*</span></label>
            <input class="form-control" id="full_name" name="full_name"
                   value="<?= e($_POST['full_name'] ?? $user['full_name']) ?>" required>
        </div>

        <div class="col-md-6">
            <label class="form-label fw-semibold" for="username">Username <span class="text-danger">*</span></label>
            <input class="form-control" id="username" name="username"
                   value="<?= e($_POST['username'] ?? $user['username']) ?>"
                   autocomplete="off" spellcheck="false" required>
        </div>

        <div class="col-md-6">
            <label class="form-label fw-semibold" for="role">Role <span class="text-danger">*</span></label>
            <select class="form-select" id="role" name="role" required>
                <?php foreach ($roles as $r): ?>
                    <option value="<?= e($r) ?>" <?= ($_POST['role'] ?? $user['role']) === $r ? 'selected' : '' ?>>
                        <?= e($r) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-6">
            <label class="form-label fw-semibold" for="status">Status</label>
            <select class="form-select" id="status" name="status">
                <?php foreach (['Active','Inactive'] as $s): ?>
                    <option <?= ($_POST['status'] ?? $user['status']) === $s ? 'selected' : '' ?>>
                        <?= e($s) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- New password (optional) -->
        <div class="col-12"><hr class="my-1"><p class="text-muted small mb-0">Change password — leave both fields blank to keep the current password.</p></div>

        <div class="col-md-6">
            <label class="form-label fw-semibold" for="password">New password</label>
            <div class="input-group">
                <input class="form-control" type="password" id="password" name="password"
                       autocomplete="new-password" placeholder="Leave blank to keep current">
                <button class="btn btn-outline-secondary" type="button" id="toggle-pw" aria-label="Show password">
                    <i class="bi bi-eye" id="pw-eye-icon"></i>
                </button>
            </div>
            <div class="d-flex gap-1 mt-2" id="strength-bar" aria-hidden="true">
                <div class="flex-fill rounded" id="sb1" style="height:4px;background:#dee2e6;transition:background .2s"></div>
                <div class="flex-fill rounded" id="sb2" style="height:4px;background:#dee2e6;transition:background .2s"></div>
                <div class="flex-fill rounded" id="sb3" style="height:4px;background:#dee2e6;transition:background .2s"></div>
                <div class="flex-fill rounded" id="sb4" style="height:4px;background:#dee2e6;transition:background .2s"></div>
            </div>
            <div id="strength-label" class="form-text mt-1" aria-live="polite"></div>
            <ul class="form-text mt-1 mb-0 ps-3" id="pw-rules" style="display:none">
                <li id="r-len"   class="text-danger">At least 8 characters</li>
                <li id="r-upper" class="text-danger">One uppercase letter (A–Z)</li>
                <li id="r-lower" class="text-danger">One lowercase letter (a–z)</li>
                <li id="r-num"   class="text-danger">One number (0–9)</li>
                <li id="r-sym"   class="text-danger">One symbol (@, #, !, etc.)</li>
            </ul>
        </div>

        <div class="col-md-6">
            <label class="form-label fw-semibold" for="confirm_password">Confirm new password</label>
            <div class="input-group">
                <input class="form-control" type="password" id="confirm_password"
                       autocomplete="new-password" placeholder="Repeat new password">
                <button class="btn btn-outline-secondary" type="button" id="toggle-cpw" aria-label="Show confirm password">
                    <i class="bi bi-eye" id="cpw-eye-icon"></i>
                </button>
            </div>
            <div id="confirm-hint" class="form-text mt-1" aria-live="polite"></div>
        </div>

        <div class="col-12 d-flex gap-2 mt-2">
            <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Save changes</button>
            <a class="btn btn-outline-secondary" href="view.php">Cancel</a>
        </div>
    </form>
</section>

<script>
(function () {
    function makeToggle(btnId, inputId, iconId) {
        document.getElementById(btnId).addEventListener('click', function () {
            var inp  = document.getElementById(inputId);
            var icon = document.getElementById(iconId);
            var show = inp.type === 'password';
            inp.type = show ? 'text' : 'password';
            icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
        });
    }
    makeToggle('toggle-pw',  'password',        'pw-eye-icon');
    makeToggle('toggle-cpw', 'confirm_password', 'cpw-eye-icon');

    var bars   = ['sb1','sb2','sb3','sb4'].map(function(id){ return document.getElementById(id); });
    var colors = { 1:'#dc3545', 2:'#fd7e14', 3:'#0d6efd', 4:'#198754' };
    var labels = { 0:'', 1:'Weak', 2:'Fair', 3:'Good', 4:'Strong' };

    function ruleEl(id, pass) {
        var el = document.getElementById(id);
        el.className = pass ? 'text-success' : 'text-danger';
    }

    function paint(level) {
        bars.forEach(function(b, i) {
            b.style.background = (i < level && level > 0) ? colors[level] : '#dee2e6';
        });
        var lbl = document.getElementById('strength-label');
        lbl.textContent = labels[level] ? 'Strength: ' + labels[level] : '';
        lbl.style.color = colors[level] || '#6c757d';
    }

    document.getElementById('password').addEventListener('input', function () {
        var pw = this.value;
        var rules = document.getElementById('pw-rules');
        if (pw.length === 0) { paint(0); rules.style.display = 'none'; checkMatch(); return; }
        rules.style.display = '';
        var r = {
            len  : pw.length >= 8,
            upper: /[A-Z]/.test(pw),
            lower: /[a-z]/.test(pw),
            num  : /[0-9]/.test(pw),
            sym  : /[^A-Za-z0-9]/.test(pw)
        };
        var s = Math.max(1, Object.values(r).filter(Boolean).length);
        paint(s);
        ruleEl('r-len',   r.len);
        ruleEl('r-upper', r.upper);
        ruleEl('r-lower', r.lower);
        ruleEl('r-num',   r.num);
        ruleEl('r-sym',   r.sym);
        checkMatch();
    });

    function checkMatch() {
        var pw   = document.getElementById('password').value;
        var cpw  = document.getElementById('confirm_password').value;
        var hint = document.getElementById('confirm-hint');
        if (!pw || !cpw) { hint.textContent = ''; return; }
        hint.textContent = (pw === cpw) ? 'Passwords match' : 'Passwords do not match';
        hint.style.color  = (pw === cpw) ? '#198754' : '#dc3545';
    }
    document.getElementById('confirm_password').addEventListener('input', checkMatch);

    document.getElementById('user-edit-form').addEventListener('submit', function (e) {
        var pw  = document.getElementById('password').value;
        var cpw = document.getElementById('confirm_password').value;
        if (pw && pw !== cpw) {
            e.preventDefault();
            document.getElementById('confirm-hint').textContent = 'Passwords do not match.';
            document.getElementById('confirm-hint').style.color = '#dc3545';
            document.getElementById('confirm_password').focus();
        }
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
