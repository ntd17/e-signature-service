<?php
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService {
    private $mailer;
    private $appUrl;

    public function __construct() {
        $this->mailer = new PHPMailer(true);
        $this->appUrl = getenv('APP_URL') ?: 'http://localhost:8000';
        
        // Configure SMTP
        $this->mailer->isSMTP();
        $this->mailer->Host = getenv('SMTP_HOST');
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = getenv('SMTP_USER');
        $this->mailer->Password = getenv('SMTP_PASS');
        $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mailer->Port = getenv('SMTP_PORT');
        
        // Set default sender
        $this->mailer->setFrom('no-reply@esignature-service.com', 'E-Signature Service');
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

            // Create signing link using APP_URL from environment
            $signingLink = "{$this->appUrl}/public/sign.html?" . http_build_query([
                'token' => $token,
                'contract_id' => $contractId,
                'email' => $email
            ]);

            // Set email content
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

    /**
     * Generates HTML email body
     */
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

    /**
     * Generates plain text email body
     */
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
