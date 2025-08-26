# Advanced Mailer — A fully Composer-free and politics-free PHP mailer. No PhD required

<p>
  <img alt="CI" src="https://github.com/ilyarein/advanced-php-mailer/actions/workflows/ci.yml/badge.svg" />
  <img alt="Codecov" src="https://codecov.io/gh/ilyarein/advanced-php-mailer/branch/main/graph/badge.svg?token=" />
  <img alt="Release" src="https://img.shields.io/github/v/release/ilyarein/advanced-php-mailer?label=Release&color=informational" />
</p>

Advanced Mailer is a modular PHP mailer library designed for flexibility and production usage. It provides multiple transports, a queueing system, a simple templating engine, email validation, PSR-3 compatible logging, DKIM and S/MIME signing helpers, and practical asynchronous sending.

Purpose
- Unified sending API across different transports (SMTP, SendGrid, etc.).
- Usable in standalone projects without Composer, but also Composer-friendly.

Key features
- Modular architecture with `TransportInterface` for easy extension.
- Built-in transports: SMTP and SendGrid.
- Queueing system with priorities and retry logic (`Queue/MailQueue.php`).
- Simple template engine for generating HTML emails (`Template/TemplateEngine.php`).
- Promise-like API and a helper for background sending (`Promise`, `Mail::sendAsync()`).
- DKIM signing and S/MIME signing support (optional; requires configuration of keys/certificates).
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

Shared hosting (no Composer, step-by-step)

Follow these steps to install and run Advanced Mailer on a simple shared hosting account (no Composer required):

1) Create a `config` folder next to your `public_html` and add `config/mail.php` with your SMTP configuration. Example `config/mail.php`:

```php
<?php
return [
  'smtp_host' => 'smtp.example.com',
  'smtp_port' => 465,
  'smtp_username' => 'user@example.com',
  'smtp_password' => 'secret',
  'smtp_encryption' => 'ssl',

  // explicit auth / timeout settings
  'smtp_auth' => true,
  'smtp_auth_method' => 'plain',
  'smtp_timeout' => 60,

  // default From / To addresses (can be overridden per message)
  'from_address' => 'from@example.com',
  'from_name' => 'Site',
  'to_address' => 'to@example.com',
  'to_name' => 'Recipient',

  // Logging options: disable verbose file logging by default in production
  'enable_file_logging' => false,
  'log_level' => 'error', // accepted: 'debug','info','notice','warning','error','critical'

  // Optional: DKIM / S/MIME keys paths if configured
  // 'dkim_private_key' => __DIR__ . '/keys/dkim_private.pem',
  // 'dkim_selector' => 'mail',
  // 'smime_cert' => __DIR__ . '/keys/smime.crt',
  // 'smime_key' => __DIR__ . '/keys/smime.key',
];
```

Notes: you can add `subject`, `html_body` or other fields when building messages in your contact handler; the mailer accepts message-level properties via the `Mail` API.

2) Copy `src/` and `vendor/` from the release archive into your project folder (next to `public_html`). Do NOT include `vendor` dev dependencies — use the provided release archive which contains only production dependencies.

3) Create `public_html/contact.php` as the web endpoint. Minimal secure example (place in `public_html/contact.php`):

```php
<?php
// Minimal contact endpoint for shared hosting with detailed SMTP logging
header('Content-Type: application/json');

ini_set('display_errors', '0');
error_reporting(E_ALL);

$logDir = __DIR__ . '/../var/log';
@mkdir($logDir, 0755, true);
$contactLog = $logDir . '/contact.log';
$mailLog = $logDir . '/mail.log';

function logError($msg) {
  global $contactLog;
  $entry = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
  @file_put_contents($contactLog, $entry, FILE_APPEND | LOCK_EX);
}

// Try common autoload locations
$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../project/vendor/autoload.php'
];
$found = false;
foreach ($autoloadPaths as $p) {
    if (file_exists($p)) {
        require_once $p;
        $found = true;
        break;
    }
}
if (!$found) {
    logError('Autoload not found. Tried: ' . implode(', ', $autoloadPaths));
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Mailer autoload not found.']);
    exit;
}

if (!class_exists('AdvancedMailer\\Mail')) {
  logError('AdvancedMailer\\Mail class not found after including autoload.');
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'AdvancedMailer\\Mail class not found.']);
  exit;
}

// Minimal PSR-3 compatible file logger
class FileLogger implements \AdvancedMailer\LoggerInterface
{
    private string $file;
    public function __construct(string $file) { $this->file = $file; }
    private function write(string $level, string $message, array $context = []): void {
        $ctx = $context ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $line = sprintf("[%s] %s: %s%s\n", date('Y-m-d H:i:s'), strtoupper($level), $message, $ctx);
        @file_put_contents($this->file, $line, FILE_APPEND | LOCK_EX);
    }
    public function emergency(string $message, array $context = []): void { $this->write('emergency', $message, $context); }
    public function alert(string $message, array $context = []): void     { $this->write('alert', $message, $context); }
    public function critical(string $message, array $context = []): void  { $this->write('critical', $message, $context); }
    public function error(string $message, array $context = []): void     { $this->write('error', $message, $context); }
    public function warning(string $message, array $context = []): void   { $this->write('warning', $message, $context); }
    public function notice(string $message, array $context = []): void    { $this->write('notice', $message, $context); }
    public function info(string $message, array $context = []): void      { $this->write('info', $message, $context); }
    public function debug(string $message, array $context = []): void     { $this->write('debug', $message, $context); }
    public function log($level, string $message, array $context = []): void { $this->write((string)$level, $message, $context); }
}

// Load configuration
$configPathCandidates = [
  __DIR__ . '/../config/mail.php',
  __DIR__ . '/config/mail.php'
];
$config = null;
foreach ($configPathCandidates as $cfg) {
  if (file_exists($cfg)) {
    $config = require $cfg;
    break;
  }
}
if (!$config || !is_array($config)) {
  logError('Mail config not found. Tried: ' . implode(', ', $configPathCandidates));
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Mail config not found']);
  exit;
}

// Defaults
$config['smtp_timeout'] = $config['smtp_timeout'] ?? 60;
$config['smtp_encryption'] = $config['smtp_encryption'] ?? 'tls';
$config['smtp_port'] = $config['smtp_port'] ?? ($config['smtp_encryption'] === 'ssl' ? 465 : 2525);

// Read input
$name = trim((string)($_POST['name'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$company = trim((string)($_POST['company'] ?? ''));
$message = trim((string)($_POST['message'] ?? ''));
$honeypot = trim((string)($_POST['website'] ?? ''));

if ($honeypot !== '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Spam detected']);
  exit;
}

if ($name === '' || $email === '' || $message === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Missing required fields']);
  exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Invalid email']);
  exit;
}

try {
  $mail = new \AdvancedMailer\Mail($config);

  @mkdir($logDir, 0755, true);

  // By default we do NOT enable verbose file logging in examples to avoid large logs
  // To enable file logging set 'enable_file_logging' => true in config/mail.php
  if (!empty($config['enable_file_logging'])) {
    $fileLogger = new FileLogger($mailLog);
    $mail->setLogger($fileLogger);
  }

  $fromAddress = $config['from_address'] ?? ('no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
  $fromName = $config['from_name'] ?? 'Website';
  $toAddress = $config['to_address'] ?? null;
  $toName = $config['to_name'] ?? $toAddress;

  if (!$toAddress) {
    logError('Configuration missing to_address in config/mail.php');
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Mail destination not configured']);
    exit;
  }

  $mail->setFrom($fromAddress, $fromName);
  $mail->addAddress($toAddress, $toName);
  $mail->setSubject('Contact from portfolio: ' . ($company ?: 'No company'));
  $body = "<p><strong>Name:</strong> " . htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>";
  $body .= "<p><strong>Email:</strong> " . htmlspecialchars($email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>";
  $body .= "<p><strong>Company:</strong> " . htmlspecialchars($company, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>";
  $body .= "<p><strong>Message:</strong><br>" . nl2br(htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . "</p>";
  $mail->setHtmlBody($body);

  if (method_exists($mail, 'setReplyTo')) {
    try { $mail->setReplyTo($email, $name); } catch (\Throwable $e) { /* ignore */ }
  }

  $sent = $mail->send();
  if ($sent) {
    echo json_encode(['ok' => true]);
  } else {
    $msg = 'Mail::send() returned false.';
    logError($msg);
    @file_put_contents($mailLog, "[" . date('Y-m-d H:i:s') . "] ERROR: " . $msg . PHP_EOL, FILE_APPEND | LOCK_EX);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to send']);
  }
} catch (\Throwable $e) {
  $err = 'Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
  logError($err . "\nTrace:\n" . $e->getTraceAsString());
  logError('Request: ' . json_encode(['name' => $name, 'email' => $email, 'company' => $company]));
  @file_put_contents($mailLog, "[" . date('Y-m-d H:i:s') . "] EXCEPTION: " . $e->getMessage() . PHP_EOL . $e->getTraceAsString() . PHP_EOL, FILE_APPEND | LOCK_EX);

  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Internal server error']);
}
```

With Composer (recommended for development and tests):
```bash
composer install
```

When running tests or using the library via Composer, require the autoloader:
```php
require 'vendor/autoload.php';
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

Logging and troubleshooting

- Place logs in `var/log/` (example `var/log/contact.log` and `var/log/mail.log`). Ensure the web user can write there (permissions 0755 for dirs, 0644 for files typically).
- If you see errors about missing dependencies (e.g., `myclabs/deep-copy`) it means `vendor/` contains dev dependencies — replace `vendor/` with the production `vendor/` from the release archive created with `composer install --no-dev --optimize-autoloader`.
- If `proc_open` or CLI is unavailable, `Mail::sendAsync()` falls back to synchronous send.
- Reduce verbose SMTP logging before publishing a release by using `NullLogger` or configuring logger level to `error`/`warning`.
