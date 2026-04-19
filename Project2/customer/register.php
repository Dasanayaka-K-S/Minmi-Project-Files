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
        $check = $pdo->prepare("SELECT id FROM customers WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            $msg = '⚠️ An account with this email already exists. <a href="login.php" style="color:var(--orange);font-weight:700">Login instead</a>';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $max_id = $pdo->query("SELECT MAX(id) FROM customers")->fetchColumn();
            $new_id = is_numeric($max_id) ? intval($max_id) + 1 : 1;
            try {
                $pdo->prepare("INSERT INTO customers (id, name, email, phone, password, status, joined) VALUES (?, ?, ?, ?, ?, 'active', CURDATE())")
                    ->execute([$new_id, $name, $email, $phone, $hashed]);
            } catch (PDOException $e) {
                $pdo->prepare("INSERT INTO customers (name, email, phone, password, status, joined) VALUES (?, ?, ?, ?, 'active', CURDATE())")
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account — Minmi Restaurent</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;0,900;1,400&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --fire:#FF4500;--orange:#FF6B2B;--amber:#FF9500;--yellow:#FFD60A;
            --cream:#FFF8F0;--brown:#3D1F0A;--brown-2:#6B3A1F;--brown-3:#9E6B4A;
        }
        html { height: 100%; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1fr 1fr;
            background: var(--brown);
            overflow: hidden;
        }

        /* ── LEFT PANEL ── */
        .left-panel {
            position: relative;
            display: flex; flex-direction: column;
            justify-content: center; align-items: flex-start;
            padding: 60px; overflow: hidden;
            background: linear-gradient(145deg, #1A0A02 0%, #2D1208 50%, #3D1F0A 100%);
        }
        .float-emoji {
            position: absolute; opacity: 0.15;
            animation: floatUp 8s ease-in-out infinite;
            user-select: none; pointer-events: none;
        }
        .float-emoji:nth-child(1){font-size:2.8rem;left:10%;bottom:-60px;animation-delay:0s;animation-duration:10s}
        .float-emoji:nth-child(2){font-size:2rem;left:28%;bottom:-60px;animation-delay:2s;animation-duration:12s}
        .float-emoji:nth-child(3){font-size:3.2rem;left:50%;bottom:-60px;animation-delay:1s;animation-duration:9s}
        .float-emoji:nth-child(4){font-size:1.8rem;left:68%;bottom:-60px;animation-delay:3.5s;animation-duration:11s}
        .float-emoji:nth-child(5){font-size:2.4rem;left:82%;bottom:-60px;animation-delay:0.5s;animation-duration:8.5s}
        @keyframes floatUp {
            0%{transform:translateY(0) rotate(0deg);opacity:0}
            10%{opacity:0.15}90%{opacity:0.08}
            100%{transform:translateY(-110vh) rotate(15deg);opacity:0}
        }
        .glow-orb{position:absolute;border-radius:50%;filter:blur(80px);pointer-events:none}
        .glow-orb-1{width:320px;height:320px;background:rgba(255,69,0,.2);top:-100px;left:-60px}
        .glow-orb-2{width:180px;height:180px;background:rgba(255,149,0,.18);bottom:40px;right:30px;animation:pulse 5s ease-in-out infinite}
        .glow-orb-3{width:140px;height:140px;background:rgba(255,69,0,.15);top:40%;left:60%;animation:pulse 6s ease-in-out infinite 1s}
        @keyframes pulse{0%,100%{transform:scale(1);opacity:.18}50%{transform:scale(1.4);opacity:.32}}
        .left-content{position:relative;z-index:2}
        .brand{display:flex;align-items:center;gap:14px;margin-bottom:40px}
        .brand-logo{width:50px;height:50px;background:linear-gradient(135deg,var(--fire),var(--amber));border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;box-shadow:0 8px 24px rgba(255,69,0,.4)}
        .brand-text{font-family:'Playfair Display',serif;font-size:1.35rem;font-weight:700;color:#fff;line-height:1.1}
        .brand-text span{display:block;font-size:.7rem;font-family:'Plus Jakarta Sans',sans-serif;font-weight:500;color:rgba(255,255,255,.4);letter-spacing:.12em;text-transform:uppercase;margin-top:2px}
        .left-heading{font-family:'Playfair Display',serif;font-size:2.8rem;font-weight:900;line-height:1.2;color:#fff;margin-bottom:18px}
        .left-heading em{font-style:italic;background:linear-gradient(135deg,var(--fire),var(--yellow));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
        .left-sub{font-size:.9rem;color:rgba(255,255,255,.48);line-height:1.75;max-width:320px;margin-bottom:40px}
        /* Steps */
        .steps{display:flex;flex-direction:column;gap:16px}
        .step{display:flex;align-items:center;gap:14px}
        .step-num{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--fire),var(--amber));display:flex;align-items:center;justify-content:center;font-size:.78rem;font-weight:800;color:#fff;flex-shrink:0;box-shadow:0 4px 12px rgba(255,69,0,.35)}
        .step-text{font-size:.85rem;color:rgba(255,255,255,.65);line-height:1.4}
        .step-text strong{display:block;color:rgba(255,255,255,.9);font-weight:700;font-size:.88rem;margin-bottom:2px}

        /* ── RIGHT PANEL ── */
        .right-panel {
            background: var(--cream);
            display: flex; flex-direction: column;
            justify-content: center; align-items: center;
            padding: 48px 48px;
            position: relative; overflow: hidden;
        }
        .right-panel::before{content:'';position:absolute;top:-120px;right:-100px;width:320px;height:320px;background:radial-gradient(circle,rgba(255,107,43,.1) 0%,transparent 70%);pointer-events:none}
        .right-panel::after{content:'';position:absolute;bottom:-100px;left:-80px;width:260px;height:260px;background:radial-gradient(circle,rgba(255,149,0,.08) 0%,transparent 70%);pointer-events:none}
        .deco-text{position:absolute;font-family:'Playfair Display',serif;font-size:8rem;font-weight:900;color:rgba(61,31,10,.04);pointer-events:none;white-space:nowrap;top:-10px;left:-10px;line-height:1;user-select:none;z-index:0}
        .form-wrap{width:100%;max-width:420px;position:relative;z-index:1}
        .form-header{margin-bottom:28px}
        .form-eyebrow{font-size:.7rem;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:var(--orange);margin-bottom:8px}
        .form-title{font-family:'Playfair Display',serif;font-size:2rem;font-weight:900;color:var(--brown);line-height:1.15;margin-bottom:6px}
        .form-subtitle{font-size:.84rem;color:var(--brown-3);line-height:1.6}

        /* Flash */
        .flash{display:flex;align-items:flex-start;gap:10px;padding:12px 16px;border-radius:12px;font-size:.83rem;font-weight:600;margin-bottom:20px;background:rgba(220,38,38,.08);color:#991B1B;border:1.5px solid rgba(220,38,38,.2);animation:slideIn .3s ease;line-height:1.5}
        @keyframes slideIn{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}

        /* Fields */
        .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
        .form-group{display:flex;flex-direction:column;gap:6px;margin-bottom:16px}
        .form-group.span2{grid-column:span 2}
        .form-label{font-size:.68rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--brown-3);transition:color .2s}
        .form-group:focus-within .form-label{color:var(--orange)}
        .input-wrap{position:relative}
        .input-icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);font-size:.95rem;pointer-events:none}
        .form-input{
            width:100%;background:#fff;border:2px solid #F0E4D4;color:var(--brown);
            border-radius:12px;padding:12px 14px 12px 42px;
            font-family:'Plus Jakarta Sans',sans-serif;font-size:.88rem;outline:none;
            transition:all .25s;box-shadow:0 2px 6px rgba(61,31,10,.04);
        }
        .form-input::placeholder{color:#C4A882}
        .form-input:focus{border-color:var(--orange);background:#fff;box-shadow:0 0 0 4px rgba(255,107,43,.1),0 2px 8px rgba(61,31,10,.06);transform:translateY(-1px)}
        .pwd-toggle{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:.95rem;color:var(--brown-3);padding:4px;transition:color .2s}
        .pwd-toggle:hover{color:var(--orange)}

        /* Password strength */
        .pwd-strength{margin-top:6px;display:flex;gap:4px;align-items:center}
        .strength-bar{flex:1;height:3px;border-radius:2px;background:#F0E4D4;transition:background .3s}
        .strength-label{font-size:.65rem;font-weight:700;color:var(--brown-3);white-space:nowrap;min-width:48px;text-align:right}

        /* Submit */
        .btn-submit{
            width:100%;padding:14px;border-radius:14px;
            font-family:'Plus Jakarta Sans',sans-serif;font-size:.92rem;font-weight:800;
            cursor:pointer;border:none;
            background:linear-gradient(135deg,var(--fire) 0%,var(--amber) 100%);
            color:#fff;transition:all .3s;position:relative;overflow:hidden;
            box-shadow:0 6px 20px rgba(255,69,0,.32);margin-top:4px;letter-spacing:.02em;
        }
        .btn-submit::before{content:'';position:absolute;top:0;left:-100%;width:100%;height:100%;background:linear-gradient(90deg,transparent,rgba(255,255,255,.15),transparent);transition:left .5s}
        .btn-submit:hover{transform:translateY(-2px);box-shadow:0 10px 28px rgba(255,69,0,.42)}
        .btn-submit:hover::before{left:100%}
        .btn-submit:active{transform:translateY(0)}

        /* Terms note */
        .terms-note{font-size:.72rem;color:var(--brown-3);text-align:center;margin-top:12px;line-height:1.6}
        .terms-note a{color:var(--orange);font-weight:600;text-decoration:none}

        .divider{display:flex;align-items:center;gap:12px;margin:16px 0;color:var(--brown-3);font-size:.76rem}
        .divider::before,.divider::after{content:'';flex:1;height:1px;background:#F0E4D4}

        .login-link{text-align:center;font-size:.84rem;color:var(--brown-3)}
        .login-link a{color:var(--orange);font-weight:800;text-decoration:none;position:relative}
        .login-link a::after{content:'';position:absolute;bottom:-2px;left:0;right:0;height:2px;background:linear-gradient(90deg,var(--fire),var(--amber));border-radius:2px;transform:scaleX(0);transition:transform .3s}
        .login-link a:hover::after{transform:scaleX(1)}

        @media(max-width:768px){body{grid-template-columns:1fr;overflow:auto}.left-panel{display:none}.right-panel{padding:36px 20px;min-height:100vh}}

         .brand-logo{
            width:52px;
            height:52px;
            background:linear-gradient(135deg,var(--fire),var(--amber));
            border-radius:14px;
            display:flex;
            align-items:center;
            justify-content:center;
            box-shadow:0 8px 24px rgba(255,69,0,.4);
            overflow:hidden;
         }

        .brand-logo img{
            width:100%;
            height:100%;
            object-fit:contain;
        }  

    </style>
</head>
<body>

<!-- LEFT -->
<div class="left-panel">
    <div class="float-emoji">🍽️</div>
    <div class="float-emoji">🔥</div>
    <div class="float-emoji">🥘</div>
    <div class="float-emoji">🍗</div>
    <div class="float-emoji">🌶️</div>
    <div class="glow-orb glow-orb-1"></div>
    <div class="glow-orb glow-orb-2"></div>
    <div class="glow-orb glow-orb-3"></div>
    <div class="left-content">
        <div class="brand">
            <div class="brand-logo">
                <img src="assets/logo.png" alt="Minmi Logo">
            </div>
            <div class="brand-text">Minmi<span>Restaurant</span></div>
        </div>
        <h1 class="left-heading">Join the<br><em>Minmi</em><br>family today.</h1>
        <p class="left-sub">Create your free account and unlock online ordering, table reservations and exclusive deals.</p>
        <div class="steps">
            <div class="step">
                <div class="step-num">1</div>
                <div class="step-text"><strong>Create your account</strong>Just your name, email and a password</div>
            </div>
            <div class="step">
                <div class="step-num">2</div>
                <div class="step-text"><strong>Browse our menu</strong>Explore 50+ fire-crafted dishes</div>
            </div>
            <div class="step">
                <div class="step-num">3</div>
                <div class="step-text"><strong>Order & enjoy</strong>Track your order in real time</div>
            </div>
        </div>
    </div>
</div>

<!-- RIGHT -->
<div class="right-panel">
    <div class="deco-text">Join</div>
    <div class="form-wrap">
        <div class="form-header">
            <div class="form-eyebrow">Get Started Free</div>
            <h2 class="form-title">Create your<br>account</h2>
            <p class="form-subtitle">Already a member? <a href="login.php" style="color:var(--orange);font-weight:700;text-decoration:none">Log in →</a></p>
        </div>

        <?php if ($msg): ?>
        <div class="flash"><?= $msg ?></div>
        <?php endif; ?>

        <form method="POST" id="regForm">
            <div class="form-grid">
                <!-- Full name -->
                <div class="form-group span2">
                    <label class="form-label">Full Name *</label>
                    <div class="input-wrap">
                        <span class="input-icon">👤</span>
                        <input class="form-input" type="text" name="name"
                               placeholder="e.g. Kasun Jayasundara"
                               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required autofocus>
                    </div>
                </div>

                <!-- Email -->
                <div class="form-group span2">
                    <label class="form-label">Email Address *</label>
                    <div class="input-wrap">
                        <span class="input-icon">📧</span>
                        <input class="form-input" type="email" name="email"
                               placeholder="you@email.com"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                    </div>
                </div>

                <!-- Phone -->
                <div class="form-group span2">
                    <label class="form-label">Phone Number <span style="color:var(--brown-3);font-weight:400;text-transform:none;letter-spacing:0"> — optional</span></label>
                    <div class="input-wrap">
                        <span class="input-icon">📞</span>
                        <input class="form-input" type="tel" name="phone"
                               placeholder="+94 77 000 0000"
                               value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                    </div>
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label class="form-label">Password *</label>
                    <div class="input-wrap">
                        <span class="input-icon">🔒</span>
                        <input class="form-input" type="password" name="password"
                               id="pwdInput" placeholder="Min. 6 chars" required
                               oninput="checkStrength(this.value)">
                        <button type="button" class="pwd-toggle" onclick="togglePwd('pwdInput','t1')" id="t1">👁️</button>
                    </div>
                    <div class="pwd-strength">
                        <div class="strength-bar" id="bar1"></div>
                        <div class="strength-bar" id="bar2"></div>
                        <div class="strength-bar" id="bar3"></div>
                        <div class="strength-bar" id="bar4"></div>
                        <span class="strength-label" id="strengthLabel"></span>
                    </div>
                </div>

                <!-- Confirm -->
                <div class="form-group">
                    <label class="form-label">Confirm *</label>
                    <div class="input-wrap">
                        <span class="input-icon">✅</span>
                        <input class="form-input" type="password" name="confirm"
                               id="confirmInput" placeholder="Repeat password" required
                               oninput="checkMatch()">
                        <button type="button" class="pwd-toggle" onclick="togglePwd('confirmInput','t2')" id="t2">👁️</button>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-submit" id="submitBtn">
                Create Account →
            </button>
        </form>

        <p class="terms-note">By creating an account you agree to our <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>.</p>

        <div class="divider">already have an account?</div>
        <div class="login-link"><a href="login.php">Sign in to your account →</a></div>
    </div>
</div>

<script>
function togglePwd(inputId, btnId) {
    const input = document.getElementById(inputId);
    const btn   = document.getElementById(btnId);
    input.type  = input.type === 'password' ? 'text' : 'password';
    btn.textContent = input.type === 'password' ? '👁️' : '🙈';
}

function checkStrength(val) {
    const bars   = [document.getElementById('bar1'),document.getElementById('bar2'),document.getElementById('bar3'),document.getElementById('bar4')];
    const label  = document.getElementById('strengthLabel');
    let score = 0;
    if (val.length >= 6)  score++;
    if (val.length >= 10) score++;
    if (/[A-Z]/.test(val) && /[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    const colors  = ['#e84242','#FF9500','#3ecf8e','#22c55e'];
    const labels  = ['Weak','Fair','Good','Strong'];
    bars.forEach((b,i) => { b.style.background = i < score ? colors[score-1] : '#F0E4D4'; });
    label.textContent  = score > 0 ? labels[score-1] : '';
    label.style.color  = score > 0 ? colors[score-1] : 'var(--brown-3)';
}

function checkMatch() {
    const pwd     = document.getElementById('pwdInput').value;
    const confirm = document.getElementById('confirmInput');
    confirm.style.borderColor = confirm.value && confirm.value !== pwd ? '#e84242' : confirm.value ? '#3ecf8e' : '#F0E4D4';
}

document.getElementById('regForm').addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    btn.textContent = 'Creating account…';
    btn.style.opacity = '.8';
});
</script>
</body>
</html>