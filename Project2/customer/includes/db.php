<?php
// ============================================================
//  DB — Minmi Restaurent Customer Website
//  Place in: customer/includes/db.php
//  Same database as admin dashboard
// ============================================================

// ── Use separate session from admin dashboard ──
if (session_status() === PHP_SESSION_NONE) {
    session_name('minmi_customer');
    session_start();
}

define('DB_HOST', 'localhost');
define('DB_NAME', 'minmi_restaurent');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    die('<div style="font-family:sans-serif;padding:40px;text-align:center;color:#e8622a">
        <h2>Database Connection Failed</h2>
        <p>' . htmlspecialchars($e->getMessage()) . '</p>
    </div>');
}

// ── Auto-create feedback table if not exists ──
$pdo->exec("CREATE TABLE IF NOT EXISTS feedback (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    name        VARCHAR(150) NOT NULL,
    rating      TINYINT NOT NULL DEFAULT 5,
    message     TEXT NOT NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// ── Auto-add password column to customers table if missing ──
try { $pdo->query("SELECT password FROM customers LIMIT 1"); }
catch (PDOException $e) {
    $pdo->exec("ALTER TABLE customers ADD COLUMN password VARCHAR(255) DEFAULT '' AFTER email");
}

// ── Auto-add status column to customers table if missing ──
try { $pdo->query("SELECT status FROM customers LIMIT 1"); }
catch (PDOException $e) {
    $pdo->exec("ALTER TABLE customers ADD COLUMN status VARCHAR(20) DEFAULT 'active' AFTER phone");
}
