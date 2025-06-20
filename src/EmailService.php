<?php
namespace App\Services;

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService {
    private $mailer;
    private $appUrl;

    public function __construct() {
        $this->mailer = new PHPMailer(true);
        $this->appUrl = getenv('APP_URL') ?: 'http://localhost:8000';

        $smtpHost = getenv('SMTP_HOST');
        $smtpUser = getenv('SMTP_USER');
        $smtpPass = getenv('SMTP_PASS');
        $smtpPort = getenv('SMTP_PORT') ?: 587;

        if ($smtpHost && $smtpUser && $smtpPass) {
            $this->mailer->isSMTP();
            // Enable debugging in development environment only
            if (getenv('APP_ENV') === 'development') {
                $this->mailer->SMTPDebug = 2; // Debug level: 2 for detailed output
            }
            $this->mailer->Host       = $smtpHost;
            $this->mailer->SMTPAuth   = true;
            $this->mailer->Username   = $smtpUser;
            $this->mailer->Password   = $smtpPass;
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mailer->Port       = $smtpPort;
        } else {
            error_log("SMTP credentials missing: falling back to mail()");
            $this->mailer->isMail();
        }

        // Use SMTP user as sender if available, else fallback
        $fromEmail = $smtpUser ?: 'no-reply@esignature-service.com';
        $this->mailer->setFrom($fromEmail, 'E-Signature Service');
    }

    /**
     * Sends a signing invitation email
     *
     * @param string $email Recipient email address
     * @param string $token Unique signature token
     * @param string $contractId Contract ID
     * @return bool True if email was sent successfully
     * @throws Exception if email sending fails
     */
    public function sendSigningInvitation($email, $token, $contractId) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($email);

            $signingLink = "{$this->appUrl}/public/sign.html?" . http_build_query([
                    'token' => $token,
                    'contract_id' => $contractId,
                    'email' => $email
                ]);

            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Document Signing Request';
            $this->mailer->Body = $this->getHtmlBody($signingLink);
            $this->mailer->AltBody = $this->getPlainTextBody($signingLink);

            return $this->mailer->send();
        } catch (Exception $e) {
            error_log("Email sending failed: {$this->mailer->ErrorInfo}");
            throw new Exception("Failed to send email notification: " . $e->getMessage());
        }
    }

    private function getHtmlBody($signingLink) {
        return "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <div style='background-color: #f8f9fa; padding: 20px; text-align: center;'>
                    <h2 style='color: #1a56db; margin-bottom: 10px;'>Document Signing Request</h2>
                </div>
                <div style='padding: 20px; background-color: white; border-radius: 8px; margin-top: 20px;'>
                    <p style='color: #374151; font-size: 16px;'>You have been requested to sign a document.</p>
                    <p style='color: #374151; font-size: 16px;'>Please click the button below to review and sign the document:</p>
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='{$signingLink}'
                           style='background-color: #1a56db; color: white; padding: 12px 24px;
                                  text-decoration: none; border-radius: 6px; font-weight: 600;'>
                            Sign Document
                        </a>
                    </div>
                    <p style='color: #6b7280; font-size: 14px;'>If you did not expect this request, please ignore this email.</p>
                    <p style='color: #6b7280; font-size: 14px; margin-top: 20px;'>
                        For security reasons, this link will expire after signing.
                    </p>
                </div>
                <div style='text-align: center; margin-top: 20px; color: #6b7280; font-size: 12px;'>
                    This is an automated message, please do not reply.
                </div>
            </div>
        ";
    }

    private function getPlainTextBody($signingLink) {
        return "Document Signing Request\n\n" .
            "You have been requested to sign a document.\n" .
            "Please visit the following link to review and sign the document:\n\n" .
            $signingLink . "\n\n" .
            "If you did not expect this request, please ignore this email.\n" .
            "For security reasons, this link will expire after signing.\n\n" .
            "This is an automated message, please do not reply.";
    }
}