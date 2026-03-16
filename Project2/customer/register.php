<?php
// ============================================================
//  REGISTER — Minmi Restaurent Customer
//  Place in: customer/register.php
// ============================================================
require_once __DIR__ . '/includes/db.php';

if (isset($_SESSION['customer_id'])) { header('Location: index.php'); exit; }

$msg = ''; $msg_type = 'danger';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $phone    = trim($_POST['phone']    ?? '');
    $password = $_POST['password']      ?? '';
    $confirm  = $_POST['confirm']       ?? '';

    if (!$name || !$email || !$password) {
        $msg = '⚠️ Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = '⚠️ Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $msg = '⚠️ Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $msg = '⚠️ Passwords do not match.';
    } else {
        // Check if email already exists
        $check = $pdo->prepare("SELECT id FROM customers WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            $msg = '⚠️ An account with this email already exists. <a href="login.php" style="color:var(--accent)">Login instead</a>';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);

            // Check if id column is auto-increment by seeing if there's a max id
            $max_id = $pdo->query("SELECT MAX(id) FROM customers")->fetchColumn();
            $new_id = is_numeric($max_id) ? intval($max_id) + 1 : 1;

            // Try insert with explicit id first (for non-auto-increment tables)
            try {
                $pdo->prepare("INSERT INTO customers (id, name, email, phone, password, status, joined)
                               VALUES (?, ?, ?, ?, ?, 'active', CURDATE())")
                    ->execute([$new_id, $name, $email, $phone, $hashed]);
            } catch (PDOException $e) {
                // If id is auto-increment, insert without id
                $pdo->prepare("INSERT INTO customers (name, email, phone, password, status, joined)
                               VALUES (?, ?, ?, ?, 'active', CURDATE())")
                    ->execute([$name, $email, $phone, $hashed]);
                $new_id = $pdo->lastInsertId();
            }

            $_SESSION['customer_id']   = $new_id;
            $_SESSION['customer_name'] = $name;
            $_SESSION['customer_email']= $email;

            header('Location: index.php?welcome=1');
            exit;
        }
    }
}

$page_title = 'Create Account';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — Minmi Restaurent</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root { --accent: #e8622a; --accent-d: #c94f1e; --bg: #faf8f5; --border: #e8e0d5; --text: #1a1512; --text-2: #4a3f35; --text-3: #8a7a6e; --radius: 10px; }
        body { font-family: 'DM Sans', sans-serif; background: var(--bg); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .wrap { width: 100%; max-width: 460px; }
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
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
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
        <h2>Create an account</h2>
        <p>Join us to order food, make reservations and more.</p>

        <?php if ($msg): ?>
        <div class="flash"><?= $msg ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label class="form-label">Full Name *</label>
                <input class="form-input" type="text" name="name" placeholder="e.g. Kasun Jayasundara"
                       value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">Email Address *</label>
                <input class="form-input" type="email" name="email" placeholder="you@email.com"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">Phone Number</label>
                <input class="form-input" type="tel" name="phone" placeholder="+94 77 000 0000"
                       value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Password *</label>
                    <input class="form-input" type="password" name="password" placeholder="Min. 6 characters" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm Password *</label>
                    <input class="form-input" type="password" name="confirm" placeholder="Repeat password" required>
                </div>
            </div>
            <button type="submit" class="btn">Create Account →</button>
        </form>
    </div>
    <div class="bottom">Already have an account? <a href="login.php">Login here</a></div>
</div>
</body>
</html>