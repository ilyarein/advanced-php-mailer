<?php

require_once __DIR__ . '/../vendor/autoload.php';

use AdvancedMailer\Mail;
use AdvancedMailer\Transport\SendGridTransport;

// SendGrid configuration
$apiKey = 'your-sendgrid-api-key-here';

// Create SendGrid transport
$transport = new SendGridTransport($apiKey);

// Create Mail instance with SendGrid transport
$mail = new Mail();
$mail->setTransport($transport);

// Configure sender (must be verified in SendGrid)
$mail->setFrom('verified-sender@example.com', 'Your Service');

// Add recipients
$mail->addAddress('recipient1@example.com', 'Recipient 1');
$mail->addAddress('recipient2@example.com', 'Recipient 2');

// Add CC/BCC
$mail->addCC('cc@example.com', 'Copy');
$mail->addBCC('bcc@example.com', 'BCC');

// Set Reply-To
$mail->setReplyTo('support@example.com', 'Support');

// Set subject and content
$mail->setSubject('Test email via SendGrid');
$mail->setHtmlBody('
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
        <h1 style="color: #333;">Hello from SendGrid!</h1>
        <p>This email was sent using <strong>SendGrid API</strong> via Advanced Mailer.</p>

        <div style="background-color: #f0f0f0; padding: 20px; border-radius: 5px; margin: 20px 0;">
            <h3>SendGrid advantages:</h3>
            <ul>
                <li>High deliverability</li>
                <li>Detailed analytics</li>
                <li>Scalability</li>
                <li>Reliable infrastructure</li>
            </ul>
        </div>

        <p style="color: #666; font-size: 14px;">
            This email was sent using Advanced Mailer via SendGrid API.
        </p>
    </div>
');
$mail->setAltBody('Hello! This email was sent using Advanced Mailer via SendGrid API. This is a text version for clients that do not support HTML.');

// Add custom headers
$mail->addHeader('X-Mailer', 'Advanced Mailer with SendGrid');
$mail->addHeader('X-Priority', '1');

// Test connection before sending
if ($transport->testConnection()) {
    echo "SendGrid connection OK.\n";

    // Send mail
    try {
        if ($mail->send()) {
            echo "Mail sent via SendGrid!\n";
        } else {
            echo "Send failed.\n";
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "Failed to connect to SendGrid. Check API key.\n";
}

// Bulk mailing example
echo "\n=== Bulk mailing example ===\n";

// Create template for bulk mailing
$newsletterTemplate = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Weekly Newsletter</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h1 style="color: #2c3e50;">Hello, {{name}}!</h1>

    <p>Here\'s our weekly newsletter with interesting news:</p>

    <div style="background-color: #e8f4f8; padding: 15px; border-radius: 5px; margin: 20px 0;">
        <h3>🆕 New features</h3>
        <ul>
            <li>Added dark theme support</li>
            <li>Performance improved by 30%</li>
            <li>New integrations with popular services</li>
        </ul>
    </div>

    <div style="background-color: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0;">
        <h3>📅 Upcoming events</h3>
        <p><strong>Webinar:</strong> "How to improve email deliverability" - {{date}}</p>
        <p><a href="{{webinar_link}}" style="color: #007bff;">Registration</a></p>
    </div>

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{unsubscribe_link}}" style="color: #6c757d; font-size: 12px;">Unsubscribe from newsletter</a>
    </div>

    <hr style="border: none; border-top: 1px solid #eee; margin: 30px 0;">

    <p style="color: #666; font-size: 14px; text-align: center;">
        You received this email because you subscribed to our newsletter.<br>
        {{company_name}} | {{current_year}}
    </p>
</body>
</html>
';

// Subscribers list for bulk mailing
$subscribers = [
    ['email' => 'user1@example.com', 'name' => 'John'],
    ['email' => 'user2@example.com', 'name' => 'Maria'],
    ['email' => 'user3@example.com', 'name' => 'Alex'],
];

$successful = 0;
$failed = 0;

foreach ($subscribers as $subscriber) {
    $newsletterMail = new Mail();
    $newsletterMail->setTransport($transport);

    $newsletterMail->setFrom('newsletter@example.com', 'Weekly Newsletter')
                   ->addAddress($subscriber['email'], $subscriber['name'])
                   ->setSubject('Your weekly newsletter')
                   ->setHtmlBody($newsletterTemplate);

    // Replace template variables
    $body = $newsletterMail->getHtmlBody();
    $body = str_replace('{{name}}', $subscriber['name'], $body);
    $body = str_replace('{{date}}', '15 сентября 2024', $body);
    $body = str_replace('{{webinar_link}}', 'https://example.com/webinar', $body);
    $body = str_replace('{{unsubscribe_link}}', 'https://example.com/unsubscribe', $body);
    $body = str_replace('{{company_name}}', 'Test company', $body);
    $body = str_replace('{{current_year}}', date('Y'), $body);

    $newsletterMail->setHtmlBody($body);

    try {
        if ($newsletterMail->send()) {
            echo "✓ Mail sent: {$subscriber['email']}\n";
            $successful++;
        } else {
            echo "✗ Error sending: {$subscriber['email']}\n";
            $failed++;
        }
    } catch (Exception $e) {
        echo "✗ Error: {$subscriber['email']} - " . $e->getMessage() . "\n";
        $failed++;
    }

    // Short delay between sends
    sleep(1);
}

echo "\n=== Bulk mailing results ===\n";
echo "Successfully sent: $successful\n";
echo "Errors: $failed\n";
echo "Total: " . count($subscribers) . "\n";
