<?php

require_once __DIR__ . '/../vendor/autoload.php';

use AdvancedMailer\Mail;
use AdvancedMailer\Queue\MailQueue;
use AdvancedMailer\Transport\SmtpTransport;

// SMTP configuration
$config = [
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_username' => 'your-email@gmail.com',
    'smtp_password' => 'your-app-password',
    'smtp_encryption' => 'tls'
];

// Create transport
$transport = new SmtpTransport($config);

// Create queue
$queue = new MailQueue($transport);

// Create several mails
$mails = [];

// Mail 1
$mail1 = new Mail($config);
$mail1->setFrom('sender@example.com', 'Sender')
      ->addAddress('user1@example.com', 'User 1')
      ->setSubject('Greeting 1')
      ->setHtmlBody('<h1>Hello, User 1!</h1>');

$mails[] = $mail1;

// Mail 2
$mail2 = new Mail($config);
$mail2->setFrom('sender@example.com', 'Sender')
      ->addAddress('user2@example.com', 'User 2')
      ->setSubject('Greeting 2')
      ->setHtmlBody('<h1>Hello, User 2!</h1>');

$mails[] = $mail2;

// Mail 3 with high priority
$mail3 = new Mail($config);
$mail3->setFrom('sender@example.com', 'Sender')
      ->addAddress('vip@example.com', 'VIP User')
      ->setSubject('Urgent message')
      ->setHtmlBody('<h1>Urgent VIP message!</h1>');

$mails[] = $mail3;

// Add mails to queue with priorities
foreach ($mails as $index => $mail) {
    $priority = ($index === 2) ? 1 : 5; // VIP mail has high priority
    $queue->add($mail, $priority);
}

echo "Mails in queue: " . $queue->getSize() . "\n";

// Process queue
$results = $queue->process(5);

echo "Processing results:\n";
echo "- Total processed: {$results['processed']}\n";
echo "- Successfully sent: {$results['successful']}\n";
echo "- Errors: {$results['failed']}\n";
echo "- Retries: {$results['retried']}\n";

// Queue statistics
$stats = $queue->getStats();
echo "\nQueue statistics:\n";
echo "- Total in queue: {$stats['total']}\n";
echo "- Pending: {$stats['pending']}\n";
echo "- Waiting for retry: {$stats['waiting']}\n";
echo "- Failed: {$stats['failed']}\n";

// Show failed mails
$failed = $queue->getFailed();
if (!empty($failed)) {
    echo "\nFailed mails:\n";
    foreach ($failed as $failedMail) {
        echo "- ID: {$failedMail['id']}, Retries: {$failedMail['retries']}\n";
    }
}
