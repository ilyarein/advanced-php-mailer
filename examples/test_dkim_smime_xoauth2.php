<?php

require_once __DIR__ . '/../src/Mail.php';
require_once __DIR__ . '/../src/Transport/SmtpTransport.php';

use AdvancedMailer\Mail;
use AdvancedMailer\Transport\SmtpTransport;

// Example configuration: replace with real paths/values
$config = [
    'smtp_host' => 'smtp.example.com',
    'smtp_port' => 587,
    'smtp_username' => 'user@example.com',
    'smtp_password' => 'password',
    'smtp_encryption' => 'tls',
    'dkim_private_key' => file_exists(__DIR__ . '/dkim.private.pem') ? file_get_contents(__DIR__ . '/dkim.private.pem') : '',
    'dkim_selector' => 'default',
    'dkim_domain' => 'example.com',
    'smime_cert' => file_exists(__DIR__ . '/smime.crt') ? __DIR__ . '/smime.crt' : '',
    'smime_key' => file_exists(__DIR__ . '/smime.key') ? __DIR__ . '/smime.key' : '',
    'smtp_auth_method' => 'xoauth2',
    'smtp_oauth_token' => ''
];

$transport = new SmtpTransport($config);

$mail = new Mail($config);
$mail->setTransport($transport);
$mail->setFrom('sender@example.com', 'Sender');
$mail->addAddress('recipient@example.com', 'Recipient');
$mail->setSubject('Test DKIM/S/MIME/XOAUTH2');
$mail->setHtmlBody('<p>Test message</p>');
$mail->setAltBody('Test message');

// Build message array without sending using reflection (private methods)
$refMail = new \ReflectionClass($mail);
$buildMsgMethod = $refMail->getMethod('buildMessage');
$buildMsgMethod->setAccessible(true);
$message = $buildMsgMethod->invoke($mail);

// Use reflection to call transport's buildEmailContent (private)
$refTransport = new \ReflectionClass($transport);
$buildContentMethod = $refTransport->getMethod('buildEmailContent');
$buildContentMethod->setAccessible(true);
$content = $buildContentMethod->invoke($transport, $message);

echo "Computed content:\n";
echo $content;

// Show XOAUTH2 auth string if configured
if (!empty($config['smtp_oauth_token'])) {
    $authStr = 'user=' . $config['smtp_username'] . "\x01auth=Bearer " . $config['smtp_oauth_token'] . "\x01\x01";
    echo "\nXOAUTH2 auth string (base64): " . base64_encode($authStr) . "\n";
}

echo "\nDone.\n";


