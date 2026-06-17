<?php
require_once __DIR__ . '/../includes/auth.php';
require_roles(['Administrator']);

$roles  = array_keys(role_permissions());
$errors = [];

// ── POST handler BEFORE any HTML output ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username  = trim($_POST['username']  ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $role      = $_POST['role']           ?? '';
    $status    = $_POST['status']         ?? 'Active';
    $password  = $_POST['password']       ?? '';

    if ($username === '')                         $errors[] = 'Username is required.';
    if ($full_name === '')                        $errors[] = 'Full name is required.';
    if (!in_array($role, $roles, true))           $errors[] = 'A valid role is required.';

    // Strong password rules
    if (strlen($password) < 8)                   $errors[] = 'Password must be at least 8 characters.';
    if (!preg_match('/[A-Z]/', $password))        $errors[] = 'Password must contain at least one uppercase letter.';
    if (!preg_match('/[a-z]/', $password))        $errors[] = 'Password must contain at least one lowercase letter.';
    if (!preg_match('/[0-9]/', $password))        $errors[] = 'Password must contain at least one number.';
    if (!preg_match('/[^A-Za-z0-9]/', $password)) $errors[] = 'Password must contain at least one symbol (e.g. @, #, !).';

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('INSERT INTO users (username, password, full_name, role, status) VALUES (?, ?, ?, ?, ?)');
        $stmt->bind_param('sssss', $username, $hash, $full_name, $role, $status);
        try {
            $stmt->execute();
            log_activity('Created user account', 'Users', $conn->insert_id);
            flash('success', 'User account created successfully.');
            redirect('/users/view.php');
        } catch (mysqli_sql_exception $e) {
            $errors[] = 'That username is already taken. Choose a different one.';
        }
    }
}

$page_title = 'Add user account';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="patient-head">
    <div>
        <h1>Add user account</h1>
        <p>User accounts are for system login only. For clinical staff profiles, use the Doctors section.</p>
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
    <form method="post" id="user-add-form" class="row g-3" novalidate>

        <div class="col-md-6">
            <label class="form-label fw-semibold" for="full_name">Full name <span class="text-danger">*</span></label>
            <input class="form-control" id="full_name" name="full_name"
                   value="<?= e($_POST['full_name'] ?? '') ?>" required autofocus>
        </div>

        <div class="col-md-6">
            <label class="form-label fw-semibold" for="username">Username <span class="text-danger">*</span></label>
            <input class="form-control" id="username" name="username"
                   value="<?= e($_POST['username'] ?? '') ?>"
                   autocomplete="off" spellcheck="false" required>
            <div class="form-text">Used to sign in. No spaces allowed.</div>
        </div>

        <div class="col-md-6">
            <label class="form-label fw-semibold" for="role">Role <span class="text-danger">*</span></label>
            <select class="form-select" id="role" name="role" required>
                <?php foreach ($roles as $r): ?>
                    <option value="<?= e($r) ?>" <?= ($_POST['role'] ?? '') === $r ? 'selected' : '' ?>>
                        <?= e($r) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-6">
            <label class="form-label fw-semibold" for="status">Status</label>
            <select class="form-select" id="status" name="status">
                <option <?= ($_POST['status'] ?? 'Active') === 'Active'   ? 'selected' : '' ?>>Active</option>
                <option <?= ($_POST['status'] ?? 'Active') === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>

        <!-- Password -->
        <div class="col-md-6">
            <label class="form-label fw-semibold" for="password">
                Password <span class="text-danger">*</span>
            </label>
            <div class="input-group">
                <input class="form-control" type="password" id="password" name="password"
                       autocomplete="new-password" required aria-describedby="pw-rules">
                <button class="btn btn-outline-secondary" type="button" id="toggle-pw"
                        aria-label="Show password" title="Show/hide password">
                    <i class="bi bi-eye" id="pw-eye-icon"></i>
                </button>
            </div>
            <!-- Strength bar -->
            <div class="d-flex gap-1 mt-2" id="strength-bar" aria-hidden="true">
                <div class="flex-fill rounded" id="sb1" style="height:4px;background:#dee2e6;transition:background .2s"></div>
                <div class="flex-fill rounded" id="sb2" style="height:4px;background:#dee2e6;transition:background .2s"></div>
                <div class="flex-fill rounded" id="sb3" style="height:4px;background:#dee2e6;transition:background .2s"></div>
                <div class="flex-fill rounded" id="sb4" style="height:4px;background:#dee2e6;transition:background .2s"></div>
            </div>
            <div id="strength-label" class="form-text mt-1" aria-live="polite"></div>
            <ul class="form-text mt-1 mb-0 ps-3" id="pw-rules">
                <li id="r-len"  class="text-danger">At least 8 characters</li>
                <li id="r-upper" class="text-danger">One uppercase letter (A–Z)</li>
                <li id="r-lower" class="text-danger">One lowercase letter (a–z)</li>
                <li id="r-num"  class="text-danger">One number (0–9)</li>
                <li id="r-sym"  class="text-danger">One symbol (@, #, !, etc.)</li>
            </ul>
        </div>

        <div class="col-md-6">
            <label class="form-label fw-semibold" for="confirm_password">Confirm password <span class="text-danger">*</span></label>
            <div class="input-group">
                <input class="form-control" type="password" id="confirm_password"
                       autocomplete="new-password" aria-describedby="confirm-hint">
                <button class="btn btn-outline-secondary" type="button" id="toggle-cpw"
                        aria-label="Show confirm password">
                    <i class="bi bi-eye" id="cpw-eye-icon"></i>
                </button>
            </div>
            <div id="confirm-hint" class="form-text mt-1" aria-live="polite"></div>
        </div>

        <div class="col-12 d-flex gap-2 mt-2">
            <button class="btn btn-primary" type="submit" id="submit-btn">
                <i class="bi bi-person-plus"></i> Create account
            </button>
            <a class="btn btn-outline-secondary" href="view.php">Cancel</a>
        </div>
    </form>
</section>

<script>
(function () {
    // ── helpers ────────────────────────────────────────────────────────
    function makeToggle(btnId, inputId, iconId) {
        document.getElementById(btnId).addEventListener('click', function () {
            var inp  = document.getElementById(inputId);
            var icon = document.getElementById(iconId);
            var show = inp.type === 'password';
            inp.type = show ? 'text' : 'password';
            icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
            this.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        });
    }
    makeToggle('toggle-pw',  'password',         'pw-eye-icon');
    makeToggle('toggle-cpw', 'confirm_password',  'cpw-eye-icon');

    // ── password rules ─────────────────────────────────────────────────
    var bars   = ['sb1','sb2','sb3','sb4'].map(function(id){ return document.getElementById(id); });
    var colors = { 1:'#dc3545', 2:'#fd7e14', 3:'#0d6efd', 4:'#198754' };
    var labels = { 0:'', 1:'Weak', 2:'Fair', 3:'Good', 4:'Strong' };

    function check(pw) {
        var rules = {
            len  : pw.length >= 8,
            upper: /[A-Z]/.test(pw),
            lower: /[a-z]/.test(pw),
            num  : /[0-9]/.test(pw),
            sym  : /[^A-Za-z0-9]/.test(pw)
        };
        return rules;
    }

    function score(r) {
        return Object.values(r).filter(Boolean).length;
    }

    function paint(level) {
        bars.forEach(function(b, i) {
            b.style.background = (i < level && level > 0) ? colors[level] : '#dee2e6';
        });
        var lbl = document.getElementById('strength-label');
        lbl.textContent = labels[level] ? 'Strength: ' + labels[level] : '';
        lbl.style.color = colors[level] || '#6c757d';
    }

    function ruleEl(id, pass) {
        var el = document.getElementById(id);
        el.className = pass ? 'text-success' : 'text-danger';
    }

    document.getElementById('password').addEventListener('input', function () {
        var pw = this.value;
        var r  = check(pw);
        var s  = pw.length === 0 ? 0 : Math.max(1, score(r));
        paint(s);
        ruleEl('r-len',   r.len);
        ruleEl('r-upper', r.upper);
        ruleEl('r-lower', r.lower);
        ruleEl('r-num',   r.num);
        ruleEl('r-sym',   r.sym);
        checkMatch();
    });

    // ── confirm match ──────────────────────────────────────────────────
    function checkMatch() {
        var pw  = document.getElementById('password').value;
        var cpw = document.getElementById('confirm_password').value;
        var hint = document.getElementById('confirm-hint');
        if (!cpw) { hint.textContent = ''; return; }
        if (pw === cpw) {
            hint.textContent = 'Passwords match';
            hint.style.color = '#198754';
        } else {
            hint.textContent = 'Passwords do not match';
            hint.style.color = '#dc3545';
        }
    }
    document.getElementById('confirm_password').addEventListener('input', checkMatch);

    // ── prevent submit if confirm doesn't match ────────────────────────
    document.getElementById('user-add-form').addEventListener('submit', function (e) {
        var pw  = document.getElementById('password').value;
        var cpw = document.getElementById('confirm_password').value;
        if (pw !== cpw) {
            e.preventDefault();
            document.getElementById('confirm-hint').textContent = 'Passwords do not match — please fix before submitting.';
            document.getElementById('confirm-hint').style.color = '#dc3545';
            document.getElementById('confirm_password').focus();
        }
    });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
