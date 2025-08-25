<?php

/**
 * Пример использования Advanced Mailer БЕЗ Composer
 *
 * Этот файл демонстрирует, что Advanced Mailer работает полностью
 * без установки внешних зависимостей через Composer.
 */

// Подключение основных классов
require_once __DIR__ . '/../src/Mail.php';
require_once __DIR__ . '/../src/Transport/SmtpTransport.php';
require_once __DIR__ . '/../src/Validation/EmailValidator.php';
require_once __DIR__ . '/../src/Exception/MailException.php';
require_once __DIR__ . '/../src/Template/TemplateEngine.php';

echo "=== Advanced Mailer - Standalone Usage ===\n\n";

// Конфигурация SMTP
$config = [
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_username' => 'your-email@gmail.com',
    'smtp_password' => 'your-app-password',
    'smtp_encryption' => 'tls'
];

try {
    // Создание экземпляра Mail
    $mail = new AdvancedMailer\Mail($config);

    // Настройка письма
    $mail->setFrom('sender@example.com', 'Тестовый отправитель');
    $mail->addAddress('recipient@example.com', 'Тестовый получатель');
    $mail->setSubject('Тестовое письмо - Standalone Mode');
    $mail->setHtmlBody('
        <div style="font-family: Arial, sans-serif; padding: 20px;">
            <h1 style="color: #333;">🎉 Advanced Mailer</h1>
            <p>Это письмо отправлено <strong>БЕЗ</strong> использования Composer!</p>
            <p>Все работает на встроенных компонентах:</p>
            <ul>
                <li>✅ Встроенный PSR-3 логгер</li>
                <li>✅ Собственная валидация email</li>
                <li>✅ SMTP транспорт</li>
                <li>✅ Обработка вложений</li>
                <li>✅ Шаблонизация</li>
            </ul>
            <p style="color: #666; font-size: 12px;">
                Отправлено: ' . date('Y-m-d H:i:s') . '
            </p>
        </div>
    ');

    // Добавление альтернативного текста
    $mail->setAltBody('Advanced Mailer работает без Composer! Отправлено: ' . date('Y-m-d H:i:s'));

    // Добавление вложения (если файл существует)
    $attachmentPath = __DIR__ . '/sample.txt';
    if (file_exists($attachmentPath)) {
        $mail->addAttachment($attachmentPath, 'sample.txt');
    }

    // Отправка письма
    if ($mail->send()) {
        echo "✅ Письмо успешно отправлено!\n";
        echo "📧 Проверьте почту получателя\n";
    } else {
        echo "❌ Ошибка отправки письма\n";
    }

} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
    echo "💡 Убедитесь, что SMTP настройки корректны\n";
}

// Демонстрация валидации email
echo "\n=== Демонстрация валидации Email ===\n";

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

// Демонстрация санитизации
echo "\n=== Демонстрация санитизации ===\n";
$dirtyEmails = [
    '  USER@EXAMPLE.COM  ',
    'Test@Domain.Org',
    '  invalid email  '
];

foreach ($dirtyEmails as $email) {
    $clean = $validator->sanitize($email);
    echo "'$email' -> '$clean'\n";
}

echo "\n🎉 Advanced Mailer полностью независим от внешних зависимостей!\n";
echo "📚 Все основные функции работают out-of-the-box\n";
