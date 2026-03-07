<?php
// ============================================================
//  LOGIN — Minmi Restaurent Admin
//  Place in: dashboard/login.php
// ============================================================
session_start();

// Already logged in → redirect
if (isset($_SESSION['user_id'])) {
    header('Location: pages/dashboard.php');
    exit;
}

require_once __DIR__ . '/includes/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Login success
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['user_name']  = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role']  = $user['role'];

            // Redirect based on role
            if ($user['role'] === 'admin') {
                header('Location: pages/dashboard.php');
            } else {
                header('Location: pages/orders.php');
            }
            exit;
        } else {
            $error = 'Invalid email or password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Minmi Restaurent</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0 }

        :root {
            --accent:   #e8622a;
            --accent-d: #c44e1e;
            --bg:       #0f0e0d;
            --bg-2:     #1a1917;
            --bg-3:     #242220;
            --border:   rgba(255,255,255,.08);
            --text:     #f0ede8;
            --text-2:   #a09890;
            --text-3:   #6b6560;
            --red:      #e84242;
            --green:    #3ecf8e;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        /* Background fire glow effect */
        body::before {
            content: '';
            position: fixed;
            bottom: -200px;
            left: 50%;
            transform: translateX(-50%);
            width: 600px;
            height: 600px;
            background: radial-gradient(ellipse, rgba(232,98,42,.18) 0%, rgba(232,98,42,.06) 40%, transparent 70%);
            pointer-events: none;
            animation: pulse 4s ease-in-out infinite;
        }
        body::after {
            content: '';
            position: fixed;
            top: -100px;
            right: -100px;
            width: 400px;
            height: 400px;
            background: radial-gradient(ellipse, rgba(232,98,42,.07) 0%, transparent 70%);
            pointer-events: none;
        }
        @keyframes pulse {
            0%,100% { opacity:.7; transform:translateX(-50%) scale(1) }
            50%      { opacity:1;  transform:translateX(-50%) scale(1.1) }
        }

        /* Grid pattern overlay */
        .grid-bg {
            position: fixed;
            inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,.015) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.015) 1px, transparent 1px);
            background-size: 40px 40px;
            pointer-events: none;
        }

        /* Login card */
        .login-wrap {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 420px;
            padding: 20px;
            animation: slideUp .5s cubic-bezier(.16,1,.3,1) both;
        }
        @keyframes slideUp {
            from { opacity:0; transform:translateY(28px) }
            to   { opacity:1; transform:translateY(0) }
        }

        .login-card {
            background: var(--bg-2);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 32px 80px rgba(0,0,0,.6), 0 0 0 1px rgba(255,255,255,.04);
        }

        /* Brand */
        .brand {
            text-align: center;
            margin-bottom: 36px;
        }
        .brand-icon {
            font-size: 2.8rem;
            display: block;
            margin-bottom: 12px;
            animation: flicker 3s ease-in-out infinite;
        }
        @keyframes flicker {
            0%,100% { filter:drop-shadow(0 0 8px rgba(232,98,42,.8)) }
            50%      { filter:drop-shadow(0 0 18px rgba(232,98,42,1)) }
        }
        .brand-name {
            font-family: 'DM Serif Display', serif;
            font-size: 1.7rem;
            color: var(--text);
            letter-spacing: -.03em;
            display: block;
        }
        .brand-name em {
            color: var(--accent);
            font-style: normal;
        }
        .brand-sub {
            color: var(--text-3);
            font-size: .78rem;
            margin-top: 6px;
            display: block;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        /* Error */
        .error-box {
            background: rgba(232,66,66,.1);
            border: 1px solid rgba(232,66,66,.3);
            border-radius: 10px;
            padding: 11px 14px;
            font-size: .82rem;
            color: #f87171;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
            animation: shake .4s cubic-bezier(.36,.07,.19,.97) both;
        }
        @keyframes shake {
            10%,90%  { transform:translateX(-2px) }
            20%,80%  { transform:translateX(4px) }
            30%,50%,70% { transform:translateX(-4px) }
            40%,60%  { transform:translateX(4px) }
        }

        /* Form */
        .form-group {
            margin-bottom: 18px;
        }
        .form-label {
            display: block;
            font-size: .7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--text-3);
            margin-bottom: 7px;
        }
        .input-wrap {
            position: relative;
        }
        .input-icon {
            position: absolute;
            left: 13px;
            top: 50%;
            transform: translateY(-50%);
            font-size: .95rem;
            pointer-events: none;
        }
        .form-input {
            width: 100%;
            background: var(--bg-3);
            border: 1px solid var(--border);
            color: var(--text);
            border-radius: 10px;
            padding: 11px 40px 11px 38px;
            font-family: 'DM Sans', sans-serif;
            font-size: .88rem;
            outline: none;
            transition: border-color .2s, box-shadow .2s;
        }
        .form-input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(232,98,42,.12);
        }
        .form-input::placeholder { color: var(--text-3) }
        .eye-btn {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            font-size: .95rem;
            padding: 2px;
            color: var(--text-3);
            transition: color .2s;
        }
        .eye-btn:hover { color: var(--text) }

        /* Role selector */
        .role-tabs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-bottom: 24px;
        }
        .role-tab {
            background: var(--bg-3);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 10px;
            text-align: center;
            cursor: pointer;
            transition: all .2s;
            font-family: 'DM Sans', sans-serif;
            color: var(--text-2);
            font-size: .82rem;
            font-weight: 500;
        }
        .role-tab .role-icon { display: block; font-size: 1.3rem; margin-bottom: 4px }
        .role-tab.active {
            border-color: var(--accent);
            background: rgba(232,98,42,.1);
            color: var(--text);
        }

        /* Submit button */
        .btn-login {
            width: 100%;
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 13px;
            font-family: 'DM Sans', sans-serif;
            font-size: .92rem;
            font-weight: 600;
            cursor: pointer;
            transition: background .2s, transform .1s, box-shadow .2s;
            box-shadow: 0 4px 20px rgba(232,98,42,.3);
            margin-top: 6px;
            letter-spacing: .02em;
        }
        .btn-login:hover {
            background: var(--accent-d);
            box-shadow: 0 6px 28px rgba(232,98,42,.45);
            transform: translateY(-1px);
        }
        .btn-login:active { transform: translateY(0) }

        /* Footer */
        .login-footer {
            text-align: center;
            margin-top: 24px;
            font-size: .72rem;
            color: var(--text-3);
        }

        /* Loading state */
        .btn-login.loading {
            pointer-events: none;
            opacity: .7;
        }
    </style>
</head>
<body>
<div class="grid-bg"></div>

<div class="login-wrap">
    <div class="login-card">

        <!-- Brand -->
        <div class="brand">
            <span class="brand-icon">🔥</span>
            <span class="brand-name">Minmi<em>Restaurent</em></span>
            <span class="brand-sub">Admin Dashboard</span>
        </div>

        <!-- Role selector (visual only — role determined by DB) -->
        <div class="role-tabs">
            <div class="role-tab active" id="tab-admin" onclick="selectRole('admin')">
                <span class="role-icon">👑</span>
                Admin
            </div>
            <div class="role-tab" id="tab-cashier" onclick="selectRole('cashier')">
                <span class="role-icon">🧾</span>
                Cashier
            </div>
        </div>

        <!-- Error message -->
        <?php if ($error): ?>
        <div class="error-box">⚠️ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Login form -->
        <form method="POST" action="login.php" onsubmit="handleSubmit(this)">
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <div class="input-wrap">
                    <span class="input-icon">📧</span>
                    <input class="form-input" type="email" name="email"
                           placeholder="your@email.com"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           required autofocus>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Password</label>
                <div class="input-wrap">
                    <span class="input-icon">🔑</span>
                    <input class="form-input" type="password" name="password"
                           id="passwordInput" placeholder="Enter your password" required>
                    <button type="button" class="eye-btn" onclick="togglePw()">👁️</button>
                </div>
            </div>

            <button type="submit" class="btn-login" id="loginBtn">
                Sign In →
            </button>
        </form>

        <div class="login-footer">
            🔒 Secure access · Minmi Restaurent &copy; <?= date('Y') ?>
        </div>
    </div>
</div>

<script>
function selectRole(role) {
    document.getElementById('tab-admin').classList.toggle('active', role === 'admin');
    document.getElementById('tab-cashier').classList.toggle('active', role === 'cashier');
    // Pre-fill email hint
    const emailInput = document.querySelector('input[name="email"]');
    if (!emailInput.value) {
        emailInput.placeholder = role === 'admin' ? 'admin@minmi.com' : 'cashier@minmi.com';
    }
}

function togglePw() {
    const input = document.getElementById('passwordInput');
    const btn   = document.querySelector('.eye-btn');
    const show  = input.type === 'password';
    input.type  = show ? 'text' : 'password';
    btn.textContent = show ? '🙈' : '👁️';
}

function handleSubmit(form) {
    const btn = document.getElementById('loginBtn');
    btn.textContent = '⏳ Signing in…';
    btn.classList.add('loading');
}
</script>
</body>
</html>
