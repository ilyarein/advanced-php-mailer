<?php

require_once __DIR__ . '/../vendor/autoload.php';

use AdvancedMailer\Mail;
use AdvancedMailer\LoggerInterface;
use AdvancedMailer\NullLogger;

// SMTP configuration
$config = [
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_username' => 'your-email@gmail.com',
    'smtp_password' => 'your-app-password',
    'smtp_encryption' => 'tls'
];

// Simple PSR-3 compatible file logger implementation
class FileLogger implements LoggerInterface {
    private string $logFile;

    public function __construct(string $logFile = 'mail.log') {
        $this->logFile = $logFile;
    }

    public function emergency(string $message, array $context = []): void {
        $this->log('EMERGENCY', $message, $context);
    }

    public function alert(string $message, array $context = []): void {
        $this->log('ALERT', $message, $context);
    }

    public function critical(string $message, array $context = []): void {
        $this->log('CRITICAL', $message, $context);
    }

    public function error(string $message, array $context = []): void {
        $this->log('ERROR', $message, $context);
    }

    public function warning(string $message, array $context = []): void {
        $this->log('WARNING', $message, $context);
    }

    public function notice(string $message, array $context = []): void {
        $this->log('NOTICE', $message, $context);
    }

    public function info(string $message, array $context = []): void {
        $this->log('INFO', $message, $context);
    }

    public function debug(string $message, array $context = []): void {
        $this->log('DEBUG', $message, $context);
    }

    public function log($level, string $message, array $context = []): void {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = empty($context) ? '' : ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        $logEntry = "[$timestamp] [$level] $message{$contextStr}\n";

        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

// Create logger
$logger = new FileLogger('mail_operations.log');

// Create Mail instance with PSR-3 compatible logger
$mail = new Mail($config);
$mail->setLogger($logger);

// Configure sender
$mail->setFrom('your-email@gmail.com', 'Your Name');

// Add recipients
$mail->addAddress('recipient1@example.com', 'Recipient 1');
$mail->addAddress('recipient2@example.com'); // No name

// Add CC/BCC
$mail->addCC('cc@example.com', 'CC');
$mail->addBCC('bcc@example.com'); // BCC

// Set Reply-To
$mail->setReplyTo('reply@example.com', 'Reply here');

// Set subject and content
$mail->setSubject('Test message from Advanced Mailer');
$mail->setHtmlBody(
    '<h1>Hello!</h1>' .
    '<p>This is the <strong>HTML</strong> version of the message.</p>' .
    '<p>Best regards,<br>Advanced Mailer</p>'
);
$mail->setAltBody('Hello! This is the plain-text version. Best regards, Advanced Mailer');

// Demonstrate PSR-3 log levels
echo "=== Demonstration of PSR-3 log levels ===\n";

$logger->emergency('System critical error', ['system' => 'mail', 'impact' => 'high']);
$logger->alert('Immediate intervention required', ['component' => 'smtp', 'issue' => 'connection']);
$logger->critical('Sending critical error', ['recipients' => 5, 'transport' => 'smtp']);
$logger->error('Email validation error', ['email' => 'invalid-email', 'validation' => 'failed']);
$logger->warning('Attachment limit exceeded', ['size' => '15MB', 'limit' => '10MB']);
$logger->notice('Using alternative transport', ['transport' => 'sendgrid']);
$logger->info('Sending mail started', ['recipients' => 1, 'subject' => 'Test']);
$logger->debug('Debug information', ['memory_usage' => memory_get_usage(), 'timestamp' => microtime(true)]);

echo "Logs written to: mail_operations.log\n\n";

// Send mail
try {
    if ($mail->send()) {
        echo "Mail sent successfully!\n";
        echo "Check the mail_operations.log file for detailed information\n";
    } else {
        echo "Mail sending error.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Detailed information in logs\n";
}
