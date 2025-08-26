<?php

require_once __DIR__ . '/../vendor/autoload.php';

use AdvancedMailer\Mail;
use AdvancedMailer\Template\TemplateEngine;

// SMTP configuration
$config = [
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_username' => 'your-email@gmail.com',
    'smtp_password' => 'your-app-password',
    'smtp_encryption' => 'tls'
];

// Create Mail instance
$mail = new Mail($config);

// Create template engine
$templateEngine = new TemplateEngine(__DIR__ . '/templates');

// Create welcome template
$welcomeTemplate = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Welcome</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h1 style="color: #2c3e50;">Welcome, {{name}}!</h1>

        <p>Thank you for registering on our site.</p>

        <div style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;">
            <p><strong>Your login:</strong> {{username}}</p>
            <p><strong>Email:</strong> {{email}}</p>
        </div>

        <p>To activate your account, please click the link:</p>
        <p style="text-align: center; margin: 30px 0;">
            <a href="{{activation_link}}" style="background-color: #3498db; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">Activate account</a>
        </p>

        {if company_name}
        <p><strong>Company:</strong> {{company_name}}</p>
        {/if}

        <hr style="border: none; border-top: 1px solid #eee; margin: 30px 0;">

        <p>Best regards,<br>
        <strong>{{site_name}}</strong></p>
    </div>
</body>
</html>
';

// Save template
$templateEngine->saveTemplate('welcome.html', $welcomeTemplate);

// Configure message using template
$mail->setFrom('noreply@example.com', 'Notification System')
     ->addAddress('newuser@example.com', 'New User')
     ->setSubject('Welcome!')
     ->useTemplate('welcome', [
         'name' => 'John Doe',
         'username' => 'john_doe',
         'email' => 'newuser@example.com',
         'activation_link' => 'https://example.com/activate?token=abc123',
         'company_name' => 'Test company',
         'site_name' => 'My site'
     ]);

// Send message
try {
    if ($mail->send()) {
        echo "Welcome message sent successfully!\n";
    } else {
        echo "Error sending message.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Demonstration of other template features
echo "\n=== Other template examples ===\n";

// Loop template
$listTemplate = '
<h1>User list</h1>
<ul>
{foreach users as user}
    <li>{{user.name}} ({{user.email}})</li>
{/foreach}
</ul>
';

$mail2 = new Mail($config);
    $mail2->setFrom('noreply@example.com', 'Report System')
      ->addAddress('admin@example.com', 'Admin')
      ->setSubject('Weekly report')
      ->setHtmlBody($templateEngine->renderString($listTemplate, [
          'users' => [
              ['name' => 'John', 'email' => 'john@example.com'],
              ['name' => 'Maria', 'email' => 'maria@example.com'],
              ['name' => 'Alex', 'email' => 'alex@example.com']
          ]
      ]));

// Send second message
try {
    if ($mail2->send()) {
        echo "Report sent successfully!\n";
    } else {
        echo "Error sending report.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Show available templates
echo "\nAvailable templates:\n";
$templates = $templateEngine->getAvailableTemplates();
foreach ($templates as $template) {
    echo "- $template\n";
}
