<?php
/**
 * utils/email.php
 * 
 * Reusable email sender using PHPMailer via Composer,
 * converted from the Node.js nodemailer utility (email.js).
 *
 * SETUP:
 *   1. Run: composer require phpmailer/phpmailer
 *   2. Fill in .env with EMAIL_USER, EMAIL_PASS, EMAIL_HOST, EMAIL_PORT
 *   3. Use a Gmail App Password (NOT your account password)
 *      → myaccount.google.com → Security → App Passwords
 */

require_once __DIR__ . '/../config/email.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$composerAutoload = __DIR__ . '/../../vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

/**
 * sendMail()
 * Sends an HTML email using PHPMailer + Gmail SMTP.
 *
 * @param string $toEmail     Recipient email address
 * @param string $toName      Recipient display name
 * @param string $subject     Email subject line
 * @param string $htmlBody    Full HTML email body
 * @return bool               True on success, false on failure
 */
function sendMail(string $toEmail, string $toName, string $subject, string $htmlBody): bool {
    if (empty($toEmail) || empty(EMAIL_USER) || empty(EMAIL_PASS)) {
        error_log('[Email] Skipped — missing recipient or SMTP credentials.');
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = EMAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = 'barangay853kahilomiii@gmail.com';
        $mail->Password   = 'cjxgvsqntabkcqzs';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int) EMAIL_PORT;

        // Sender & recipient
        $mail->setFrom('barangay853kahilomiii@gmail.com', 'Brgy. 853 Admin');
        $mail->addAddress($toEmail, $toName);

        // Content
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
 * Sends a styled status-update email for document requests.
 * Converted from the documentRequests-fixed.js PUT route email block.
 *
 * @param string $toEmail       Resident's email
 * @param string $toName        Resident's name
 * @param string $documentType  e.g. "Barangay Clearance"
 * @param string $referenceNo   e.g. "DOC-2026-K4RX92BT"
 * @param string $status        e.g. "Approved", "Rejected", "Released"
 * @return bool
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