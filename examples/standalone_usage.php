<?php

/**
 * Example of using Advanced Mailer WITHOUT Composer
 *
 * This file demonstrates that Advanced Mailer works fully
 * without installing external dependencies through Composer.
 */

// Include core classes
require_once __DIR__ . '/../src/Mail.php';
require_once __DIR__ . '/../src/Transport/SmtpTransport.php';
require_once __DIR__ . '/../src/Validation/EmailValidator.php';
require_once __DIR__ . '/../src/Exception/MailException.php';
require_once __DIR__ . '/../src/Template/TemplateEngine.php';

echo "=== Advanced Mailer - Standalone Usage ===\n\n";

// SMTP configuration
$config = [
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_username' => 'your-email@gmail.com',
    'smtp_password' => 'your-app-password',
    'smtp_encryption' => 'tls'
];

try {
    // Create Mail instance
    $mail = new AdvancedMailer\Mail($config);

    // Configure message
    $mail->setFrom('sender@example.com', 'Test sender');
    $mail->addAddress('recipient@example.com', 'Test recipient');
    $mail->setSubject('Test message - Standalone Mode');
    $mail->setHtmlBody('
        <div style="font-family: Arial, sans-serif; padding: 20px;">
            <h1 style="color: #333;">🎉 Advanced Mailer</h1>
            <p>This message was sent <strong>WITHOUT</strong> using Composer!</p>
            <p>All works on built-in components:</p>
            <ul>
                <li>✅ Built-in PSR-3 logger</li>
                <li>✅ Own email validation</li>
                <li>✅ SMTP transport</li>
                <li>✅ Attachment handling</li>
                <li>✅ Template engine</li>
            </ul>
            <p style="color: #666; font-size: 12px;">
                Sent: ' . date('Y-m-d H:i:s') . '
            </p>
        </div>
    ');

    // Add alternative text
    $mail->setAltBody('Advanced Mailer works without Composer! Sent: ' . date('Y-m-d H:i:s'));

    // Add attachment (if file exists)
    $attachmentPath = __DIR__ . '/sample.txt';
    if (file_exists($attachmentPath)) {
        $mail->addAttachment($attachmentPath, 'sample.txt');
    }

    // Send message
    if ($mail->send()) {
        echo "✅ Message sent successfully!\n";
        echo "📧 Check the recipient's email\n";
    } else {
        echo "❌ Error sending message\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "💡 Make sure SMTP settings are correct\n";
}

// Demonstration of email validation
echo "\n=== Demonstration of email validation ===\n";

$validator = new AdvancedMailer\Validation\EmailValidator();

$testEmails = [
    'valid@example.com',
    'user.name@domain.co.uk',
    'test+tag@gmail.com',
    'invalid-email',
    '',
    'test@',
    '@domain.com'
];

foreach ($testEmails as $email) {
    $isValid = $validator->isValidQuick($email);
    $status = $isValid ? '✅' : '❌';
    echo "$status $email\n";
}

// Demonstration of sanitization
echo "\n=== Demonstration of sanitization ===\n";
$dirtyEmails = [
    '  USER@EXAMPLE.COM  ',
    'Test@Domain.Org',
    '  invalid email  '
];

foreach ($dirtyEmails as $email) {
    $clean = $validator->sanitize($email);
    echo "'$email' -> '$clean'\n";
}

echo "\n🎉 Advanced Mailer is fully independent of external dependencies!\n";
echo "📚 Core features work out-of-the-box\n";
