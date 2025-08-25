<?php

// Пример безопасного обработчика контактной формы для использования с Advanced Mailer

require_once __DIR__ . '/../src/Mail.php';
require_once __DIR__ . '/../src/Transport/SmtpTransport.php';
require_once __DIR__ . '/../src/Validation/EmailValidator.php';
require_once __DIR__ . '/../src/Exception/MailException.php';

use AdvancedMailer\Mail;
use AdvancedMailer\Transport\SmtpTransport;
use AdvancedMailer\Validation\EmailValidator;
use AdvancedMailer\Exception\MailException;
use AdvancedMailer\LoggerInterface;

// Простая реализация логгера в файл
class FileLogger implements LoggerInterface
{
    private string $filePath;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    private function write(string $level, string $message, array $context = []): void
    {
        $time = date('Y-m-d H:i:s');
        $line = sprintf("%s [%s] %s %s\n", $time, strtoupper($level), $message, json_encode($context, JSON_UNESCAPED_UNICODE));
        @file_put_contents($this->filePath, $line, FILE_APPEND | LOCK_EX);
    }

    public function emergency(string $message, array $context = []): void { $this->write('emergency', $message, $context); }
    public function alert(string $message, array $context = []): void { $this->write('alert', $message, $context); }
    public function critical(string $message, array $context = []): void { $this->write('critical', $message, $context); }
    public function error(string $message, array $context = []): void { $this->write('error', $message, $context); }
    public function warning(string $message, array $context = []): void { $this->write('warning', $message, $context); }
    public function notice(string $message, array $context = []): void { $this->write('notice', $message, $context); }
    public function info(string $message, array $context = []): void { $this->write('info', $message, $context); }
    public function debug(string $message, array $context = []): void { $this->write('debug', $message, $context); }
    public function log($level, string $message, array $context = []): void { $this->write((string)$level, $message, $context); }
}

// Настройки: замените на реальные значения
$smtpConfig = [
    'smtp_host' => 'smtp.example.com',
    'smtp_port' => 587,
    'smtp_username' => 'smtp-user',
    'smtp_password' => 'smtp-pass',
    'smtp_encryption' => 'tls',
    'smtp_timeout' => 30,
    'max_attachment_size' => 5 * 1024 * 1024, // 5 MB
    'allowed_extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'txt']
];

$log = new FileLogger(__DIR__ . '/../logs/contact_form.log');

// Только POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Получаем поля
$rawName = trim((string)($_POST['name'] ?? ''));
$rawEmail = trim((string)($_POST['email'] ?? ''));
$rawMessage = trim((string)($_POST['message'] ?? ''));

$validator = new EmailValidator();

$errors = [];

// Валидация имени
if ($rawName === '' || mb_strlen($rawName) > 200) {
    $errors[] = 'Invalid name';
}

// Валидация email
$cleanEmail = $validator->sanitize($rawEmail);
if ($cleanEmail === '' || !$validator->isValidQuick($cleanEmail)) {
    $errors[] = 'Invalid email';
}

// Валидация сообщения
if ($rawMessage === '' || mb_strlen($rawMessage) > 10000) {
    $errors[] = 'Invalid message';
}

// Обработка вложения (опционально)
$attachmentPath = null;
if (!empty($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
    $uploaded = $_FILES['attachment'];
    $ext = strtolower(pathinfo($uploaded['name'], PATHINFO_EXTENSION));
    if ($uploaded['size'] > $smtpConfig['max_attachment_size']) {
        $errors[] = 'Attachment too large';
    } elseif (!in_array($ext, $smtpConfig['allowed_extensions'], true)) {
        $errors[] = 'Attachment type not allowed';
    } else {
        // Временное имя файла (используем tmp_name — файл доступен для чтения)
        $attachmentPath = $uploaded['tmp_name'];
    }
}

if (!empty($errors)) {
    http_response_code(400);
    $log->warning('Contact form validation failed', ['errors' => $errors, 'ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

// Подготавливаем письмо
$siteFromEmail = 'no-reply@example.com'; // должен быть действительным для вашего домена
$siteRecipientEmail = 'contact@example.com'; // куда приходят сообщения

$mail = new Mail($smtpConfig);
// Явно установить транспорт, чтобы использовать наш логгер
$smtpTransport = new SmtpTransport($smtpConfig, $log);
$mail->setTransport($smtpTransport);
$mail->setLogger($log);

$mail->setFrom($siteFromEmail, 'Website Contact');
$mail->setReplyTo($cleanEmail, $rawName);
$mail->addAddress($siteRecipientEmail);
$mail->setSubject('New contact form message from ' . $rawName);

// Подготовка HTML и plain
$safeName = htmlspecialchars($rawName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$safeEmail = htmlspecialchars($cleanEmail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$safeMessage = nl2br(htmlspecialchars($rawMessage, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));

$htmlBody = "<h2>Contact form message</h2>" .
    "<p><strong>Name:</strong> {$safeName}</p>" .
    "<p><strong>Email:</strong> {$safeEmail}</p>" .
    "<p><strong>Message:</strong><br>{$safeMessage}</p>";

$plainBody = "Name: {$rawName}\nEmail: {$cleanEmail}\n\n{$rawMessage}";

$mail->setHtmlBody($htmlBody);
$mail->setAltBody($plainBody);

if ($attachmentPath !== null) {
    // Mail::addAttachment проверяет существование файла
    $mail->addAttachment($attachmentPath, basename($_FILES['attachment']['name']));
}

try {
    $result = $mail->send();
    if ($result) {
        $log->info('Contact form email sent', ['from' => $cleanEmail, 'to' => $siteRecipientEmail]);
        echo json_encode(['success' => true]);
    } else {
        $log->error('Contact form email failed without exception', ['from' => $cleanEmail]);
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to send email']);
    }
} catch (MailException $e) {
    $log->error('Contact form MailException', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Mailer error']);
} catch (Exception $e) {
    $log->error('Contact form unexpected exception', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unexpected error']);
}


