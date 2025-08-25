<?php

require_once __DIR__ . '/../vendor/autoload.php';

use AdvancedMailer\Mail;
use AdvancedMailer\LoggerInterface;
use Psr\Log\LoggerInterface as PsrLoggerInterface; // Для демонстрации совместимости

/**
 * Демонстрация PSR-3 совместимости
 *
 * Этот пример показывает, как Advanced Mailer может работать
 * с любым PSR-3 совместимым логгером
 */

// Конфигурация
$config = [
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_username' => 'your-email@gmail.com',
    'smtp_password' => 'your-app-password',
    'smtp_encryption' => 'tls'
];

echo "=== PSR-3 Совместимость: Собственная реализация ===\n";

// 1. Использование собственной PSR-3 реализации
$mail = new Mail($config);

// Наша собственная реализация уже совместима с PSR-3
$customLogger = new class implements LoggerInterface {
    public function emergency(string $message, array $context = []): void {
        echo "[EMERGENCY] $message\n";
    }
    public function alert(string $message, array $context = []): void {
        echo "[ALERT] $message\n";
    }
    public function critical(string $message, array $context = []): void {
        echo "[CRITICAL] $message\n";
    }
    public function error(string $message, array $context = []): void {
        echo "[ERROR] $message\n";
    }
    public function warning(string $message, array $context = []): void {
        echo "[WARNING] $message\n";
    }
    public function notice(string $message, array $context = []): void {
        echo "[NOTICE] $message\n";
    }
    public function info(string $message, array $context = []): void {
        echo "[INFO] $message\n";
    }
    public function debug(string $message, array $context = []): void {
        echo "[DEBUG] $message\n";
    }
    public function log($level, string $message, array $context = []): void {
        echo "[$level] $message\n";
    }
};

$mail->setLogger($customLogger);

// Настройка письма
$mail->setFrom('sender@example.com', 'Тестовый отправитель');
$mail->addAddress('recipient@example.com', 'Тестовый получатель');
$mail->setSubject('PSR-3 совместимость');
$mail->setHtmlBody('<h1>Тест PSR-3 логирования</h1>');

echo "Создание письма...\n";

try {
    // Это вызовет различные методы логирования
    $mail->send();
} catch (Exception $e) {
    echo "Ошибка при отправке: " . $e->getMessage() . "\n";
}

echo "\n=== Готовность к работе с внешними PSR-3 логгерами ===\n";

// 2. Демонстрация готовности к Monolog (если он установлен)
if (class_exists('Monolog\Logger')) {
    echo "Monolog найден! Можно использовать:\n";

    echo "```php\n";
    echo "use Monolog\Logger;\n";
    echo "use Monolog\Handler\StreamHandler;\n";
    echo "\n";
    echo "\$monolog = new Logger('mail');\n";
    echo "\$monolog->pushHandler(new StreamHandler('logs/mail.log'));\n";
    echo "\n";
    echo "\$mail = new Mail(\$config);\n";
    echo "\$mail->setLogger(\$monolog); // Полная совместимость!\n";
    echo "```\n";
} else {
    echo "Monolog не установлен. Для использования установите:\n";
    echo "composer require monolog/monolog\n\n";

    echo "Пример использования с Monolog:\n";
    echo "```php\n";
    echo "\$monolog = new Monolog\Logger('mail');\n";
    echo "\$monolog->pushHandler(new Monolog\Handler\StreamHandler('logs/mail.log'));\n";
    echo "\n";
    echo "\$mail = new Mail(\$config);\n";
    echo "\$mail->setLogger(\$monolog); // Работает без изменений!\n";
    echo "```\n";
}

echo "\n=== Преимущества PSR-3 совместимости ===\n";
echo "✓ Стандартизированный интерфейс\n";
echo "✓ Легкая замена логгеров\n";
echo "✓ Совместимость с любыми PSR-3 реализациями\n";
echo "✓ Богатый контекст логирования\n";
echo "✓ 8 уровней важности\n";
echo "✓ Структурированные данные в логах\n";

echo "\n=== Пример использования с разными логгерами ===\n";

// 3. Пример адаптера для других систем логирования
class LegacyLoggerAdapter implements LoggerInterface {
    private $legacyLogger;

    public function __construct($legacyLogger) {
        $this->legacyLogger = $legacyLogger;
    }

    public function emergency(string $message, array $context = []): void {
        $this->legacyLogger->log('EMERGENCY', $message, $context);
    }

    public function alert(string $message, array $context = []): void {
        $this->legacyLogger->log('ALERT', $message, $context);
    }

    public function critical(string $message, array $context = []): void {
        $this->legacyLogger->log('CRITICAL', $message, $context);
    }

    public function error(string $message, array $context = []): void {
        $this->legacyLogger->log('ERROR', $message, $context);
    }

    public function warning(string $message, array $context = []): void {
        $this->legacyLogger->log('WARNING', $message, $context);
    }

    public function notice(string $message, array $context = []): void {
        $this->legacyLogger->log('NOTICE', $message, $context);
    }

    public function info(string $message, array $context = []): void {
        $this->legacyLogger->log('INFO', $message, $context);
    }

    public function debug(string $message, array $context = []): void {
        $this->legacyLogger->log('DEBUG', $message, $context);
    }

    public function log($level, string $message, array $context = []): void {
        $this->legacyLogger->log($level, $message, $context);
    }
}

echo "Адаптер для устаревших систем логирования создан!\n";
echo "Теперь любую систему можно адаптировать к PSR-3.\n\n";

echo "🎉 Advanced Mailer полностью совместим с PSR-3!\n";
