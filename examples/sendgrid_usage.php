<?php

require_once __DIR__ . '/../vendor/autoload.php';

use AdvancedMailer\Mail;
use AdvancedMailer\Transport\SendGridTransport;

// Конфигурация SendGrid
$apiKey = 'your-sendgrid-api-key-here';

// Создание SendGrid транспорта
$transport = new SendGridTransport($apiKey);

// Создание экземпляра Mail с SendGrid транспортом
$mail = new Mail();
$mail->setTransport($transport);

// Настройка отправителя (должен быть верифицирован в SendGrid)
$mail->setFrom('verified-sender@example.com', 'Ваш Сервис');

// Добавление получателей
$mail->addAddress('recipient1@example.com', 'Получатель 1');
$mail->addAddress('recipient2@example.com', 'Получатель 2');

// Добавление копий
$mail->addCC('cc@example.com', 'Копия');
$mail->addBCC('bcc@example.com', 'Скрытая копия');

// Настройка reply-to
$mail->setReplyTo('support@example.com', 'Служба поддержки');

// Настройка темы и контента
$mail->setSubject('Тестовое письмо через SendGrid');
$mail->setHtmlBody('
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
        <h1 style="color: #333;">Привет от SendGrid!</h1>
        <p>Это письмо отправлено через <strong>SendGrid API</strong> используя Advanced Mailer.</p>

        <div style="background-color: #f0f0f0; padding: 20px; border-radius: 5px; margin: 20px 0;">
            <h3>Преимущества SendGrid:</h3>
            <ul>
                <li>Высокая доставляемость</li>
                <li>Подробная аналитика</li>
                <li>Масштабируемость</li>
                <li>Надежная инфраструктура</li>
            </ul>
        </div>

        <p style="color: #666; font-size: 14px;">
            Это письмо отправлено через Advanced Mailer - улучшенную альтернативу PHPMailer.
        </p>
    </div>
');
$mail->setAltBody('Привет! Это письмо отправлено через SendGrid API используя Advanced Mailer. Это текстовая версия для клиентов, которые не поддерживают HTML.');

// Добавление пользовательских заголовков
$mail->addHeader('X-Mailer', 'Advanced Mailer with SendGrid');
$mail->addHeader('X-Priority', '1');

// Тестирование соединения перед отправкой
if ($transport->testConnection()) {
    echo "SendGrid соединение успешно установлено.\n";

    // Отправка письма
    try {
        if ($mail->send()) {
            echo "Письмо успешно отправлено через SendGrid!\n";
        } else {
            echo "Ошибка отправки письма.\n";
        }
    } catch (Exception $e) {
        echo "Ошибка: " . $e->getMessage() . "\n";
    }
} else {
    echo "Не удалось установить соединение с SendGrid. Проверьте API ключ.\n";
}

// Пример массовой рассылки
echo "\n=== Пример массовой рассылки ===\n";

// Создание шаблонного письма для массовой рассылки
$newsletterTemplate = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Еженедельная рассылка</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h1 style="color: #2c3e50;">Привет, {{name}}!</h1>

    <p>Вот наша еженедельная подборка интересных новостей:</p>

    <div style="background-color: #e8f4f8; padding: 15px; border-radius: 5px; margin: 20px 0;">
        <h3>🆕 Новые возможности</h3>
        <ul>
            <li>Добавлена поддержка темной темы</li>
            <li>Улучшена производительность на 30%</li>
            <li>Новые интеграции с популярными сервисами</li>
        </ul>
    </div>

    <div style="background-color: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0;">
        <h3>📅 Ближайшие события</h3>
        <p><strong>Вебинар:</strong> "Как улучшить доставляемость email" - {{date}}</p>
        <p><a href="{{webinar_link}}" style="color: #007bff;">Регистрация</a></p>
    </div>

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{unsubscribe_link}}" style="color: #6c757d; font-size: 12px;">Отписаться от рассылки</a>
    </div>

    <hr style="border: none; border-top: 1px solid #eee; margin: 30px 0;">

    <p style="color: #666; font-size: 14px; text-align: center;">
        Вы получили это письмо, потому что подписались на рассылку.<br>
        {{company_name}} | {{current_year}}
    </p>
</body>
</html>
';

// Список подписчиков для массовой рассылки
$subscribers = [
    ['email' => 'user1@example.com', 'name' => 'Иван'],
    ['email' => 'user2@example.com', 'name' => 'Мария'],
    ['email' => 'user3@example.com', 'name' => 'Алексей'],
];

$successful = 0;
$failed = 0;

foreach ($subscribers as $subscriber) {
    $newsletterMail = new Mail();
    $newsletterMail->setTransport($transport);

    $newsletterMail->setFrom('newsletter@example.com', 'Еженедельная рассылка')
                   ->addAddress($subscriber['email'], $subscriber['name'])
                   ->setSubject('Ваша еженедельная подборка новостей')
                   ->setHtmlBody($newsletterTemplate);

    // Замена переменных в шаблоне
    $body = $newsletterMail->getHtmlBody();
    $body = str_replace('{{name}}', $subscriber['name'], $body);
    $body = str_replace('{{date}}', '15 сентября 2024', $body);
    $body = str_replace('{{webinar_link}}', 'https://example.com/webinar', $body);
    $body = str_replace('{{unsubscribe_link}}', 'https://example.com/unsubscribe', $body);
    $body = str_replace('{{company_name}}', 'Тестовая компания', $body);
    $body = str_replace('{{current_year}}', date('Y'), $body);

    $newsletterMail->setHtmlBody($body);

    try {
        if ($newsletterMail->send()) {
            echo "✓ Письмо отправлено: {$subscriber['email']}\n";
            $successful++;
        } else {
            echo "✗ Ошибка отправки: {$subscriber['email']}\n";
            $failed++;
        }
    } catch (Exception $e) {
        echo "✗ Ошибка: {$subscriber['email']} - " . $e->getMessage() . "\n";
        $failed++;
    }

    // Небольшая задержка между отправками
    sleep(1);
}

echo "\n=== Результаты массовой рассылки ===\n";
echo "Успешно отправлено: $successful\n";
echo "Ошибок: $failed\n";
echo "Всего: " . count($subscribers) . "\n";
