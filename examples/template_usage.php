<?php

require_once __DIR__ . '/../vendor/autoload.php';

use AdvancedMailer\Mail;
use AdvancedMailer\Template\TemplateEngine;

// Конфигурация SMTP
$config = [
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_username' => 'your-email@gmail.com',
    'smtp_password' => 'your-app-password',
    'smtp_encryption' => 'tls'
];

// Создание экземпляра Mail
$mail = new Mail($config);

// Создание шаблонного движка
$templateEngine = new TemplateEngine(__DIR__ . '/templates');

// Создание шаблона приветствия
$welcomeTemplate = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Добро пожаловать</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h1 style="color: #2c3e50;">Добро пожаловать, {{name}}!</h1>

        <p>Спасибо за регистрацию на нашем сайте.</p>

        <div style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;">
            <p><strong>Ваш логин:</strong> {{username}}</p>
            <p><strong>Email:</strong> {{email}}</p>
        </div>

        <p>Для активации аккаунта перейдите по ссылке:</p>
        <p style="text-align: center; margin: 30px 0;">
            <a href="{{activation_link}}" style="background-color: #3498db; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">Активировать аккаунт</a>
        </p>

        {if company_name}
        <p><strong>Компания:</strong> {{company_name}}</p>
        {/if}

        <hr style="border: none; border-top: 1px solid #eee; margin: 30px 0;">

        <p>С уважением,<br>
        <strong>{{site_name}}</strong></p>
    </div>
</body>
</html>
';

// Сохранение шаблона
$templateEngine->saveTemplate('welcome.html', $welcomeTemplate);

// Настройка письма с использованием шаблона
$mail->setFrom('noreply@example.com', 'Система уведомлений')
     ->addAddress('newuser@example.com', 'Новый пользователь')
     ->setSubject('Добро пожаловать!')
     ->useTemplate('welcome', [
         'name' => 'Иван Петров',
         'username' => 'ivan_petrov',
         'email' => 'newuser@example.com',
         'activation_link' => 'https://example.com/activate?token=abc123',
         'company_name' => 'Тестовая компания',
         'site_name' => 'Мой сайт'
     ]);

// Отправка письма
try {
    if ($mail->send()) {
        echo "Приветственное письмо отправлено успешно!\n";
    } else {
        echo "Ошибка отправки письма.\n";
    }
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}

// Демонстрация других возможностей шаблонов
echo "\n=== Другие примеры шаблонов ===\n";

// Шаблон с циклом
$listTemplate = '
<h1>Список пользователей</h1>
<ul>
{foreach users as user}
    <li>{{user.name}} ({{user.email}})</li>
{/foreach}
</ul>
';

$mail2 = new Mail($config);
$mail2->setFrom('noreply@example.com', 'Система отчетов')
      ->addAddress('admin@example.com', 'Администратор')
      ->setSubject('Еженедельный отчет')
      ->setHtmlBody($templateEngine->renderString($listTemplate, [
          'users' => [
              ['name' => 'Иван', 'email' => 'ivan@example.com'],
              ['name' => 'Мария', 'email' => 'maria@example.com'],
              ['name' => 'Алексей', 'email' => 'alex@example.com']
          ]
      ]));

// Отправка второго письма
try {
    if ($mail2->send()) {
        echo "Отчет отправлен успешно!\n";
    } else {
        echo "Ошибка отправки отчета.\n";
    }
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}

// Показать доступные шаблоны
echo "\nДоступные шаблоны:\n";
$templates = $templateEngine->getAvailableTemplates();
foreach ($templates as $template) {
    echo "- $template\n";
}
