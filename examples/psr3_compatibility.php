<?php

require_once __DIR__ . '/../vendor/autoload.php';

use AdvancedMailer\Mail;
use AdvancedMailer\LoggerInterface;
use Psr\Log\LoggerInterface as PsrLoggerInterface; // For demonstration of compatibility

/**
 * PSR-3 compatibility demo
 *
 * This example shows how Advanced Mailer can work with any PSR-3 compatible logger
 */

// Configuration
$config = [
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_username' => 'your-email@gmail.com',
    'smtp_password' => 'your-app-password',
    'smtp_encryption' => 'tls'
];

echo "=== PSR-3 compatibility: built-in implementation ===\n";

// 1. Using the built-in PSR-3 implementation
$mail = new Mail($config);

// The built-in logger is already PSR-3 compatible
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

// Configure message
 $mail->setFrom('sender@example.com', 'Test Sender');
 $mail->addAddress('recipient@example.com', 'Test Recipient');
 $mail->setSubject('PSR-3 compatibility');
 $mail->setHtmlBody('<h1>PSR-3 logging test</h1>');

echo "Creating message...\n";

try {
    // This will call various logging methods
    $mail->send();
} catch (Exception $e) {
    echo "Send error: " . $e->getMessage() . "\n";
}

echo "\n=== Ready to work with external PSR-3 loggers ===\n";

// 2. Demonstrate Monolog availability (if installed)
if (class_exists('Monolog\Logger')) {
    echo "Monolog found! You can use it:\n";

    echo "```php\n";
    echo "use Monolog\Logger;\n";
    echo "use Monolog\Handler\StreamHandler;\n";
    echo "\n";
    echo "\$monolog = new Logger('mail');\n";
    echo "\$monolog->pushHandler(new StreamHandler('logs/mail.log'));\n";
    echo "\n";
    echo "\$mail = new Mail(\$config);\n";
    echo "\$mail->setLogger(\$monolog); // Full compatibility!\n";
    echo "```\n";
} else {
    echo "Monolog not installed. To use it, run:\n";
    echo "composer require monolog/monolog\n\n";

    echo "Example using Monolog:\n";
    echo "```php\n";
    echo "\$monolog = new Monolog\Logger('mail');\n";
    echo "\$monolog->pushHandler(new Monolog\Handler\StreamHandler('logs/mail.log'));\n";
    echo "\n";
    echo "\$mail = new Mail(\$config);\n";
    echo "\$mail->setLogger(\$monolog); // Works without changes!\n";
    echo "```\n";
}

echo "\n=== PSR-3 compatibility benefits ===\n";
echo "✓ Standardized interface\n";
echo "✓ Easy logger replacement\n";
echo "✓ Works with any PSR-3 implementations\n";
echo "✓ Rich logging context\n";
echo "✓ 8 severity levels\n";
echo "✓ Structured log data\n";

echo "\n=== Example of using with different loggers ===\n";

// 3. Example adapter for legacy logging systems
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

echo "Legacy logger adapter created!\n";
echo "You can adapt any legacy logger to PSR-3 now.\n\n";

echo "🎉 Advanced Mailer is PSR-3 compatible!\n";
