<?php
// ============================================================
//  LOGIN — Minmi Restaurent Customer
//  Place in: customer/login.php
// ============================================================
require_once __DIR__ . '/includes/db.php';

if (isset($_SESSION['customer_id'])) { header('Location: index.php'); exit; }

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password']      ?? '';

    if (!$email || !$password) {
        $msg = '⚠️ Please enter your email and password.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM customers WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $customer = $stmt->fetch();

        if ($customer && password_verify($password, $customer['password'])) {
            $_SESSION['customer_id']   = $customer['id'];
            $_SESSION['customer_name'] = $customer['name'];
            $_SESSION['customer_email']= $customer['email'];

            // Update last seen / orders_count refresh
            $redirect = $_GET['redirect'] ?? 'index.php';
            header('Location: ' . $redirect);
            exit;
        } else {
            $msg = '❌ Incorrect email or password. Please try again.';
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
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root { --accent: #e8622a; --accent-d: #c94f1e; --bg: #faf8f5; --border: #e8e0d5; --text: #1a1512; --text-2: #4a3f35; --text-3: #8a7a6e; --radius: 10px; }
        body { font-family: 'DM Sans', sans-serif; background: var(--bg); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .wrap { width: 100%; max-width: 420px; }
        .logo { text-align: center; margin-bottom: 28px; }
        .logo a { font-family: 'DM Serif Display', serif; font-size: 1.8rem; color: var(--accent); text-decoration: none; }
        .logo p { color: var(--text-3); font-size: .82rem; margin-top: 4px; }
        .card { background: #fff; border: 1px solid var(--border); border-radius: 16px; padding: 32px; box-shadow: 0 4px 24px rgba(0,0,0,.08); }
        .card h2 { font-family: 'DM Serif Display', serif; font-size: 1.4rem; font-weight: 400; margin-bottom: 6px; }
        .card p { color: var(--text-3); font-size: .85rem; margin-bottom: 24px; }
        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--text-3); margin-bottom: 6px; }
        .form-input { width: 100%; background: #faf8f5; border: 1.5px solid var(--border); color: var(--text); border-radius: var(--radius); padding: 11px 14px; font-family: 'DM Sans', sans-serif; font-size: .9rem; outline: none; transition: border-color .2s; }
        .form-input:focus { border-color: var(--accent); background: #fff; }
        .btn { width: 100%; padding: 12px; border-radius: var(--radius); font-family: 'DM Sans', sans-serif; font-size: .95rem; font-weight: 600; cursor: pointer; border: none; background: var(--accent); color: #fff; transition: background .2s; margin-top: 4px; }
        .btn:hover { background: var(--accent-d); }
        .flash { padding: 12px 16px; border-radius: var(--radius); font-size: .85rem; margin-bottom: 18px; background: rgba(232,66,66,.1); color: #a82020; border: 1px solid rgba(232,66,66,.3); }
        .bottom { text-align: center; margin-top: 20px; font-size: .85rem; color: var(--text-3); }
        .bottom a { color: var(--accent); text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="logo">
        <a href="index.php">🔥 Minmi Restaurent</a>
        <p>Fire-crafted flavours, every night</p>
    </div>
    <div class="card">
        <h2>Welcome back</h2>
        <p>Login to your account to order food and make reservations.</p>

        <?php if ($msg): ?>
        <div class="flash"><?= $msg ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input class="form-input" type="email" name="email" placeholder="you@email.com"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
            </div>
            <div class="form-group">
                <label class="form-label">Password</label>
                <input class="form-input" type="password" name="password" placeholder="Your password" required>
            </div>
            <button type="submit" class="btn">Login →</button>
        </form>
    </div>
    <div class="bottom">Don't have an account? <a href="register.php">Register here</a></div>
</div>
</body>
</html>
