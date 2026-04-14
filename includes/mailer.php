<?php
/**
 * ClearPath Mailer
 * ─────────────────────────────────────────────────────────
 * Handles all outgoing emails for ClearPath BPC:
 *   1. Clearance Started    - when a new clearance is submitted
 *   2. Deadline Warning     - 3 days before a requirement is due
 *   3. Failed Compliance    - when deadline has passed without submission
 *   4. Clearance Complete   - when all offices approve (with PDF cert)
 *   5. Password Reset       - forgot password flow
 *
 * SETUP:
 *   a. Download PHPMailer: github.com/PHPMailer/PHPMailer (download zip)
 *   b. Extract and place the `src` folder at: includes/lib/PHPMailer/src/
 *   c. Update SMTP_USER and SMTP_PASS below with your Gmail + App Password
 *      (Google Account → Security → 2-Step Verification → App Passwords)
 * ─────────────────────────────────────────────────────────
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/lib/PHPMailer/src/Exception.php';
require_once __DIR__ . '/lib/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/lib/PHPMailer/src/SMTP.php';

// ── SMTP Configuration ─────────────────────────────────
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your_gmail@gmail.com');   // ← Change this
define('SMTP_PASS', 'your_app_password');       // ← Change this (Gmail App Password)
define('MAIL_FROM_NAME', 'ClearPath BPC');
// ───────────────────────────────────────────────────────

/**
 * Creates a pre-configured PHPMailer instance
 */
function _createMailer(): PHPMailer {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = SMTP_PORT;
    $mail->setFrom(SMTP_USER, MAIL_FROM_NAME);
    $mail->isHTML(true);
    $mail->CharSet = 'UTF-8';
    return $mail;
}

/**
 * Shared email wrapper with error handling
 * Returns true on success, error string on failure
 */
function _sendMail(string $toEmail, string $toName, string $subject, string $htmlBody, ?string $pdfPath = null) {
    try {
        $mail = _createMailer();
        $mail->addAddress($toEmail, $toName);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody); // Plain text fallback

        // Attach PDF if provided
        if ($pdfPath && file_exists($pdfPath)) {
            $mail->addAttachment($pdfPath, 'Clearance_Certificate.pdf');
        }

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('[ClearPath Mailer] ' . $e->getMessage());
        return 'Email error: ' . $e->getMessage();
    }
}

// ══════════════════════════════════════════════════════════
// EMAIL 1: Clearance Started
// Sent when a student submits a new clearance request
// ══════════════════════════════════════════════════════════
function sendClearanceStartEmail(
    string $email,
    string $fullName,
    string $studentId,
    string $schoolYear,
    string $semester,
    ?string $deadline = null
) {
    $deadlineNote = $deadline
        ? '<p style="color:#b45309;font-weight:600;">📅 Deadline: ' . date('F j, Y', strtotime($deadline)) . '</p>'
        : '';

    $html = _emailLayout("Clearance Process Started", "
        <p>Hi <strong>$fullName</strong>,</p>
        <p>Your clearance request for <strong>$schoolYear – $semester Semester</strong> has been successfully submitted and is now being processed.</p>
        <p><strong>Student ID:</strong> $studentId</p>
        $deadlineNote
        <p>You will need to submit the required documents to each office listed in your clearance portal.</p>
        " . _ctaButton('View My Clearance', APP_URL . '/student/clearance.php') . "
        <p style='margin-top:20px;color:#6b7280;font-size:13px;'>Log in regularly to check your clearance status and submit requirements before their deadlines.</p>
    ");

    return _sendMail($email, $fullName, "✅ Your Clearance Has Started — ClearPath BPC", $html);
}

// ══════════════════════════════════════════════════════════
// EMAIL 2: Deadline Warning
// Sent 3 days (or less) before a specific requirement's deadline
// ══════════════════════════════════════════════════════════
function sendDeadlineWarningEmail(
    string $email,
    string $fullName,
    string $officeName,
    string $deadline,
    int    $daysLeft
) {
    $urgency = $daysLeft <= 1 ? '🚨 URGENT — ' : '⚠️ ';
    $dayText  = $daysLeft === 1 ? 'tomorrow' : "in <strong>$daysLeft days</strong>";
    $color    = $daysLeft <= 1 ? '#dc2626' : '#d97706';

    $html = _emailLayout("Requirement Deadline Approaching", "
        <p>Hi <strong>$fullName</strong>,</p>
        <p>This is a reminder that your requirement for the <strong>$officeName</strong> is due <span style='color:$color;font-weight:700;'>$dayText</span> on <strong>" . date('F j, Y', strtotime($deadline)) . "</strong>.</p>
        <div style='background:#fef3c7;border:1px solid #fcd34d;border-radius:10px;padding:16px;margin:20px 0;'>
          <p style='margin:0;color:#92400e;font-weight:600;'>⏰ Action Required</p>
          <p style='margin:6px 0 0;color:#78350f;font-size:14px;'>Log in to ClearPath and submit your documents for <strong>$officeName</strong> immediately to avoid a failed compliance notice.</p>
        </div>
        " . _ctaButton('Submit Requirements Now', APP_URL . '/student/clearance.php') . "
    ");

    return _sendMail($email, $fullName, "{$urgency}Deadline Approaching: $officeName — ClearPath BPC", $html);
}

// ══════════════════════════════════════════════════════════
// EMAIL 3: Failed Compliance
// Sent when a deadline has passed and requirements not submitted
// ══════════════════════════════════════════════════════════
function sendFailedComplianceEmail(
    string $email,
    string $fullName,
    string $officeName,
    string $deadline
) {
    $html = _emailLayout("Failed to Comply — Action Required", "
        <p>Hi <strong>$fullName</strong>,</p>
        <p>Our records show that you <strong>failed to submit</strong> the required documents for the <strong>$officeName</strong> before the deadline of <strong>" . date('F j, Y', strtotime($deadline)) . "</strong>.</p>
        <div style='background:#fee2e2;border:1px solid #fca5a5;border-radius:10px;padding:16px;margin:20px 0;'>
          <p style='margin:0;color:#991b1b;font-weight:700;'>⛔ Clearance on Hold</p>
          <p style='margin:6px 0 0;color:#7f1d1d;font-size:14px;'>Your clearance for <strong>$officeName</strong> has been placed on hold. Please contact the office directly or log in to ClearPath to resolve this.</p>
        </div>
        " . _ctaButton('Go to My Clearance', APP_URL . '/student/clearance.php') . "
        <p style='margin-top:20px;font-size:13px;color:#6b7280;'>If you believe this is an error, please contact the Registrar's Office immediately.</p>
    ");

    return _sendMail($email, $fullName, "⛔ Failed Compliance: $officeName — ClearPath BPC", $html);
}

// ══════════════════════════════════════════════════════════
// EMAIL 4: Clearance Complete
// Sent when ALL offices have approved the student's clearance
// Attaches the PDF clearance certificate
// ══════════════════════════════════════════════════════════
function sendClearanceCompleteEmail(
    string  $email,
    string  $fullName,
    string  $studentId,
    string  $schoolYear,
    string  $semester,
    ?string $pdfPath = null
) {
    $attachNote = $pdfPath ? '<p style="color:#059669;">📎 Your clearance certificate is attached to this email.</p>' : '';

    $html = _emailLayout("🎉 Clearance Complete!", "
        <p>Hi <strong>$fullName</strong>,</p>
        <p>Congratulations! Your school clearance for <strong>$schoolYear – $semester Semester</strong> has been <span style='color:#059669;font-weight:700;'>fully approved</span> by all offices.</p>
        <div style='background:#d1fae5;border:1px solid #6ee7b7;border-radius:10px;padding:16px;margin:20px 0;text-align:center;'>
          <p style='margin:0;font-size:24px;'>✅</p>
          <p style='margin:8px 0 0;color:#065f46;font-weight:700;font-size:16px;'>CLEARED</p>
          <p style='margin:4px 0 0;color:#047857;font-size:13px;'>$fullName &nbsp;|&nbsp; $studentId</p>
        </div>
        $attachNote
        " . _ctaButton('Download Certificate', APP_URL . '/student/download.php') . "
        <p style='margin-top:20px;font-size:13px;color:#6b7280;'>You may also log in to ClearPath to download your clearance certificate anytime.</p>
    ");

    return _sendMail($email, $fullName, "🎉 Clearance Complete — ClearPath BPC", $html, $pdfPath);
}

// ══════════════════════════════════════════════════════════
// EMAIL 5: Password Reset
// ══════════════════════════════════════════════════════════
function sendPasswordResetEmail(string $email, string $fullName, string $resetLink) {
    $html = _emailLayout("Reset Your Password", "
        <p>Hi <strong>$fullName</strong>,</p>
        <p>You requested a password reset for your ClearPath BPC account.</p>
        <p>Click the button below to reset your password. This link expires in <strong>30 minutes</strong>.</p>
        " . _ctaButton('Reset My Password', $resetLink, '#dc2626') . "
        <p style='margin-top:20px;font-size:13px;color:#6b7280;'>If you did not request this, you can safely ignore this email. Your password will not change.</p>
    ");

    return _sendMail($email, $fullName, "🔑 Password Reset — ClearPath BPC", $html);
}

// ══════════════════════════════════════════════════════════
// HELPERS — Email layout templates
// ══════════════════════════════════════════════════════════

/** Wraps content in the standard branded email template */
function _emailLayout(string $title, string $content): string {
    $year = date('Y');
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#e8edf8;font-family:'Segoe UI',Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#e8edf8;padding:30px 16px;">
    <tr><td align="center">
      <table width="100%" style="max-width:580px;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(15,31,92,0.12);">
        <!-- Header -->
        <tr>
          <td style="background:linear-gradient(135deg,#0f1f5c,#1e3a9e);padding:28px 36px;">
            <table><tr>
              <td style="background:rgba(255,255,255,0.15);border-radius:10px;padding:8px 14px;border:1px solid rgba(255,255,255,0.2);">
                <span style="font-size:15px;font-weight:800;color:#fff;letter-spacing:-0.5px;">CP</span>
              </td>
              <td style="padding-left:12px;">
                <div style="color:#fff;font-weight:700;font-size:17px;">ClearPath</div>
                <div style="color:rgba(255,255,255,0.6);font-size:12px;">BPC Digital School Clearance</div>
              </td>
            </tr></table>
            <div style="color:#fff;font-size:20px;font-weight:700;margin-top:20px;letter-spacing:-0.5px;">$title</div>
          </td>
        </tr>
        <!-- Body -->
        <tr>
          <td style="padding:32px 36px;color:#374151;font-size:15px;line-height:1.7;">
            $content
          </td>
        </tr>
        <!-- Footer -->
        <tr>
          <td style="background:#f8f9fc;border-top:1px solid #e5e7eb;padding:20px 36px;text-align:center;">
            <p style="margin:0;font-size:12px;color:#9ca3af;">This email was sent by ClearPath BPC &copy; $year. Please do not reply to this email.</p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
}

/** Generates a branded call-to-action button */
function _ctaButton(string $label, string $url, string $color = '#1e3a9e'): string {
    return <<<HTML
<div style="text-align:center;margin:28px 0;">
  <a href="$url" style="background:$color;color:#fff;text-decoration:none;padding:14px 32px;border-radius:10px;font-weight:700;font-size:15px;display:inline-block;">
    $label
  </a>
</div>
HTML;
}
