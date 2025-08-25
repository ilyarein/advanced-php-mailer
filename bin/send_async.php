<?php
// Helper script to send email asynchronously.
// Usage: php bin/send_async.php /path/to/tempfile.json

if ($argc < 2) {
    exit(1);
}

$tmp = $argv[1];
if (!file_exists($tmp)) {
    exit(2);
}

$data = json_decode(file_get_contents($tmp), true);
if (!is_array($data)) {
    @unlink($tmp);
    exit(3);
}

// Basic includes (standalone mode)
require_once __DIR__ . '/../src/Mail.php';
require_once __DIR__ . '/../src/Transport/SmtpTransport.php';
require_once __DIR__ . '/../src/Validation/EmailValidator.php';
require_once __DIR__ . '/../src/Exception/MailException.php';

use AdvancedMailer\Mail;
use AdvancedMailer\Transport\SmtpTransport;

try {
    $config = $data['config'] ?? [];
    $message = $data['message'] ?? [];

    $mail = new Mail($config);
    $transport = new SmtpTransport($config);
    $mail->setTransport($transport);

    if (!empty($message['from']['email'])) {
        $mail->setFrom($message['from']['email'], $message['from']['name'] ?? '');
    }

    foreach ($message['recipients'] as $r) {
        $mail->addAddress($r['email'], $r['name'] ?? '');
    }
    foreach ($message['cc'] as $r) {
        $mail->addCC($r['email'], $r['name'] ?? '');
    }
    foreach ($message['bcc'] as $r) {
        $mail->addBCC($r['email'], $r['name'] ?? '');
    }

    if (!empty($message['reply_to']['email'])) {
        $mail->setReplyTo($message['reply_to']['email'], $message['reply_to']['name'] ?? '');
    }

    $mail->setSubject($message['subject'] ?? '');
    if (!empty($message['is_html'])) {
        $mail->setHtmlBody($message['body'] ?? '');
    } else {
        $mail->setTextBody($message['body'] ?? '');
    }
    $mail->setAltBody($message['alt_body'] ?? '');

    if (!empty($message['attachments'])) {
        foreach ($message['attachments'] as $att) {
            if (file_exists($att['path'])) {
                $mail->addAttachment($att['path'], $att['name'] ?? '', $att['mime_type'] ?? '');
            }
        }
    }

    $mail->send();
} catch (Throwable $e) {
    // best-effort logging
    @file_put_contents(__DIR__ . '/../logs/async_errors.log', date('c') . " - " . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
}

@unlink($tmp);


