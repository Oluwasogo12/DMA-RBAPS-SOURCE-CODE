<?php
/**
 * mailer.php — simple mail helper for RBAPS
 *
 * Uses PHP's built-in mail() by default.
 * For production, swap the body of sendMail() to use PHPMailer/SMTP.
 * See the commented block below for a PHPMailer example.
 */

// ── Configuration ──────────────────────────────────────────────────────────
// Change these to match your server / hosting panel settings.
define('MAIL_FROM',      'no-reply@yourdomain.com');   // sender address
define('MAIL_FROM_NAME', 'RBAPS');                     // sender name
define('APP_NAME',       'RBAPS');

// ── sendMail() ─────────────────────────────────────────────────────────────
/**
 * Send an HTML email.
 *
 * @param  string $to      Recipient address
 * @param  string $subject Subject line
 * @param  string $body    HTML body (plain-text fallback generated automatically)
 * @return bool
 */
function sendMail(string $to, string $subject, string $body): bool {
    $from     = MAIL_FROM;
    $fromName = MAIL_FROM_NAME;
    $appName  = APP_NAME;

    // Wrap in a styled email shell
    $html = <<<HTML
    <!DOCTYPE html>
    <html>
    <head><meta charset="UTF-8"></head>
    <body style="margin:0;padding:0;background:#f0ede8;font-family:sans-serif;">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr><td align="center" style="padding:40px 20px;">
          <table width="520" cellpadding="0" cellspacing="0"
                 style="background:#0d0f14;border-radius:16px;overflow:hidden;border:1px solid rgba(201,168,76,0.2)">
            <tr>
              <td style="background:linear-gradient(135deg,#1a1c22,#0d0f14);padding:30px 40px;text-align:center;border-bottom:1px solid rgba(201,168,76,0.15)">
                <span style="font-size:28px;font-weight:700;color:#c9a84c;letter-spacing:-0.02em;">{$appName}</span>
              </td>
            </tr>
            <tr>
              <td style="padding:36px 40px;color:#c8c2b8;font-size:15px;line-height:1.7;">
                {$body}
              </td>
            </tr>
            <tr>
              <td style="padding:20px 40px 30px;text-align:center;font-size:12px;color:#6b6560;border-top:1px solid rgba(255,255,255,0.06)">
                &copy; <?= date('Y') ?> {$appName}. This is an automated message — please do not reply.
              </td>
            </tr>
          </table>
        </td></tr>
      </table>
    </body>
    </html>
    HTML;

    // Plain-text fallback (strip tags)
    $plain = wordwrap(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</li>'], "\n", $body)), 75, "\n");

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$fromName} <{$from}>\r\n";
    $headers .= "Reply-To: {$from}\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    return mail($to, $subject, $html, $headers);

    /* ── PHPMailer (SMTP) alternative ────────────────────────────────────────
     * Install via: composer require phpmailer/phpmailer
     * Then uncomment and fill in your SMTP credentials:
     *
     * use PHPMailer\PHPMailer\PHPMailer;
     * use PHPMailer\PHPMailer\SMTP;
     * require __DIR__ . '/../vendor/autoload.php';
     *
     * $mail = new PHPMailer(true);
     * $mail->isSMTP();
     * $mail->Host       = 'smtp.gmail.com';     // or your SMTP host
     * $mail->SMTPAuth   = true;
     * $mail->Username   = 'you@gmail.com';
     * $mail->Password   = 'your_app_password';
     * $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
     * $mail->Port       = 587;
     * $mail->setFrom($from, $fromName);
     * $mail->addAddress($to);
     * $mail->isHTML(true);
     * $mail->Subject = $subject;
     * $mail->Body    = $html;
     * $mail->AltBody = $plain;
     * return $mail->send();
     * ─────────────────────────────────────────────────────────────────────── */
}
