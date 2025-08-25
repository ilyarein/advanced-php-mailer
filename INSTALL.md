# Installation

## System requirements

- PHP 8.1 or newer
- `mbstring` extension
- `openssl` extension

## Installation via Composer

```bash
composer require advanced-mailer/mailer
```

## Manual installation

1. Download or clone the repository
2. (Optional) Install development dependencies:

```bash
composer install
```

## Configuration

### SMTP configuration

```php
$config = [
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_username' => 'your-email@gmail.com',
    'smtp_password' => 'your-app-password',
    'smtp_encryption' => 'tls',
    'smtp_timeout' => 30,
    'max_attachment_size' => 25 * 1024 * 1024, // 25MB
    'allowed_extensions' => ['pdf', 'doc', 'docx', 'txt', 'jpg', 'jpeg', 'png', 'gif']
];
```

### SendGrid configuration

```php
$apiKey = 'your-sendgrid-api-key';
$transport = new AdvancedMailer\Transport\SendGridTransport($apiKey);
```

## Standalone usage (no Composer)

Advanced Mailer is designed to work without external dependencies for basic usage. Example:

```php
// All required files can be required manually for standalone usage
require_once 'src/Mail.php';

$config = [
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_username' => 'your-email@gmail.com',
    'smtp_password' => 'your-password',
    'smtp_encryption' => 'tls'
];

$mail = new AdvancedMailer\Mail($config);
$mail->setFrom('sender@example.com');
$mail->addAddress('recipient@example.com');
$mail->setSubject('Test');
$mail->setHtmlBody('<h1>Hello!</h1>');
$mail->send();
```

## Optional dependencies

If you want to use advanced features or external loggers, install dependencies via Composer:

```bash
composer install
```

After installing, you can use Monolog or any PSR-3 logger.

## Testing

Run tests using your preferred PHPUnit setup (see `tests/README.md`).

```bash
composer test
```

## Examples

See the `examples/` directory for usage samples:

- `examples/basic_usage.php` - basic usage
- `examples/queue_usage.php` - queue usage
- `examples/template_usage.php` - template usage
- `examples/sendgrid_usage.php` - SendGrid integration

## Project structure

```
├── src/
│   ├── Mail.php                 # Main class
│   ├── Promise.php              # Promise helper for async sending
│   ├── Transport/               # Transports
│   │   ├── TransportInterface.php
│   │   ├── SmtpTransport.php
│   │   └── SendGridTransport.php
│   ├── Validation/              # Validation
│   │   └── EmailValidator.php
│   ├── Template/                # Templates
│   │   └── TemplateEngine.php
│   ├── Queue/                   # Queue
│   │   └── MailQueue.php
│   └── Exception/               # Exceptions
│       └── MailException.php
├── examples/                    # Examples
├── tests/                       # Tests
├── composer.json               # Composer configuration
└── README.md                   # Documentation
```

## License

MIT License - see the `LICENSE` file for details.
