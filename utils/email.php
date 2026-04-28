<?php
/**
 * utils/email.php
 */

require_once __DIR__ . '/../config/email.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$composerAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

/**
 * sendMail()
 */
function sendMail(string $toEmail, string $toName, string $subject, string $htmlBody): bool {
    if (empty($toEmail) || empty(EMAIL_USER) || empty(EMAIL_PASS)) {
        error_log('[Email] Skipped — missing recipient or SMTP credentials.');
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = EMAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = 'lasseterjohn75@gmail.com';
        $mail->Password   = 'qeml iapb ddwe qzeo';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int) EMAIL_PORT;

        $mail->setFrom('barangay853kahilomiii@gmail.com', 'Brgy. 853 Admin');
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '</p>'], "\n", $htmlBody));

        $mail->send();
        error_log("[Email] ✅ Sent to: $toEmail | Subject: $subject");
        return true;

    } catch (Exception $e) {
        error_log("[Email] ❌ Failed to send to $toEmail — " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * sendDocumentStatusEmail()
 */
function sendDocumentStatusEmail(
    string $toEmail,
    string $toName,
    string $documentType,
    string $referenceNo,
    string $status
): bool {
    $statusColors = [
        'Approved' => '#16a34a',
        'Released' => '#2563eb',
        'Rejected' => '#dc2626',
        'Review'   => '#d97706',
        'Pending'  => '#6b7280',
    ];
    $color = $statusColors[$status] ?? '#6b7280';

    $subject = "Update on your Barangay Document Request: {$status}";

    $html = "
    <div style='font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;
                border:1px solid #e5e7eb;border-radius:10px;'>
        <h2 style='color:#2563eb;'>Document Request Update</h2>
        <p>Hello <strong>" . htmlspecialchars($toName) . "</strong>,</p>
        <p>Your request for a <strong>" . htmlspecialchars($documentType) . "</strong>
           has been updated to:</p>
        <h3 style='background:#f3f4f6;padding:10px;text-align:center;
                   border-radius:5px;color:{$color};'>
            STATUS: " . htmlspecialchars(strtoupper($status)) . "
        </h3>
        <p>Tracking Reference Number: <strong>" . htmlspecialchars($referenceNo) . "</strong></p>
        <p>You can track your request anytime on the Barangay 853 Public Portal.</p>
        <br/>
        <p style='color:#6b7280;font-size:12px;'>
            Thank you,<br/>Barangay 853 Administration
        </p>
    </div>";

    return sendMail($toEmail, $toName, $subject, $html);
}

/**
 * sendTwoFactorEmail()
 */
function sendTwoFactorEmail(
    string $toEmail,
    string $toName,
    string $code,
    string $expiryLabel
): bool {
    $subject = 'Your Barangay 853 Verification Code';

    $digits = implode('', array_map(
        fn($d) => "<span style='display:inline-block;width:44px;height:52px;line-height:52px;
                         text-align:center;font-size:1.75rem;font-weight:800;color:#0b2250;
                         background:#f1f5f9;border:2px solid #cbd5e1;border-radius:8px;
                         margin:0 3px;letter-spacing:0;'>{$d}</span>",
        str_split($code)
    ));

    $html = "
    <div style='font-family:Inter,Arial,sans-serif;max-width:520px;margin:0 auto;
                background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;
                overflow:hidden;'>

      <!-- Header -->
      <div style='background:linear-gradient(135deg,#0b2250 0%,#1e3a8a 100%);
                  padding:32px 40px;text-align:center;'>
        <h1 style='color:#ffffff;font-size:1.3rem;font-weight:700;margin:0;'>
          🔐 Verification Code
        </h1>
        <p style='color:rgba(255,255,255,0.75);font-size:0.85rem;margin:6px 0 0;'>
          Barangay 853 — Two-Factor Authentication
        </p>
      </div>

      <!-- Body -->
      <div style='padding:36px 40px;'>
        <p style='color:#374151;font-size:0.95rem;margin:0 0 8px;'>
          Hello, <strong>" . htmlspecialchars($toName) . "</strong>
        </p>
        <p style='color:#6b7280;font-size:0.875rem;line-height:1.6;margin:0 0 28px;'>
          Use the code below to complete your sign-in.
          This code is valid for <strong>{$expiryLabel}</strong>
          and can only be used once.
        </p>

        <!-- Code display -->
        <div style='text-align:center;margin:0 0 28px;'>
          {$digits}
        </div>

        <!-- Warning box -->
        <div style='background:#fefce8;border:1px solid #fde68a;border-radius:10px;
                    padding:14px 18px;margin-bottom:24px;'>
          <p style='margin:0;font-size:0.82rem;color:#92400e;line-height:1.5;'>
            ⚠️ <strong>Never share this code</strong> with anyone — not even barangay staff.
            If you did not request this, please change your password immediately.
          </p>
        </div>

        <!-- Expiry note -->
        <p style='color:#94a3b8;font-size:0.78rem;text-align:center;margin:0;'>
          This code expires in {$expiryLabel}. Request a new one if it runs out.
        </p>
      </div>

      <!-- Footer -->
      <div style='background:#f8fafc;border-top:1px solid #e2e8f0;
                  padding:16px 40px;text-align:center;'>
        <p style='color:#94a3b8;font-size:0.75rem;margin:0;'>
          Barangay 853, Zone 93, Manila &nbsp;·&nbsp;
          This is an automated message, do not reply.
        </p>
      </div>
    </div>";

    return sendMail($toEmail, $toName, $subject, $html);
}