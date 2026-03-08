<?php
// ============================================================
//  MAILER.PHP — Minmi Restaurent
//  Place in: dashboard/includes/mailer.php
// ============================================================

define('MAIL_USERNAME', 'minmirestaurant@gmail.com');
define('MAIL_PASSWORD', 'qols fmeg svha rmbf');
define('MAIL_FROM_NAME', 'Minmi Restaurent');

// ── Find vendor/autoload.php — try multiple possible locations ──
// Because vendor/ could be inside dashboard/ OR next to dashboard/
$_possible_autoloads = [
    __DIR__ . '/../vendor/autoload.php',       // dashboard/vendor/   (vendor inside dashboard)
    __DIR__ . '/../../vendor/autoload.php',    // Project2/vendor/    (vendor next to dashboard)
    __DIR__ . '/../../../vendor/autoload.php', // one more level up
];

$_autoload_found = false;
foreach ($_possible_autoloads as $_path) {
    if (file_exists($_path)) {
        require_once $_path;
        $_autoload_found = true;
        break;
    }
}

// ════════════════════════════════════════
//  sendMail()
// ════════════════════════════════════════
function sendMail(string $to_email, string $to_name, string $subject, string $body): array {

    global $_autoload_found;

    if (!$_autoload_found) {
        return [
            'success' => false,
            'error'   => 'vendor/autoload.php not found. Run: composer require phpmailer/phpmailer inside your project root.'
        ];
    }

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom(MAIL_USERNAME, MAIL_FROM_NAME);
        $mail->addAddress($to_email, $to_name);
        $mail->addReplyTo(MAIL_USERNAME, MAIL_FROM_NAME);

        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;
        $mail->Body    = buildEmailTemplate($to_name, $body);
        $mail->AltBody = strip_tags($body);

        $mail->send();
        return ['success' => true];

    } catch (\PHPMailer\PHPMailer\Exception $e) {
        return ['success' => false, 'error' => $mail->ErrorInfo];
    }
}

// ════════════════════════════════════════
//  buildEmailTemplate()
// ════════════════════════════════════════
function buildEmailTemplate(string $name, string $body): string {
    $safe_name = htmlspecialchars($name);
    return <<<HTML
    <!DOCTYPE html>
    <html>
    <head><meta charset="UTF-8"></head>
    <body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,sans-serif">
        <table width="100%" cellpadding="0" cellspacing="0" style="padding:30px 0">
            <tr><td align="center">
                <table width="600" cellpadding="0" cellspacing="0"
                       style="background:#fff;border-radius:12px;overflow:hidden;
                              box-shadow:0 2px 16px rgba(0,0,0,.08);max-width:600px;width:100%">
                    <tr>
                        <td style="background:#1a1512;padding:28px 36px;text-align:center">
                            <div style="font-size:1.5rem;font-weight:700;color:#e8622a;letter-spacing:-0.02em">
                                🔥 Minmi Restaurent
                            </div>
                            <div style="color:#a8998a;font-size:.8rem;margin-top:4px">
                                Fire-crafted flavours, every night
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:36px">
                            <p style="color:#333;font-size:1rem;margin:0 0 16px">
                                Dear <strong>{$safe_name}</strong>,
                            </p>
                            <div style="color:#444;font-size:.94rem;line-height:1.75">
                                {$body}
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 36px">
                            <hr style="border:none;border-top:1px solid #eee">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px 36px;text-align:center">
                            <p style="color:#aaa;font-size:.75rem;margin:0;line-height:1.6">
                                <strong style="color:#888">Minmi Restaurent</strong><br>
                                minmirestaurant@gmail.com<br><br>
                                To unsubscribe, reply with "unsubscribe" in the subject.
                            </p>
                        </td>
                    </tr>
                </table>
            </td></tr>
        </table>
    </body>
    </html>
HTML;
}