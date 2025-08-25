<?php

require_once __DIR__ . '/../vendor/autoload.php';

use AdvancedMailer\Mail;
use AdvancedMailer\LoggerInterface;
use AdvancedMailer\NullLogger;

// Конфигурация SMTP
$config = [
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_username' => 'your-email@gmail.com',
    'smtp_password' => 'your-app-password',
    'smtp_encryption' => 'tls'
];

// Наша собственная PSR-3 совместимая реализация логгера
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

// Создание логгера
$logger = new FileLogger('mail_operations.log');

// Создание экземпляра Mail с PSR-3 совместимым логгером
$mail = new Mail($config);
$mail->setLogger($logger);

// Настройка отправителя
$mail->setFrom('your-email@gmail.com', 'Ваш Имя');

// Добавление получателей
$mail->addAddress('recipient1@example.com', 'Получатель 1');
$mail->addAddress('recipient2@example.com'); // Без имени

// Добавление копий
$mail->addCC('cc@example.com', 'Копия');
$mail->addBCC('bcc@example.com'); // Скрытая копия

// Настройка reply-to
$mail->setReplyTo('reply@example.com', 'Ответить сюда');

// Настройка темы и контента
$mail->setSubject('Тестовое письмо от Advanced Mailer');
$mail->setHtmlBody('
    <h1>Привет!</h1>
    <p>Это <strong>HTML</strong> версия письма.</p>
    <p>С наилучшими пожеланиями,<br>Advanced Mailer</p>
');
$mail->setAltBody('Привет! Это текстовая версия письма. С наилучшими пожеланиями, Advanced Mailer');

// Демонстрация различных уровней PSR-3 логирования
echo "=== Демонстрация PSR-3 уровней логирования ===\n";

$logger->emergency('Критическая ошибка системы', ['system' => 'mail', 'impact' => 'high']);
$logger->alert('Требуется срочное вмешательство', ['component' => 'smtp', 'issue' => 'connection']);
$logger->critical('Критическая ошибка отправки', ['recipients' => 5, 'transport' => 'smtp']);
$logger->error('Ошибка валидации email', ['email' => 'invalid-email', 'validation' => 'failed']);
$logger->warning('Превышен лимит вложений', ['size' => '15MB', 'limit' => '10MB']);
$logger->notice('Используется альтернативный транспорт', ['transport' => 'sendgrid']);
$logger->info('Отправка письма начата', ['recipients' => 1, 'subject' => 'Тест']);
$logger->debug('Отладочная информация', ['memory_usage' => memory_get_usage(), 'timestamp' => microtime(true)]);

echo "Логи записаны в файл: mail_operations.log\n\n";

// Отправка письма
try {
    if ($mail->send()) {
        echo "Письмо отправлено успешно!\n";
        echo "Проверьте логи в файле mail_operations.log для подробной информации\n";
    } else {
        echo "Ошибка отправки письма.\n";
    }
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
    echo "Подробная информация в логах\n";
}
