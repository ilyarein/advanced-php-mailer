# Advanced Mailer

![CI](https://github.com/ilyarein/advanced-php-mailer/actions/workflows/ci.yml/badge.svg)

Advanced Mailer is a modular PHP mailer library designed for flexibility and production usage. It provides multiple transports, a queueing system, a simple templating engine, email validation, and PSR-3 compatible logging.

Purpose
- Unified sending API across different transports (SMTP, SendGrid, etc.).
- Usable in standalone projects without Composer, but also Composer-friendly.

Key features
- Modular architecture with `TransportInterface` for easy extension.
- Built-in transports: SMTP and SendGrid.
- Queueing system with priorities and retry logic (`Queue/MailQueue.php`).
- Simple template engine for generating HTML emails (`Template/TemplateEngine.php`).
- Promise-like API and a helper for background sending (`Promise`, `Mail::sendAsync()`).
- Email validation (format + optional DNS checks) (`Validation/EmailValidator.php`).
- Attachments and embedded images handling with type/size checks.
- PSR-3 compatible logging interface with a `NullLogger` provided.
- Basic DKIM and S/MIME signing helpers (configure keys/certificates in transport config).

Requirements
- PHP >= 8.1
- Recommended extensions: `mbstring`, `openssl`, `fileinfo` (finfo). For `SendGridTransport` the `curl` extension is required.

Installation and usage

Standalone (no Composer):
```php
require_once 'src/Mail.php';

$config = [
    'smtp_host' => 'smtp.example.com',
    'smtp_port' => 587,
    'smtp_username' => 'user@example.com',
    'smtp_password' => 'secret',
    'smtp_encryption' => 'tls'
];

$mail = new AdvancedMailer\Mail($config);
$mail->setFrom('from@example.com', 'Sender')
     ->addAddress('to@example.com', 'Recipient')
     ->setSubject('Subject')
     ->setHtmlBody('<h1>Example</h1>')
     ->send();
```

With Composer (optional):
```bash
composer install
require 'vendor/autoload.php'
```

Logging
-----
Advanced Mailer provides a PSR-3 compatible interface. You can pass any PSR-3 logger (e.g., Monolog) or use the included `NullLogger` for no-op logging.

License
-------
This project is distributed under the MIT License — see the `LICENSE` file.

Support and contribution
------------------------
Issues and pull requests are welcome on GitHub. Please open an issue before submitting large or breaking changes.

## Sponsorship

Development of Advanced Mailer is supported by the author and community contributions. If you find this library useful, you can support development in several ways:

- **GitHub Sponsors**: become a sponsor through the standard GitHub sponsorship program.
- **One-time donations**: the author appreciates small contributions for coffee or to support AI service subscriptions.

Supported crypto wallets (one-time donations accepted):

- **USDT (TON)** - `UQBE_vWtGeQXvx0HtPiSTGcHU-TRdnufjSLLcXp-oUoisRQe`
- **USDT (TRON TRC20)** - `TZGQLK5azZBfoFK53X5HhqnyqRW5YzhTKm`
- **USDT (SOL)** - `ANDH4PqyEk27D6cooiVfnktT8isUPe6hBPt3xPecMzmp`
- **USDT (BSC BEP20)** - `0x01015a3e522b316c01c049ba432c843bd504a29d`
- **BTC** - `1CGt3a14FYUjjzcsVE5VBFUUGtjdndiKcs`

Thank you for considering support — every contribution helps maintain and improve the project.

## Asynchronous sending

Advanced Mailer provides a practical asynchronous sending helper via `Mail::sendAsync()`.

- Behavior: `sendAsync()` writes the prepared message to a temporary JSON file and attempts to spawn a background PHP process that runs `bin/send_async.php` to perform the actual send. This avoids blocking the current request when `proc_open` and PHP CLI are available.
- Requirements for background send:
  - `proc_open` must be available in PHP and not disabled by hosting provider
  - PHP CLI binary must be accessible (PHP_BINARY)
  - File system writable for temporary files (`sys_get_temp_dir()`)
- Fallback: if the environment does not allow spawning a background process, `sendAsync()` falls back to a synchronous `send()` call and resolves/rejects the returned Promise accordingly.

Security and reliability notes:
- On shared hosting `proc_open` is often disabled — check with your provider. In such environments `sendAsync()` will use the synchronous fallback.
- Ensure temporary files directory is secure and writable by the PHP process.
- For robust background processing consider using a real queue/worker system (e.g., Supervisor + CLI worker, system queue) for high throughput.
