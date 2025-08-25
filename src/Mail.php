<?php

namespace AdvancedMailer;

use AdvancedMailer\Transport\TransportInterface;
use AdvancedMailer\Transport\SmtpTransport;
use AdvancedMailer\Validation\EmailValidator;
use AdvancedMailer\Exception\MailException;
use AdvancedMailer\Template\TemplateEngine;
use AdvancedMailer\LoggerInterface;
use AdvancedMailer\NullLogger;

/**
 * Main Mail class - Advanced alternative to PHPMailer
 */
class Mail
{
    private array $recipients = [];
    private array $cc = [];
    private array $bcc = [];
    private string $subject = '';
    private string $body = '';
    private string $altBody = '';
    private array $attachments = [];
    private array $embeddedImages = [];
    private string $fromEmail = '';
    private string $fromName = '';
    private string $replyToEmail = '';
    private string $replyToName = '';
    private string $messageId = '';
    private array $headers = [];
    private int $priority = 3; // 1 = High, 3 = Normal, 5 = Low
    private bool $isHtml = false;
    private string $charset = 'UTF-8';
    private TransportInterface $transport;
    private EmailValidator $validator;
    private TemplateEngine $templateEngine;
    private \AdvancedMailer\LoggerInterface $logger;
    private array $config = [];

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'smtp_host' => '',
            'smtp_port' => 587,
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_encryption' => 'tls',
            'smtp_timeout' => 30,
            'max_attachment_size' => 25 * 1024 * 1024, // 25MB
            'allowed_extensions' => ['pdf', 'doc', 'docx', 'txt', 'jpg', 'jpeg', 'png', 'gif'],
        ], $config);

        $this->validator = new EmailValidator();
        $this->templateEngine = new TemplateEngine();
        $this->logger = new NullLogger();
        $this->transport = new SmtpTransport($this->config, $this->logger);
        $this->messageId = $this->generateMessageId();
    }

    /**
     * Set the sender
     */
    public function setFrom(string $email, string $name = ''): self
    {
        if (!$this->validator->isValid($email)) {
            $this->logger->error('Некорректный email отправителя', [
                'email' => $email,
                'validation_result' => false
            ]);
            throw new MailException("Invalid sender email address: {$email}");
        }

        $this->logger->debug('Отправитель установлен', [
            'email' => $email,
            'name' => $name,
            'validation_passed' => true
        ]);

        $this->fromEmail = $email;
        $this->fromName = $this->sanitizeName($name);
        return $this;
    }

    /**
     * Add a recipient
     */
    public function addAddress(string $email, string $name = ''): self
    {
        if (!$this->validator->isValid($email)) {
            throw new MailException("Invalid recipient email address: {$email}");
        }

        $this->recipients[] = [
            'email' => $email,
            'name' => $this->sanitizeName($name)
        ];
        return $this;
    }

    /**
     * Add CC recipient
     */
    public function addCC(string $email, string $name = ''): self
    {
        if (!$this->validator->isValid($email)) {
            throw new MailException("Invalid CC email address: {$email}");
        }

        $this->cc[] = [
            'email' => $email,
            'name' => $this->sanitizeName($name)
        ];
        return $this;
    }

    /**
     * Add BCC recipient
     */
    public function addBCC(string $email, string $name = ''): self
    {
        if (!$this->validator->isValid($email)) {
            throw new MailException("Invalid BCC email address: {$email}");
        }

        $this->bcc[] = [
            'email' => $email,
            'name' => $this->sanitizeName($name)
        ];
        return $this;
    }

    /**
     * Set reply-to address
     */
    public function setReplyTo(string $email, string $name = ''): self
    {
        if (!$this->validator->isValid($email)) {
            throw new MailException("Invalid reply-to email address: {$email}");
        }

        $this->replyToEmail = $email;
        $this->replyToName = $this->sanitizeName($name);
        return $this;
    }

    /**
     * Set email subject
     */
    public function setSubject(string $subject): self
    {
        $this->subject = $this->sanitizeSubject($subject);
        return $this;
    }

    /**
     * Set HTML body
     */
    public function setHtmlBody(string $body): self
    {
        $this->body = $body;
        $this->isHtml = true;
        return $this;
    }

    /**
     * Set plain text body
     */
    public function setTextBody(string $body): self
    {
        $this->body = $body;
        $this->isHtml = false;
        return $this;
    }

    /**
     * Set alternative plain text body for HTML emails
     */
    public function setAltBody(string $altBody): self
    {
        $this->altBody = $altBody;
        return $this;
    }

    /**
     * Get the email body
     */
    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * Get the HTML body
     */
    public function getHtmlBody(): string
    {
        return $this->isHtml ? $this->body : '';
    }

    /**
     * Get the plain text body
     */
    public function getTextBody(): string
    {
        return !$this->isHtml ? $this->body : '';
    }

    /**
     * Get the alternative body
     */
    public function getAltBody(): string
    {
        return $this->altBody;
    }

    /**
     * Add attachment
     */
    public function addAttachment(string $path, string $name = '', string $mimeType = ''): self
    {
        if (!file_exists($path)) {
            $this->logger->error('Файл вложения не найден', [
                'path' => $path,
                'exists' => file_exists($path),
                'readable' => is_readable($path)
            ]);
            throw new MailException("Attachment file not found: {$path}");
        }

        $fileSize = filesize($path);
        if ($fileSize > $this->config['max_attachment_size']) {
            $this->logger->warning('Размер вложения превышает лимит', [
                'path' => $path,
                'size' => $fileSize,
                'max_size' => $this->config['max_attachment_size']
            ]);
            throw new MailException("Attachment too large: {$path} ({$fileSize} bytes)");
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($extension, $this->config['allowed_extensions'])) {
            $this->logger->error('Недопустимый тип файла вложения', [
                'path' => $path,
                'extension' => $extension,
                'allowed' => $this->config['allowed_extensions']
            ]);
            throw new MailException("Attachment type not allowed: {$extension}");
        }

        if (empty($name)) {
            $name = basename($path);
        }

        $this->attachments[] = [
            'path' => $path,
            'name' => $name,
            'mime_type' => $mimeType ?: $this->getMimeType($path),
            'size' => $fileSize
        ];

        $this->logger->debug('Вложение добавлено', [
            'name' => $name,
            'size' => $fileSize,
            'mime_type' => $this->attachments[count($this->attachments)-1]['mime_type']
        ]);

        return $this;
    }

    /**
     * Add embedded image
     */
    public function addEmbeddedImage(string $path, string $cid, string $name = ''): self
    {
        if (!file_exists($path)) {
            throw new MailException("Embedded image not found: {$path}");
        }

        $this->embeddedImages[] = [
            'path' => $path,
            'cid' => $cid,
            'name' => $name ?: basename($path),
            'mime_type' => $this->getMimeType($path)
        ];

        return $this;
    }

    /**
     * Set email priority
     */
    public function setPriority(int $priority): self
    {
        if (!in_array($priority, [1, 2, 3, 4, 5])) {
            throw new MailException("Invalid priority: {$priority}. Must be 1-5");
        }

        $this->priority = $priority;
        return $this;
    }

    /**
     * Add custom header
     */
    public function addHeader(string $name, string $value): self
    {
        $safeName = $this->sanitizeHeaderName($name);
        $safeValue = $this->sanitizeHeaderValue($value);
        if ($safeName === '') {
            throw new MailException('Invalid header name');
        }
        $this->headers[$safeName] = $safeValue;
        return $this;
    }

    /**
     * Set logger
     */
    public function setLogger(\AdvancedMailer\LoggerInterface $logger): self
    {
        $this->logger = $logger;
        $this->transport->setLogger($logger);
        return $this;
    }

    /**
     * Set transport
     */
    public function setTransport(TransportInterface $transport): self
    {
        $this->transport = $transport;
        return $this;
    }

    /**
     * Send the email
     */
    public function send(): bool
    {
        $this->validateBeforeSend();

        try {
            $this->logger->info('Отправка письма начата', [
                'to_count' => count($this->recipients),
                'cc_count' => count($this->cc),
                'bcc_count' => count($this->bcc),
                'subject' => $this->subject,
                'has_attachments' => !empty($this->attachments)
            ]);

            $result = $this->transport->send($this->buildMessage());

            $this->logger->info('Письмо успешно отправлено', [
                'message_id' => $this->messageId,
                'transport' => $this->transport->getName()
            ]);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('Ошибка отправки письма', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'subject' => $this->subject,
                'recipient_count' => count($this->recipients),
                'transport' => $this->transport->getName(),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            throw new MailException("Failed to send email: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Send asynchronously (returns promise-like object)
     */
    public function sendAsync(): Promise
    {
        $this->validateBeforeSend();

        // Try to perform non-blocking background send via a helper script.
        // This is not true non-blocking IO; it spawns a separate PHP process to send.
        // If `proc_open` is unavailable, fallback to promise that executes synchronously.

        $tmpFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'am_msg_' . uniqid() . '.json';
        $message = $this->buildMessage();
        $data = ['config' => $this->config, 'message' => $message];
        file_put_contents($tmpFile, json_encode($data));

        $promise = new Promise(function($resolve, $reject) use ($tmpFile) {
            // Prefer proc_open for true background process
            if (function_exists('proc_open')) {
                $php = PHP_BINARY;
                $cmd = escapeshellarg($php) . ' ' . escapeshellarg(__DIR__ . '/../bin/send_async.php') . ' ' . escapeshellarg($tmpFile);
                $descriptors = [
                    0 => ['pipe', 'r'],
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w']
                ];
                $proc = @proc_open($cmd, $descriptors, $pipes);
                if (is_resource($proc)) {
                    // Close pipes and return immediately
                    foreach ($pipes as $p) {
                        @fclose($p);
                    }
                    @proc_close($proc);
                    $resolve(true);
                    return;
                }
            }

            // Fallback: synchronous send
            try {
                $result = $this->send();
                @unlink($tmpFile);
                $resolve($result);
            } catch (\Exception $e) {
                @unlink($tmpFile);
                $reject($e);
            }
        });

        return $promise;
    }

    /**
     * Use template
     */
    public function useTemplate(string $template, array $data = []): self
    {
        $this->body = $this->templateEngine->render($template, $data);
        $this->isHtml = true;
        return $this;
    }

    /**
     * Validate email before sending
     */
    private function validateBeforeSend(): void
    {
        if (empty($this->fromEmail)) {
            throw new MailException("Sender email address is required");
        }

        if (empty($this->recipients)) {
            throw new MailException("At least one recipient is required");
        }

        if (empty($this->subject)) {
            throw new MailException("Email subject is required");
        }

        if (empty($this->body)) {
            throw new MailException("Email body is required");
        }
    }

    /**
     * Build the complete email message
     */
    private function buildMessage(): array
    {
        return [
            'from' => [
                'email' => $this->fromEmail,
                'name' => $this->fromName
            ],
            'recipients' => $this->recipients,
            'cc' => $this->cc,
            'bcc' => $this->bcc,
            'reply_to' => [
                'email' => $this->replyToEmail,
                'name' => $this->replyToName
            ],
            'subject' => $this->subject,
            'body' => $this->body,
            'alt_body' => $this->altBody,
            'is_html' => $this->isHtml,
            'attachments' => $this->attachments,
            'embedded_images' => $this->embeddedImages,
            'headers' => $this->headers,
            'priority' => $this->priority,
            'charset' => $this->charset,
            'message_id' => $this->messageId
        ];
    }

    /**
     * Generate unique message ID
     */
    private function generateMessageId(): string
    {
        return uniqid('', true) . '@' . gethostname();
    }

    /**
     * Sanitize name field
     */
    private function sanitizeName(string $name): string
    {
        // Replace control characters with a single space, collapse whitespace and limit length
        $clean = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $name);
        // Normalize all whitespace (tabs, newlines, multiple spaces) to a single space
        $clean = preg_replace('/\s+/u', ' ', $clean);
        $clean = trim($clean);
        if (mb_strlen($clean) > 255) {
            $clean = mb_substr($clean, 0, 255);
        }
        return $clean;
    }

    /**
     * Sanitize subject field
     */
    private function sanitizeSubject(string $subject): string
    {
        // Remove CR/LF and control characters; limit subject length to 998 (RFC 5322 line length)
        $clean = preg_replace('/[\r\n\x00-\x1F\x7F]+/u', ' ', $subject);
        $clean = trim(preg_replace('/\s+/u', ' ', $clean));
        if (mb_strlen($clean) > 998) {
            $clean = mb_substr($clean, 0, 998);
        }
        return $clean;
    }

    /**
     * Sanitize header name and value to prevent header injection
     */
    private function sanitizeHeaderName(string $name): string
    {
        // Only allow A-Z, a-z, 0-9, hyphen
        $clean = preg_replace('/[^A-Za-z0-9\-]/', '', $name);
        return substr($clean, 0, 78);
    }

    private function sanitizeHeaderValue(string $value): string
    {
        // Remove control chars and limit length
        $clean = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value);
        $clean = trim(preg_replace('/\s+/u', ' ', $clean));
        if (mb_strlen($clean) > 1000) {
            $clean = mb_substr($clean, 0, 1000);
        }
        return $clean;
    }

    /**
     * Get MIME type from file
     */
    private function getMimeType(string $path): string
    {
        // Prefer fileinfo extension if available
        if (function_exists('finfo_open')) {
            try {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                if ($finfo !== false) {
                    $mimeType = finfo_file($finfo, $path);
                    finfo_close($finfo);
                    if (!empty($mimeType)) {
                        return $mimeType;
                    }
                }
            } catch (\Throwable $e) {
                // log and fallback
                $this->logger->warning('finfo failed to determine MIME type', ['path' => $path, 'error' => $e->getMessage()]);
            }
        }

        // Fallback to mime_content_type if available
        if (function_exists('mime_content_type')) {
            $mime = @mime_content_type($path);
            if ($mime !== false && $mime !== null) {
                return $mime;
            }
        }

        // Final fallback
        $this->logger->warning('Could not determine MIME type, using application/octet-stream', ['path' => $path]);
        return 'application/octet-stream';
    }
}
