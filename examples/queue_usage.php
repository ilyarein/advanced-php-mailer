<?php

require_once __DIR__ . '/../vendor/autoload.php';

use AdvancedMailer\Mail;
use AdvancedMailer\Queue\MailQueue;
use AdvancedMailer\Transport\SmtpTransport;

// Конфигурация SMTP
$config = [
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_username' => 'your-email@gmail.com',
    'smtp_password' => 'your-app-password',
    'smtp_encryption' => 'tls'
];

// Создание транспорта
$transport = new SmtpTransport($config);

// Создание очереди
$queue = new MailQueue($transport);

// Создание нескольких писем
$mails = [];

// Письмо 1
$mail1 = new Mail($config);
$mail1->setFrom('sender@example.com', 'Отправитель')
      ->addAddress('user1@example.com', 'Пользователь 1')
      ->setSubject('Приветствие 1')
      ->setHtmlBody('<h1>Привет, Пользователь 1!</h1>');

$mails[] = $mail1;

// Письмо 2
$mail2 = new Mail($config);
$mail2->setFrom('sender@example.com', 'Отправитель')
      ->addAddress('user2@example.com', 'Пользователь 2')
      ->setSubject('Приветствие 2')
      ->setHtmlBody('<h1>Привет, Пользователь 2!</h1>');

$mails[] = $mail2;

// Письмо 3 с высоким приоритетом
$mail3 = new Mail($config);
$mail3->setFrom('sender@example.com', 'Отправитель')
      ->addAddress('vip@example.com', 'VIP Пользователь')
      ->setSubject('Срочное сообщение')
      ->setHtmlBody('<h1>Срочное VIP сообщение!</h1>');

$mails[] = $mail3;

// Добавление писем в очередь с разными приоритетами
foreach ($mails as $index => $mail) {
    $priority = ($index === 2) ? 1 : 5; // VIP письмо имеет высокий приоритет
    $queue->add($mail, $priority);
}

echo "Писем в очереди: " . $queue->getSize() . "\n";

// Обработка очереди
$results = $queue->process(5);

echo "Результаты обработки:\n";
echo "- Всего обработано: {$results['processed']}\n";
echo "- Успешно отправлено: {$results['successful']}\n";
echo "- Ошибок: {$results['failed']}\n";
echo "- Повторных попыток: {$results['retried']}\n";

// Статистика очереди
$stats = $queue->getStats();
echo "\nСтатистика очереди:\n";
echo "- Всего в очереди: {$stats['total']}\n";
echo "- Ожидают обработки: {$stats['pending']}\n";
echo "- Ожидают повторной попытки: {$stats['waiting']}\n";
echo "- Неудачных: {$stats['failed']}\n";

// Показать неудачные письма
$failed = $queue->getFailed();
if (!empty($failed)) {
    echo "\nНеудачные письма:\n";
    foreach ($failed as $failedMail) {
        echo "- ID: {$failedMail['id']}, Попыток: {$failedMail['retries']}\n";
    }
}
