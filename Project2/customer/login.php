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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;0,900;1,400&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --fire:#FF4500;--orange:#FF6B2B;--amber:#FF9500;--yellow:#FFD60A;
            --cream:#FFF8F0;--brown:#3D1F0A;--brown-2:#6B3A1F;--brown-3:#9E6B4A;
        }
        html { height:100%; }
        body {
            font-family:'Plus Jakarta Sans',sans-serif;
            min-height:100vh;
            display:grid;
            grid-template-columns:1fr 1fr;

            /* THIS LINE SWAPS PANELS */
            grid-template-areas: "right left";

            background:var(--brown);
            overflow:hidden;
        }
        /* LEFT */
        .left-panel {
        grid-area:left;   /* ADD THIS */

        position:relative;
        display:flex;
        flex-direction:column;
        justify-content:center;
        align-items:flex-start;
        padding:60px;
        overflow:hidden;
        background:linear-gradient(145deg,#1A0A02 0%,#2D1208 50%,#3D1F0A 100%);
        }

        .float-emoji {
            position:absolute;font-size:2.5rem;opacity:0.15;
            animation:floatUp 8s ease-in-out infinite;
            user-select:none;pointer-events:none;
        }
        .float-emoji:nth-child(1){left:8%;bottom:-60px;animation-delay:0s;animation-duration:9s}
        .float-emoji:nth-child(2){left:22%;bottom:-60px;animation-delay:1.5s;animation-duration:11s;font-size:2rem}
        .float-emoji:nth-child(3){left:40%;bottom:-60px;animation-delay:3s;animation-duration:8s;font-size:3rem}
        .float-emoji:nth-child(4){left:58%;bottom:-60px;animation-delay:4.5s;animation-duration:10s;font-size:1.8rem}
        .float-emoji:nth-child(5){left:75%;bottom:-60px;animation-delay:0.8s;animation-duration:12s}
        .float-emoji:nth-child(6){left:88%;bottom:-60px;animation-delay:2.2s;animation-duration:9.5s;font-size:2.2rem}
        @keyframes floatUp {
            0%{transform:translateY(0) rotate(0deg);opacity:0}
            10%{opacity:0.15}90%{opacity:0.1}
            100%{transform:translateY(-110vh) rotate(20deg);opacity:0}
        }
        .glow-orb{position:absolute;border-radius:50%;filter:blur(80px);pointer-events:none}
        .glow-orb-1{width:300px;height:300px;background:rgba(255,69,0,.25);top:-80px;left:-80px}
        .glow-orb-2{width:200px;height:200px;background:rgba(255,149,0,.2);bottom:60px;right:20px;animation:pulse 4s ease-in-out infinite}
        @keyframes pulse{0%,100%{transform:scale(1);opacity:.2}50%{transform:scale(1.3);opacity:.35}}
        .left-content{position:relative;z-index:2}
        .brand{display:flex;align-items:center;gap:14px;margin-bottom:48px}
        .brand-logo{
            width:52px;height:52px;
            background:linear-gradient(135deg,var(--fire),var(--amber));
            border-radius:14px;display:flex;align-items:center;justify-content:center;
            font-size:1.6rem;box-shadow:0 8px 24px rgba(255,69,0,.4);
        }
        .brand-text{font-family:'Playfair Display',serif;font-size:1.4rem;font-weight:700;color:#fff;line-height:1.1}
        .brand-text span{display:block;font-size:.72rem;font-family:'Plus Jakarta Sans',sans-serif;font-weight:500;color:rgba(255,255,255,.45);letter-spacing:.12em;text-transform:uppercase;margin-top:2px}
        .left-heading{font-family:'Playfair Display',serif;font-size:3.5rem;font-weight:900;line-height:1.25;color:#fff;margin-bottom:20px}
        .left-heading em{font-style:italic;background:linear-gradient(135deg,var(--fire),var(--yellow));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
        .left-sub{font-size:.95rem;color:rgba(255,255,255,.5);line-height:1.7;max-width:340px;margin-bottom:48px}
        .feature-pills{display:flex;flex-direction:column;gap:12px}
        .pill{display:flex;align-items:center;gap:12px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:50px;padding:10px 18px;width:fit-content;font-size:.82rem;color:rgba(255,255,255,.7);backdrop-filter:blur(10px);transition:all .3s}
        .pill:hover{background:rgba(255,255,255,.1);color:#fff;transform:translateX(4px)}
        /* RIGHT */
        .right-panel{
        grid-area:right;   /* ADD THIS */

        background:var(--cream);
        display:flex;
        flex-direction:column;
        justify-content:center;
        align-items:center;
        padding:60px 48px;
        position:relative;
        overflow:hidden;
        }
        
        .right-panel::before{content:'';position:absolute;top:-100px;right:-100px;width:300px;height:300px;background:radial-gradient(circle,rgba(255,107,43,.12) 0%,transparent 70%);pointer-events:none}
        .right-panel::after{content:'';position:absolute;bottom:-80px;left:-80px;width:250px;height:250px;background:radial-gradient(circle,rgba(255,149,0,.1) 0%,transparent 70%);pointer-events:none}
        .form-wrap{width:100%;max-width:400px;position:relative;z-index:1}
        .form-header{margin-bottom:36px}
        .form-eyebrow{font-size:.72rem;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:var(--orange);margin-bottom:10px}
        .form-title{font-family:'Playfair Display',serif;font-size:2.2rem;font-weight:900;color:var(--brown);line-height:1.15;margin-bottom:8px}
        .form-subtitle{font-size:.88rem;color:var(--brown-3);line-height:1.6}
        .flash{display:flex;align-items:center;gap:10px;padding:14px 16px;border-radius:12px;font-size:.85rem;font-weight:600;margin-bottom:24px;background:rgba(220,38,38,.08);color:#991B1B;border:1.5px solid rgba(220,38,38,.2);animation:slideIn .3s ease}
        @keyframes slideIn{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}
        .form-group{margin-bottom:20px;position:relative}
        .form-label{display:block;font-size:.72rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--brown-3);margin-bottom:8px;transition:color .2s}
        .form-group:focus-within .form-label{color:var(--orange)}
        .input-wrap{position:relative}
        .input-icon{position:absolute;left:16px;top:50%;transform:translateY(-50%);font-size:1rem;pointer-events:none}
        .form-input{
            width:100%;background:#fff;border:2px solid #F0E4D4;color:var(--brown);
            border-radius:14px;padding:14px 16px 14px 46px;
            font-family:'Plus Jakarta Sans',sans-serif;font-size:.92rem;outline:none;
            transition:all .25s;box-shadow:0 2px 8px rgba(61,31,10,.04);
        }
        .form-input::placeholder{color:#C4A882}
        .form-input:focus{border-color:var(--orange);background:#fff;box-shadow:0 0 0 4px rgba(255,107,43,.1),0 2px 8px rgba(61,31,10,.06);transform:translateY(-1px)}
        .pwd-toggle{position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:1rem;color:var(--brown-3);padding:4px;transition:color .2s}
        .pwd-toggle:hover{color:var(--orange)}
        .btn-submit{
            width:100%;padding:15px;border-radius:14px;
            font-family:'Plus Jakarta Sans',sans-serif;font-size:.95rem;font-weight:800;
            cursor:pointer;border:none;
            background:linear-gradient(135deg,var(--fire) 0%,var(--amber) 100%);
            color:#fff;transition:all .3s;position:relative;overflow:hidden;
            box-shadow:0 6px 20px rgba(255,69,0,.35);margin-top:8px;letter-spacing:.02em;
        }
        .btn-submit::before{content:'';position:absolute;top:0;left:-100%;width:100%;height:100%;background:linear-gradient(90deg,transparent,rgba(255,255,255,.15),transparent);transition:left .5s}
        .btn-submit:hover{transform:translateY(-2px);box-shadow:0 10px 32px rgba(255,69,0,.45)}
        .btn-submit:hover::before{left:100%}
        .btn-submit:active{transform:translateY(0)}
        .divider{display:flex;align-items:center;gap:12px;margin:24px 0;color:var(--brown-3);font-size:.78rem}
        .divider::before,.divider::after{content:'';flex:1;height:1px;background:#F0E4D4}
        .register-link{text-align:center;font-size:.86rem;color:var(--brown-3);margin-top:8px}
        .register-link a{color:var(--orange);font-weight:800;text-decoration:none;position:relative}
        .register-link a::after{content:'';position:absolute;bottom:-2px;left:0;right:0;height:2px;background:linear-gradient(90deg,var(--fire),var(--amber));border-radius:2px;transform:scaleX(0);transition:transform .3s}
        .register-link a:hover::after{transform:scaleX(1)}
        .deco-text{position:absolute;font-family:'Playfair Display',serif;font-size:9rem;font-weight:900;color:rgba(61,31,10,.04);pointer-events:none;white-space:nowrap;bottom:-20px;right:-20px;line-height:1;user-select:none}
        @media(max-width:768px){body{grid-template-columns:1fr;overflow:auto}.left-panel{display:none}.right-panel{padding:40px 24px;min-height:100vh}} 

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

<div class="left-panel">
    <div class="float-emoji">🍛</div>
    <div class="float-emoji">🍽️</div>
    <div class="float-emoji">🍗</div>
    <div class="float-emoji">🥘</div>
    <div class="float-emoji">🍜</div>
    <div class="float-emoji">🌶️</div>
    <div class="glow-orb glow-orb-1"></div>
    <div class="glow-orb glow-orb-2"></div>
    <div class="left-content">
        <div class="brand">
            <div class="brand-logo">
                <img src="assets/logo.png" alt="Minmi Logo">
            </div>
            <div class="brand-text">Minmi<span>Restaurant</span></div>
        </div>
        <h1 class="left-heading">Fire-crafted<br><em>flavours,</em><br>every night.</h1>
        <p class="left-sub">Order your favourite dishes, book a table, and track your meals — all in one place.</p>
        <div class="feature-pills">
            <div class="pill"><span>🍽️</span> Browse our full menu</div>
            <div class="pill"><span>📦</span> Real-time order tracking</div>
            <div class="pill"><span>📅</span> Easy table reservations</div>
            <div class="pill"><span>⭐</span> Share your experience</div>
        </div>
    </div>
</div>

<div class="right-panel">
    <div class="deco-text">Minmi</div>
    <div class="form-wrap">
        <div class="form-header">
            <div class="form-eyebrow">Welcome Back</div>
            <h2 class="form-title">Sign in to<br>your account</h2>
            <p class="form-subtitle">Enter your details to continue your culinary journey.</p>
        </div>

        <?php if ($msg): ?>
        <div class="flash"><?= $msg ?></div>
        <?php endif; ?>

        <form method="POST" id="loginForm">
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <div class="input-wrap">
                    <span class="input-icon">📧</span>
                    <input class="form-input" type="email" name="email"
                           placeholder="you@email.com"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           required autofocus>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Password</label>
                <div class="input-wrap">
                    <span class="input-icon">🔒</span>
                    <input class="form-input" type="password" name="password"
                           id="passwordInput" placeholder="Your password" required>
                    <button type="button" class="pwd-toggle" onclick="togglePwd()" id="pwdToggle">👁️</button>
                </div>
            </div>
            <button type="submit" class="btn-submit">Sign In →</button>
        </form>

        <div class="divider">or</div>
        <div class="register-link">
            Don't have an account? <a href="register.php">Create one free →</a>
        </div>
    </div>
</div>

<script>
function togglePwd() {
    const input = document.getElementById('passwordInput');
    const btn   = document.getElementById('pwdToggle');
    input.type  = input.type === 'password' ? 'text' : 'password';
    btn.textContent = input.type === 'password' ? '👁️' : '🙈';
}
document.getElementById('loginForm').addEventListener('submit', function() {
    const btn = this.querySelector('.btn-submit');
    btn.textContent = 'Signing in…';
    btn.style.opacity = '.8';
});
</script>
</body>
</html>