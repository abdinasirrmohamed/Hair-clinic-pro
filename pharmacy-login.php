<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect(current_role() === 'Pharmacy User' ? '/pharmacy/medicines.php' : '/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT id, username, password, full_name, role, status FROM users WHERE username = ? AND role = 'Pharmacy User' LIMIT 1");
    $stmt->bind_param('s', $username);
    $user = fetch_one($stmt);

    if ($user && $user['status'] === 'Active' && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $user['id'];
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['admin_name'] = $user['full_name'];
        $_SESSION['admin_role'] = $user['role'];
        log_activity('Login', 'Authentication', $user['id']);
        redirect('/pharmacy/medicines.php');
    }

    $error = 'Invalid pharmacy credentials. Please try again.';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pharmacy sign-in â€” Hair Clinic Pro</title>
    <meta name="description" content="Secure pharmacy staff login for Hair Clinic Pro POS.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:        #0d1117;
            --surface:   #161b22;
            --border:    rgba(255,255,255,0.08);
            --accent:    #3fb950;
            --accent-h:  #56d469;
            --text:      #e6edf3;
            --muted:     #8b949e;
            --danger:    #f85149;
            --danger-bg: rgba(248,81,73,0.12);
            --radius:    14px;
            --transition: 220ms cubic-bezier(0.4,0,0.2,1);
        }

        html { scroll-behavior: smooth; }

        body {
            background-color: var(--bg);
            color: var(--text);
            font-family: 'Outfit', system-ui, sans-serif;
            font-size: 1rem;
            line-height: 1.6;
            min-height: 100dvh;
            display: grid;
            place-items: center;
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
        }

        /* Ambient gradient blobs â€” green-tinted for pharmacy */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse 60% 50% at 15% 15%, rgba(63,185,80,0.11) 0%, transparent 70%),
                radial-gradient(ellipse 50% 60% at 80% 80%, rgba(61,142,248,0.06) 0%, transparent 70%);
            pointer-events: none;
            z-index: 0;
        }

        /* Subtle noise grain */
        body::after {
            content: '';
            position: fixed;
            inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.04'/%3E%3C/svg%3E");
            pointer-events: none;
            z-index: 0;
            opacity: 0.45;
        }

        .skip-link {
            position: absolute;
            top: -100%;
            left: 1rem;
            background: var(--accent);
            color: #0d1117;
            padding: .5rem 1rem;
            border-radius: 6px;
            font-weight: 600;
            z-index: 100;
            transition: top var(--transition);
        }
        .skip-link:focus { top: 1rem; }

        /* Card */
        .login-card {
            position: relative;
            z-index: 1;
            width: min(440px, 100%);
            background: rgba(22, 27, 34, 0.85);
            backdrop-filter: blur(24px) saturate(160%);
            -webkit-backdrop-filter: blur(24px) saturate(160%);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow:
                0 0 0 1px rgba(255,255,255,0.04) inset,
                0 1px 0 rgba(255,255,255,0.06) inset,
                0 24px 64px rgba(0,0,0,0.6),
                0 4px 16px rgba(63,185,80,0.08);
            padding: 2.5rem 2.25rem 2rem;
            animation: card-in 0.5s cubic-bezier(0.22,1,0.36,1) both;
        }

        @keyframes card-in {
            from { opacity: 0; transform: translateY(18px) scale(0.98); }
            to   { opacity: 1; transform: translateY(0)   scale(1); }
        }

        /* Brand */
        .brand {
            display: flex;
            align-items: center;
            gap: .75rem;
            margin-bottom: 2rem;
        }

        .brand-icon {
            width: 2.6rem;
            height: 2.6rem;
            background: linear-gradient(135deg, #3fb950 0%, #1a8f35 100%);
            border-radius: 10px;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            box-shadow: 0 4px 14px rgba(63,185,80,0.35);
        }

        .brand-icon svg {
            width: 1.25rem;
            height: 1.25rem;
            fill: #fff;
        }

        .brand-text strong {
            display: block;
            font-size: 1rem;
            font-weight: 700;
            color: var(--text);
            line-height: 1.2;
        }

        .brand-text span {
            display: block;
            font-size: .75rem;
            font-weight: 500;
            color: var(--muted);
            letter-spacing: .04em;
        }

        /* Heading */
        .login-heading {
            margin-bottom: 1.75rem;
        }

        .login-heading h1 {
            font-size: 1.65rem;
            font-weight: 700;
            letter-spacing: -.03em;
            color: var(--text);
            line-height: 1.2;
            text-wrap: balance;
        }

        .login-heading p {
            color: var(--muted);
            font-size: .9rem;
            margin-top: .35rem;
        }

        /* Error */
        .alert-error {
            background: var(--danger-bg);
            border: 1px solid rgba(248,81,73,0.3);
            border-radius: 8px;
            color: var(--danger);
            font-size: .875rem;
            font-weight: 500;
            padding: .75rem 1rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: flex-start;
            gap: .5rem;
        }
        .alert-error svg { flex-shrink: 0; margin-top: 1px; }

        /* Fields */
        .field { margin-bottom: 1.1rem; }

        .field label {
            display: block;
            font-size: .82rem;
            font-weight: 600;
            color: #c9d1d9;
            margin-bottom: .45rem;
            letter-spacing: .01em;
        }

        .input-wrap { position: relative; }

        .input-wrap input {
            width: 100%;
            background: rgba(255,255,255,0.04);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            color: var(--text);
            font: inherit;
            font-size: .95rem;
            height: 2.9rem;
            padding: 0 2.75rem 0 .9rem;
            outline: none;
            transition: border-color var(--transition), box-shadow var(--transition), background var(--transition);
        }
        .input-wrap input::placeholder { color: var(--muted); }
        .input-wrap input:focus {
            border-color: var(--accent);
            background: rgba(63,185,80,0.06);
            box-shadow: 0 0 0 3px rgba(63,185,80,0.15);
        }

        .toggle-vis {
            position: absolute;
            right: .7rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--muted);
            cursor: pointer;
            padding: .25rem;
            display: grid;
            place-items: center;
            transition: color var(--transition);
        }
        .toggle-vis:hover { color: var(--text); }
        .toggle-vis svg { width: 1.1rem; height: 1.1rem; }

        /* Strength meter */
        .strength-bar {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: .3rem;
            margin-top: .45rem;
        }
        .strength-bar span {
            height: 3px;
            border-radius: 999px;
            background: rgba(255,255,255,0.1);
            transition: background var(--transition);
        }
        .strength-bar[data-level="1"] span:nth-child(1) { background: #f85149; }
        .strength-bar[data-level="2"] span:nth-child(-n+2) { background: #e3b341; }
        .strength-bar[data-level="3"] span:nth-child(-n+3) { background: #58a6ff; }
        .strength-bar[data-level="4"] span { background: #3fb950; }

        .strength-label {
            font-size: .75rem;
            color: var(--muted);
            margin-top: .3rem;
            min-height: 1em;
            transition: color var(--transition);
        }
        .strength-label[data-level="1"] { color: #f85149; }
        .strength-label[data-level="2"] { color: #e3b341; }
        .strength-label[data-level="3"] { color: #58a6ff; }
        .strength-label[data-level="4"] { color: #3fb950; }

        /* Button */
        .btn-login {
            width: 100%;
            height: 2.9rem;
            background: var(--accent);
            border: none;
            border-radius: 8px;
            color: #0d1117;
            cursor: pointer;
            font: inherit;
            font-size: .95rem;
            font-weight: 600;
            letter-spacing: .01em;
            margin-top: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            transition: background var(--transition), transform var(--transition), box-shadow var(--transition);
            box-shadow: 0 4px 14px rgba(63,185,80,0.3);
        }
        .btn-login:hover {
            background: var(--accent-h);
            box-shadow: 0 6px 20px rgba(63,185,80,0.4);
            transform: translateY(-1px);
        }
        .btn-login:active {
            transform: translateY(0) scale(0.98);
            box-shadow: 0 2px 8px rgba(63,185,80,0.2);
        }
        .btn-login:focus-visible {
            outline: 2px solid var(--accent);
            outline-offset: 3px;
        }

        /* Footer */
        .login-footer {
            margin-top: 1.5rem;
            padding-top: 1.25rem;
            border-top: 1px solid var(--border);
            text-align: center;
            font-size: .82rem;
            color: var(--muted);
        }
        .login-footer a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 500;
            transition: color var(--transition);
        }
        .login-footer a:hover { color: var(--accent-h); text-decoration: underline; }
        .login-footer a:focus-visible { outline: 2px solid var(--accent); border-radius: 2px; }
    </style>
    <script>if(localStorage.getItem('hcp_theme')==='dark'){document.documentElement.setAttribute('data-theme','dark');}</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="<?= BASE_URL ?>/css/dark-role.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body>
    <button class="dark-toggle" id="darkToggle" title="Toggle dark mode" type="button" style="position:fixed; top:1.5rem; right:2rem; border:0; background:rgba(0,0,0,0.2); width:40px; height:40px; border-radius:50%; color:var(--text-muted); font-size:1.2rem; cursor:pointer; z-index:100; transition: all var(--transition); display:grid; place-items:center;">
        <i class="bi bi-moon-fill" id="darkIcon"></i>
    </button>
    <a href="#main-login" class="skip-link">Skip to sign-in form</a>

    <main class="login-card" id="main-login" role="main">
        <!-- Brand -->
        <div class="brand" aria-label="Hair Clinic Pro â€” Pharmacy">
            <div class="brand-icon" aria-hidden="true">
                <!-- Pill / capsule icon -->
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M4.5 12.75a7.25 7.25 0 1 1 14.5 0 7.25 7.25 0 0 1-14.5 0zm7.25-5.75a5.75 5.75 0 1 0 0 11.5 5.75 5.75 0 0 0 0-11.5zm-2 5.75h4v1.5h-4v-1.5zm0-2.5h4v1.5h-4V10zm1-2h2v1.5h-2V8z"/>
                </svg>
            </div>
            <div class="brand-text">
                <strong>Hair Clinic Pro</strong>
                <span>Pharmacy POS</span>
            </div>
        </div>

        <!-- Heading -->
        <div class="login-heading">
            <h1>Pharmacy sign-in</h1>
            <p>Access the dispensary and point-of-sale system.</p>
        </div>

        <!-- Error -->
        <?php if ($error): ?>
        <div class="alert-error" role="alert" aria-live="assertive">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                <circle cx="8" cy="8" r="7" stroke="#f85149" stroke-width="1.5"/>
                <path d="M8 5v3.5M8 10.5v.5" stroke="#f85149" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
            <?= e($error) ?>
        </div>
        <?php endif; ?>

        <!-- Form -->
        <form method="post" novalidate id="login-form">
            <div class="field">
                <label for="username">Pharmacy username</label>
                <div class="input-wrap">
                    <input
                        id="username"
                        type="text"
                        name="username"
                        autocomplete="username"
                        required
                        autofocus
                        spellcheck="false"
                        aria-required="true"
                    >
                </div>
            </div>

            <div class="field">
                <label for="password">Password</label>
                <div class="input-wrap">
                    <input
                        id="password"
                        type="password"
                        name="password"
                        autocomplete="current-password"
                        required
                        aria-required="true"
                        aria-describedby="strength-label"
                    >
                    <button
                        type="button"
                        class="toggle-vis"
                        id="toggle-vis"
                        aria-label="Show password"
                        aria-pressed="false"
                    >
                        <svg id="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                        <svg id="icon-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none">
                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                            <line x1="1" y1="1" x2="23" y2="23"/>
                        </svg>
                    </button>
                </div>
                <div class="strength-bar" id="strength-bar" data-level="0" aria-hidden="true">
                    <span></span><span></span><span></span><span></span>
                </div>
                <p class="strength-label" id="strength-label" data-level="0" aria-live="polite"></p>
            </div>

            <button type="submit" class="btn-login" id="submit-btn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                    <polyline points="10 17 15 12 10 7"/>
                    <line x1="15" y1="12" x2="3" y2="12"/>
                </svg>
                Sign in to pharmacy
            </button>
        </form>

        <div class="login-footer">
            <a href="<?= BASE_URL ?>/login.php">â† Back to staff login</a>
        </div>
    </main>

    <script>
    (function () {
        var pwInput   = document.getElementById('password');
        var toggleBtn = document.getElementById('toggle-vis');
        var iconEye   = document.getElementById('icon-eye');
        var iconOff   = document.getElementById('icon-eye-off');

        toggleBtn.addEventListener('click', function () {
            var isHidden = pwInput.type === 'password';
            pwInput.type = isHidden ? 'text' : 'password';
            iconEye.style.display = isHidden ? 'none' : '';
            iconOff.style.display = isHidden ? '' : 'none';
            toggleBtn.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
            toggleBtn.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
        });

        var strengthBar   = document.getElementById('strength-bar');
        var strengthLabel = document.getElementById('strength-label');
        var labels = ['', 'Weak', 'Fair', 'Good', 'Strong'];

        function scorePassword(pw) {
            if (!pw) return 0;
            var score = 0;
            if (pw.length >= 8)  score++;
            if (pw.length >= 12) score++;
            if (/[A-Z]/.test(pw) && /[a-z]/.test(pw)) score++;
            if (/[0-9]/.test(pw)) score++;
            if (/[^A-Za-z0-9]/.test(pw)) score++;
            if (score <= 1) return 1;
            if (score === 2) return 2;
            if (score === 3) return 3;
            return 4;
        }

        pwInput.addEventListener('input', function () {
            var val = pwInput.value;
            if (!val) {
                strengthBar.dataset.level = 0;
                strengthLabel.dataset.level = 0;
                strengthLabel.textContent = '';
                return;
            }
            var level = scorePassword(val);
            strengthBar.dataset.level = level;
            strengthLabel.dataset.level = level;
            strengthLabel.textContent = labels[level];
        });

        var form = document.getElementById('login-form');
        form.addEventListener('submit', function (e) {
            var user = document.getElementById('username').value.trim();
            var pass = pwInput.value;
            if (!user || !pass) {
                e.preventDefault();
                if (!user) document.getElementById('username').focus();
                else pwInput.focus();
            }
        });
    })();
    </script>
<script>
function toggleDark() {
    var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    if (isDark) {
        document.documentElement.removeAttribute('data-theme');
        localStorage.setItem('hcp_theme', 'light');
        document.getElementById('darkIcon').className = 'bi bi-moon-fill';
    } else {
        document.documentElement.setAttribute('data-theme', 'dark');
        localStorage.setItem('hcp_theme', 'dark');
        document.getElementById('darkIcon').className = 'bi bi-sun-fill';
    }
}
(function () {
    if (localStorage.getItem('hcp_theme') === 'dark') {
        var icon = document.getElementById('darkIcon');
        if (icon) icon.className = 'bi bi-sun-fill';
    }
})();
</script><script>
document.addEventListener("DOMContentLoaded", function() {
    if (typeof IntersectionObserver !== 'undefined') {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry, index) => {
                if (entry.isIntersecting) {
                    setTimeout(() => {
                        entry.target.classList.add('animate-reveal');
                    }, index * 80);
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.05, rootMargin: '0px 0px -20px 0px' });
        
        const elementsToAnimate = document.querySelectorAll('.stat-card, .table-wrap, .form-panel, .metric-card, .recent-panel, .appointments-panel, .dashboard-appointment-hub, .hub-appointment-card, .appointment-card, .patient-row');
        elementsToAnimate.forEach(el => observer.observe(el));
    }
});
</script>
<script id="bulletproof-dark-toggle">
document.addEventListener('DOMContentLoaded', function() {
    var btn = document.getElementById('darkToggle');
    if (btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            var icon = document.getElementById('darkIcon');
            if (isDark) {
                document.documentElement.removeAttribute('data-theme');
                localStorage.setItem('hcp_theme', 'light');
                if (icon) icon.className = 'bi bi-moon-fill';
            } else {
                document.documentElement.setAttribute('data-theme', 'dark');
                localStorage.setItem('hcp_theme', 'dark');
                if (icon) icon.className = 'bi bi-sun-fill';
            }
        });
    }
});
</script>
</body>
</html>





