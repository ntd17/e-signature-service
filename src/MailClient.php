<?php
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Send an email using SMTP credentials defined in environment variables.
 * Falls back to logging the message if sending fails.
 */
function sendEmail(string $to, string $subject, string $body): bool
{
    $mailer = new PHPMailer(true);

    $smtpHost = getenv('SMTP_HOST');
    $smtpPort = getenv('SMTP_PORT') ?: 587;
    $smtpUser = getenv('SMTP_USER');
    $smtpPass = getenv('SMTP_PASS');

    try {
        $mailer->isSMTP();
        $mailer->Host       = $smtpHost;
        $mailer->Port       = $smtpPort;
        if ($smtpUser || $smtpPass) {
            $mailer->SMTPAuth = true;
            $mailer->Username = $smtpUser;
            $mailer->Password = $smtpPass;
        }
        $mailer->SMTPSecure = $smtpPort == 465 ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;

        $from = $smtpUser ?: 'no-reply@localhost';
        $mailer->setFrom($from, 'E-Signature Service');
        $mailer->addAddress($to);

        $mailer->Subject = $subject;
        $isHtml = $body !== strip_tags($body);
        $mailer->isHTML($isHtml);
        $mailer->Body    = $body;
        if ($isHtml) {
            $mailer->AltBody = strip_tags($body);
        }

        $mailer->send();
        return true;
    } catch (Exception $e) {
        error_log('Email sending failed: ' . $e->getMessage());
        error_log("[Email Fallback] To: $to Subject: $subject Body: $body");
        return false;
    }
}
