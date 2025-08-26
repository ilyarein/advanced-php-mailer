<?php

namespace AdvancedMailer\Transport;

use AdvancedMailer\LoggerInterface;
use AdvancedMailer\NullLogger;

/**
 * SMTP transport implementation
 */
class SmtpTransport implements TransportInterface
{
    private array $config;
    private LoggerInterface $logger;
    private $socket = null;
    private bool $connected = false;
    private bool $smtpUtf8Supported = false;
    private bool $serverSupports8bit = false;

    public function __construct(array $config, ?LoggerInterface $logger = null)
    {
        $this->config = array_merge([
            'smtp_host' => 'localhost',
            'smtp_port' => 587,
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_encryption' => 'tls', // tls, ssl, or none
            'smtp_timeout' => 30,
            'smtp_auth' => true,
            'smtp_auth_method' => 'login', // login, plain, cram-md5, xoauth2
            'smtp_oauth_token' => '',
            'allow_smtputf8' => false,
        ], $config);

        $this->logger = $logger ?? new NullLogger();
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function getName(): string
    {
        return 'SMTP';
    }

    public function send(array $message): bool
    {
        try {
            $this->connect();
            $this->logger->debug('After connect() in send()', ['connected' => $this->connected]);

            // Send MAIL FROM
            $mailFromCmd = "MAIL FROM:<{$message['from']['email']}>";
            if (!empty($this->config['allow_smtputf8']) && $this->smtpUtf8Supported) {
                $mailFromCmd .= ' SMTPUTF8';
            }
            $this->sendCommand($mailFromCmd);

            // Send RCPT TO for each recipient
            foreach ($message['recipients'] as $recipient) {
                $rcptCmd = "RCPT TO:<{$recipient['email']}>";
                if (!empty($this->config['allow_smtputf8']) && $this->smtpUtf8Supported) {
                    $rcptCmd .= ' SMTPUTF8';
                }
                $this->sendCommand($rcptCmd);
            }

            // Send CC recipients
            foreach ($message['cc'] as $recipient) {
                $rcptCmd = "RCPT TO:<{$recipient['email']}>";
                if (!empty($this->config['allow_smtputf8']) && $this->smtpUtf8Supported) {
                    $rcptCmd .= ' SMTPUTF8';
                }
                $this->sendCommand($rcptCmd);
            }

            // Send BCC recipients
            foreach ($message['bcc'] as $recipient) {
                $rcptCmd = "RCPT TO:<{$recipient['email']}>";
                if (!empty($this->config['allow_smtputf8']) && $this->smtpUtf8Supported) {
                    $rcptCmd .= ' SMTPUTF8';
                }
                $this->sendCommand($rcptCmd);
            }

            // Send DATA and wait for 354 response
            $this->sendCommand('DATA');
            $response = $this->readResponse();
            if (substr($response, 0, 3) !== '354') {
                throw new TransportException("SMTP server did not accept DATA command: {$response}");
            }

            // Build and send email content (with dot-stuffing and chunked writes)
            $emailContent = $this->buildEmailContent($message);
            $this->sendData($emailContent);

            $this->logger->info('Email sent successfully via SMTP', [
                'to' => count($message['recipients']),
                'subject' => $message['subject']
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('SMTP send failed', [
                'error' => $e->getMessage(),
                'host' => $this->config['smtp_host'],
                'port' => $this->config['smtp_port']
            ]);
            throw new TransportException(
                'SMTP send failed: ' . $e->getMessage(),
                0,
                $e,
                ['host' => $this->config['smtp_host'], 'port' => $this->config['smtp_port']]
            );
        } finally {
            $this->disconnect();
        }
    }

    public function testConnection(): bool
    {
        try {
            $this->connect();
            $this->disconnect();
            return true;
        } catch (\Exception $e) {
            $this->logger->error('SMTP connection test failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    private function connect(): void
    {
        if ($this->connected) {
            return;
        }

        $host = $this->config['smtp_host'];
        $port = $this->config['smtp_port'];
        $encryption = $this->config['smtp_encryption'];
        $timeout = $this->config['smtp_timeout'];

        // Debug: beginning of connect (mask sensitive fields)
        $cfgDebug = $this->config;
        if (isset($cfgDebug['smtp_password'])) {
            $cfgDebug['smtp_password'] = '***';
        }
        if (isset($cfgDebug['dkim_private_key'])) {
            $cfgDebug['dkim_private_key'] = '***';
        }
        $this->logger->debug('SMTP connect starting', ['host' => $host, 'port' => $port, 'encryption' => $encryption, 'timeout' => $timeout, 'config' => $cfgDebug]);

        // Create socket
        $socket = @fsockopen(
            $encryption === 'ssl' ? "ssl://{$host}" : $host,
            $port,
            $errno,
            $errstr,
            $timeout
        );

        if (!$socket) {
            throw new TransportException("Could not connect to SMTP server: {$errstr} ({$errno})");
        }
        $this->socket = $socket;
        stream_set_timeout($this->socket, $timeout);

        $this->logger->debug('SMTP socket created', ['meta' => stream_get_meta_data($this->socket)]);

        // Read greeting
        $response = $this->readResponse();
        if (!$this->isSuccessResponse($response)) {
            throw new TransportException("SMTP server greeting failed: {$response}");
        }

        // Send EHLO (sendCommand already reads the response)
        $response = $this->sendCommand('EHLO ' . gethostname());

        // Detect SMTPUTF8 support
        $this->smtpUtf8Supported = (strpos($response, 'SMTPUTF8') !== false);

        // Try STARTTLS if requested
        if ($encryption === 'tls' && strpos($response, 'STARTTLS') !== false) {
            $this->logger->debug('SMTP initiating STARTTLS');
            $this->sendCommand('STARTTLS');
            $response = $this->readResponse();

            $this->logger->debug('SMTP STARTTLS response', ['response' => $response]);
            if ($this->isSuccessResponse($response)) {
                $ok = @stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $this->logger->debug('stream_socket_enable_crypto result', ['ok' => $ok, 'meta' => stream_get_meta_data($this->socket)]);
                // Re-send EHLO after STARTTLS
                $this->sendCommand('EHLO ' . gethostname());
                $this->readResponse();
            } else {
                $this->logger->warning('STARTTLS was rejected by server', ['response' => $response]);
            }
        }

        // Authenticate if credentials provided or XOAUTH2 token
        $this->logger->debug('SMTP pre-auth check', ['username_set' => !empty($this->config['smtp_username']), 'password_set' => !empty($this->config['smtp_password']), 'oauth_set' => !empty($this->config['smtp_oauth_token'])]);
        if (!empty($this->config['smtp_username']) && (!empty($this->config['smtp_password']) || !empty($this->config['smtp_oauth_token']))) {
            $this->logger->debug('SMTP about to call authenticate() — adding short pause to avoid timing issues');
            // small pause to allow server buffers to settle (diagnostic)
            usleep(100000); // 100ms
            // mark entry to authenticate
            $this->logger->debug('SMTP authenticate entry marker');
            $this->authenticate();
            $this->logger->debug('SMTP authenticate() finished');
        } else {
            $this->logger->debug('SMTP authentication skipped (no credentials)');
        }

        $this->connected = true;
        $this->logger->info('SMTP connection established', ['host' => $host, 'port' => $port, 'socket_meta' => stream_get_meta_data($this->socket)]);
    }

    private function authenticate(): void
    {
        $method = strtolower($this->config['smtp_auth_method'] ?? 'login');
        $this->logger->debug('SMTP authenticate start', ['method' => $method, 'user' => isset($this->config['smtp_username']) ? substr($this->config['smtp_username'], 0, 3) . '***' : null]);

        switch ($method) {
            case 'plain':
                // AUTH PLAIN <base64(\0user\0pass)>
                $auth = "\0" . $this->config['smtp_username'] . "\0" . $this->config['smtp_password'];
                $this->logger->debug('SMTP AUTH PLAIN sending');
                $response = $this->sendCommand('AUTH PLAIN ' . base64_encode($auth));
                $this->logger->debug('SMTP AUTH PLAIN response', ['response' => $response]);
                if (!$this->isSuccessResponse($response)) {
                    throw new TransportException('SMTP authentication failed - PLAIN rejected: ' . $response);
                }
                break;

            case 'cram-md5':
                // AUTH CRAM-MD5 -> server sends base64 challenge
                $this->logger->debug('SMTP AUTH CRAM-MD5 start');
                $response = $this->sendCommand('AUTH CRAM-MD5');
                $this->logger->debug('SMTP AUTH CRAM-MD5 challenge', ['response' => $response]);
                if (substr($response, 0, 3) !== '334') {
                    throw new TransportException('SMTP authentication failed - CRAM-MD5 not supported: ' . $response);
                }
                $challenge = trim(substr($response, 4));
                $challenge = base64_decode($challenge);
                $digest = hash_hmac('md5', $challenge, $this->config['smtp_password']);
                $responseStr = $this->config['smtp_username'] . ' ' . $digest;
                $response = $this->sendCommand(base64_encode($responseStr));
                $this->logger->debug('SMTP AUTH CRAM-MD5 response', ['response' => $response]);
                if (!$this->isSuccessResponse($response)) {
                    throw new TransportException('SMTP authentication failed - CRAM-MD5 rejected: ' . $response);
                }
                break;

            case 'xoauth2':
                // AUTH XOAUTH2 base64('user=' . $user . "\x01auth=Bearer " . $token . "\x01\x01")
                $token = $this->config['smtp_oauth_token'] ?? '';
                if (empty($token)) {
                    throw new TransportException('SMTP XOAUTH2 token not provided');
                }
                $authStr = 'user=' . $this->config['smtp_username'] . "\x01auth=Bearer " . $token . "\x01\x01";
                $this->logger->debug('SMTP AUTH XOAUTH2 sending');
                $response = $this->sendCommand('AUTH XOAUTH2 ' . base64_encode($authStr));
                $this->logger->debug('SMTP AUTH XOAUTH2 response', ['response' => $response]);
                if (!$this->isSuccessResponse($response)) {
                    throw new TransportException('SMTP authentication failed - XOAUTH2 rejected: ' . $response);
                }
                break;

            case 'login':
            default:
                // AUTH LOGIN
                $this->logger->debug('SMTP AUTH LOGIN start');
                $response = $this->sendCommand('AUTH LOGIN');
                $this->logger->debug('SMTP AUTH LOGIN response', ['response' => $response]);
                if (substr($response, 0, 3) !== '334') {
                    throw new TransportException('SMTP authentication failed - AUTH LOGIN not supported: ' . $response);
                }

                // Send username (base64 encoded)
                $this->logger->debug('SMTP AUTH sending username (base64)');
                $response = $this->sendCommand(base64_encode($this->config['smtp_username']));
                $this->logger->debug('SMTP AUTH username response', ['response' => $response]);
                if (substr($response, 0, 3) !== '334') {
                    throw new TransportException('SMTP authentication failed - username rejected: ' . $response);
                }

                // Send password (base64 encoded)
                $this->logger->debug('SMTP AUTH sending password (base64)');
                $response = $this->sendCommand(base64_encode($this->config['smtp_password']));
                $this->logger->debug('SMTP AUTH password response', ['response' => $response]);
                if (!$this->isSuccessResponse($response)) {
                    throw new TransportException('SMTP authentication failed - password rejected: ' . $response);
                }
                break;
        }

        $this->logger->info('SMTP authentication successful', ['method' => $method]);
    }

    private function disconnect(): void
    {
        if ($this->socket && $this->connected) {
            try {
                $this->sendCommand('QUIT');
                $this->readResponse();
            } catch (\Exception $e) {
                // Ignore errors during disconnect
            }

            fclose($this->socket);
            $this->socket = null;
            $this->connected = false;
            $this->logger->info('SMTP connection closed');
        }
    }

    private function sendCommand(string $command): string
    {
        if (!$this->socket) {
            throw new TransportException('SMTP connection not established');
        }

        $this->logger->debug('SMTP command send start', ['command' => $command]);
        $bytes = @fwrite($this->socket, $command . "\r\n");
        $meta = @stream_get_meta_data($this->socket);
        $this->logger->debug('SMTP command written', ['command' => $command, 'bytes' => $bytes, 'meta' => $meta]);

        // For commands other than DATA we must read the immediate response
        $cmdPrefix = strtoupper(substr($command, 0, 4));
        if ($cmdPrefix !== 'DATA') {
            $this->logger->debug('SMTP waiting for response', ['command' => $command]);
            $response = $this->readResponse();
            $this->logger->debug('SMTP response after command', ['command' => $command, 'response' => $response]);
            if (!$this->isSuccessResponse($response)) {
                throw new TransportException("SMTP command failed: {$command} -> {$response}");
            }
            return $response;
        }

        return '';
    }

    /**
     * Send DATA content to SMTP server. Performs dot-stuffing and chunked writes.
     * Expects final server response (e.g. 250) after terminating the data with CRLF.CRLF
     */
    private function sendData(string $data): void
    {
        if (!$this->socket) {
            throw new TransportException('SMTP connection not established');
        }

        // Normalize line endings to CRLF
        $data = preg_replace("/\r\n|\n|\r/", "\r\n", $data);

        // Dot-stuffing: prefix any line that begins with a dot with an extra dot
        $data = preg_replace('/(^|\r\n)\./', '$1..', $data);

        // Ensure message ends with CRLF before terminating with .\r\n
        if (substr($data, -2) !== "\r\n") {
            $data .= "\r\n";
        }

        // Terminate data with a single dot on a line by itself
        $terminator = ".\r\n";

        $fullData = $data . $terminator;

        // Send in chunks to handle large messages
        $len = strlen($fullData);
        $pos = 0;
        $chunkSize = 8192; // 8 KB chunks
        $start = microtime(true);
        while ($pos < $len) {
            $toWrite = min($chunkSize, $len - $pos);
            $chunk = substr($fullData, $pos, $toWrite);
            $written = @fwrite($this->socket, $chunk);
            $now = microtime(true);
            $this->logger->debug('SMTP chunk written', ['offset' => $pos, 'toWrite' => $toWrite, 'written' => $written, 'ms_since_start' => round(($now - $start) * 1000, 2)]);
            if ($written === false || $written === 0) {
                $meta = stream_get_meta_data($this->socket);
                $this->logger->error('SMTP failed to write chunk', ['meta' => $meta]);
                throw new TransportException('Failed to write SMTP data to socket: ' . ($meta['timed_out'] ? 'timeout' : 'connection error'));
            }
            $pos += $written;
        }

        // Read final response after DATA termination (expect 250)
        $response = $this->readResponse();
        $code = substr($response, 0, 3);
        if ($code !== '250') {
            throw new TransportException("SMTP data send failed: {$response}");
        }
    }

    private function readResponse(): string
    {
        if (!$this->socket) {
            throw new TransportException('SMTP connection not established');
        }

        // Enter readResponse (diagnostic tag will be added by caller via debug)
        $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        $caller = $bt[1] ?? null;
        $callerName = $caller['function'] ?? 'unknown';
        $trace = [];
        foreach ($bt as $b) {
            $trace[] = (isset($b['function']) ? $b['function'] : '') . (isset($b['line']) ? ':' . $b['line'] : '');
        }
        $this->logger->debug('SMTP enter readResponse', ['caller' => $callerName, 'trace' => $trace]);

        $response = '';
        $start = microtime(true);
        while (($line = fgets($this->socket, 515)) !== false) {
            $now = microtime(true);
            $elapsedMs = round(($now - $start) * 1000, 2);
            $this->logger->debug('SMTP raw line received', ['caller' => $callerName, 'line' => rtrim($line, "\r\n"), 'ms_since_start' => $elapsedMs]);
            $response .= $line;
            // Line format: 3-digit-code + (' ' or '-') + text
            if (isset($line[3]) && $line[3] === ' ') {
                break; // end of multi-line response
            }
        }

        if ($response === '') {
            $meta = stream_get_meta_data($this->socket);
            $this->logger->error('SMTP no response', ['caller' => $callerName, 'meta' => $meta, 'trace' => $trace]);
            throw new TransportException('No response from SMTP server');
        }

        $this->logger->debug('SMTP exit readResponse', ['caller' => $callerName, 'response' => trim($response)]);
        return $response;
    }

    private function isSuccessResponse(string $response): bool
    {
        $code = substr($response, 0, 3);
        return in_array($code, ['220', '235', '250', '251', '252', '334', '354']);
    }

    private function buildEmailContent(array $message): string
    {
        $boundary = '----=_NextPart_' . md5(uniqid());
        $content = '';

        // Headers
        $content .= "Message-ID: <{$message['message_id']}>\r\n";
        $content .= "Date: " . date('r') . "\r\n";
        $content .= "From: " . $this->formatAddress($message['from']) . "\r\n";

        // To recipients
        if (!empty($message['recipients'])) {
            $content .= "To: " . implode(', ', array_map([$this, 'formatAddress'], $message['recipients'])) . "\r\n";
        }

        // CC recipients
        if (!empty($message['cc'])) {
            $content .= "Cc: " . implode(', ', array_map([$this, 'formatAddress'], $message['cc'])) . "\r\n";
        }

        // Reply-To
        if (!empty($message['reply_to']['email'])) {
            $content .= "Reply-To: " . $this->formatAddress($message['reply_to']) . "\r\n";
        }

        // Subject
        $content .= "Subject: " . $this->encodeSubject($message['subject']) . "\r\n";

        // Custom headers (encode non-ASCII values)
        foreach ($message['headers'] as $name => $value) {
            $hdrValue = $value;
            if (preg_match('/[^\x00-\x7F]/', $hdrValue)) {
                if (function_exists('mb_encode_mimeheader')) {
                    $hdrValue = mb_encode_mimeheader($hdrValue, 'UTF-8', 'B');
                } else {
                    $hdrValue = '=?UTF-8?B?' . base64_encode($hdrValue) . '?=';
                }
            }
            $content .= "{$name}: {$hdrValue}\r\n";
        }

        // Priority
        $content .= "X-Priority: {$message['priority']}\r\n";

        // MIME headers for attachments or HTML
        if (!empty($message['attachments']) || !empty($message['embedded_images']) || $message['is_html']) {
            $content .= "MIME-Version: 1.0\r\n";
            $content .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";
            $content .= "\r\n";

            // Main content part
            $content .= "--{$boundary}\r\n";
            if ($message['is_html'] && !empty($message['alt_body'])) {
                $content .= "Content-Type: multipart/alternative; boundary=\"alt-{$boundary}\"\r\n";
                $content .= "\r\n";

                // Plain text part
                $plain = $message['alt_body'];
                $plainEnc = $this->prepareTextPart($plain);
                $content .= "--alt-{$boundary}\r\n";
                $content .= "Content-Type: text/plain; charset={$message['charset']}\r\n";
                $content .= "Content-Transfer-Encoding: {$plainEnc['encoding']}\r\n";
                $content .= "\r\n";
                $content .= $plainEnc['content'] . "\r\n";
                $content .= "\r\n";

                // HTML part
                $html = $message['body'];
                $htmlEnc = $this->prepareTextPart($html);
                $content .= "--alt-{$boundary}\r\n";
                $content .= "Content-Type: text/html; charset={$message['charset']}\r\n";
                $content .= "Content-Transfer-Encoding: {$htmlEnc['encoding']}\r\n";
                $content .= "\r\n";
                $content .= $htmlEnc['content'] . "\r\n";
                $content .= "\r\n";
                $content .= "--alt-{$boundary}--\r\n";
            } else {
                $contentType = $message['is_html'] ? 'text/html' : 'text/plain';
                $part = $message['body'];
                $partEnc = $this->prepareTextPart($part);
                $content .= "Content-Type: {$contentType}; charset={$message['charset']}\r\n";
                $content .= "Content-Transfer-Encoding: {$partEnc['encoding']}\r\n";
                $content .= "\r\n";
                $content .= $partEnc['content'] . "\r\n";
            }

            // Embedded images
            foreach ($message['embedded_images'] as $image) {
                $content .= "\r\n--{$boundary}\r\n";
                $content .= "Content-Type: {$image['mime_type']}\r\n";
                $content .= "Content-ID: <{$image['cid']}>\r\n";
                $content .= "Content-Transfer-Encoding: base64\r\n";
                $content .= "Content-Disposition: inline; filename=\"{$image['name']}\"\r\n";
                $content .= "\r\n";
                $content .= chunk_split(base64_encode(file_get_contents($image['path'])));
            }

            // Attachments
            foreach ($message['attachments'] as $attachment) {
                $content .= "\r\n--{$boundary}\r\n";
                $content .= "Content-Type: {$attachment['mime_type']}\r\n";
                $content .= "Content-Transfer-Encoding: base64\r\n";
                $content .= "Content-Disposition: attachment; filename=\"{$attachment['name']}\"\r\n";
                $content .= "\r\n";
                $content .= chunk_split(base64_encode(file_get_contents($attachment['path'])));
            }

            $content .= "\r\n--{$boundary}--";
        } else {
            // Simple email without attachments
            $contentType = $message['is_html'] ? 'text/html' : 'text/plain';
            $content .= "Content-Type: {$contentType}; charset={$message['charset']}\r\n";
            $content .= "\r\n";
            $content .= $message['body'];
        }

        // If S/MIME is configured, attempt to sign the message
        if (!empty($this->config['smime_cert']) && !empty($this->config['smime_key'])) {
            if (function_exists('openssl_pkcs7_sign')) {
                $tmpIn = tempnam(sys_get_temp_dir(), 'am_in_');
                $tmpOut = tempnam(sys_get_temp_dir(), 'am_out_');
                file_put_contents($tmpIn, $content);
                $cert = $this->config['smime_cert'];
                $privkey = [$this->config['smime_key'], $this->config['smime_key_pass'] ?? ''];
                // This will create a signed MIME message in $tmpOut
                $ok = @openssl_pkcs7_sign($tmpIn, $tmpOut, $cert, $privkey, [], PKCS7_DETACHED);
                if ($ok && file_exists($tmpOut)) {
                    $signed = file_get_contents($tmpOut);
                    @unlink($tmpIn);
                    @unlink($tmpOut);
                    return $signed;
                }
                // fallback to unsigned content on failure
                @unlink($tmpIn);
                @unlink($tmpOut);
            }
        }

        // If DKIM configured, compute DKIM-Signature header and prepend
        if (!empty($this->config['dkim_private_key']) && !empty($this->config['dkim_selector']) && !empty($this->config['dkim_domain'])) {
            $dkimHeader = $this->computeDkimHeader($message, $content);
            if ($dkimHeader) {
                $content = $dkimHeader . "\r\n" . $content;
            }
        }

        return $content;
    }

    /**
     * Compute DKIM-Signature header (simple/simple canonicalization)
     */
    private function computeDkimHeader(array $message, string $rawContent): ?string
    {
        if (!function_exists('openssl_sign')) {
            return null;
        }

        $selector = $this->config['dkim_selector'];
        $domain = $this->config['dkim_domain'];
        $identity = $this->config['dkim_identity'] ?? $message['from']['email'];
        $privateKey = $this->config['dkim_private_key'];

        // Extract headers from rawContent (up to first blank line)
        $parts = preg_split("/\r\n\r\n/", $rawContent, 2);
        $rawHeaders = $parts[0] ?? '';
        $body = $parts[1] ?? '';

        // Simple canonicalization: headers as-is with unfolded lines
        $headerNames = ['From','To','Subject','Date','Message-ID','MIME-Version','Content-Type','Content-Transfer-Encoding'];
        $h = '';
        foreach ($headerNames as $name) {
            if (preg_match('/^' . preg_quote($name, '/') . ':\s*(.*)$/mi', $rawHeaders, $m)) {
                $value = preg_replace('/\s+/',' ', trim($m[1]));
                $h .= strtolower($name) . ':' . $value . "\r\n";
            }
        }

        // Body canonicalization (simple): ensure CRLF line endings and no trailing empty lines
        $bodyCanonical = preg_replace("/\r\n|\n|\r/", "\r\n", $body);
        $bodyCanonical = rtrim($bodyCanonical, "\r\n") . "\r\n";
        $bodyHash = base64_encode(hash('sha256', $bodyCanonical, true));

        $dkimFields = [
            'v' => '1',
            'a' => 'rsa-sha256',
            'd' => $domain,
            's' => $selector,
            'c' => 'simple/simple',
            'q' => 'dns/txt',
            't' => time(),
            'h' => implode(':', array_map('strtolower', array_filter($headerNames))),
            'bh' => $bodyHash,
            'i' => $identity
        ];

        // Build DKIM header without signature
        $dkimHeaderBase = '';
        foreach ($dkimFields as $k => $v) {
            $dkimHeaderBase .= $k . '=' . $v . '; ';
        }
        $dkimHeaderBase = rtrim($dkimHeaderBase, '; ');

        // Data to sign: selected headers in order + DKIM-Signature header up to b=
        $dataToSign = $h . 'dkim-signature:' . $dkimHeaderBase . ' b=';

        // Load private key
        $pkey = openssl_pkey_get_private($privateKey);
        if ($pkey === false) {
            return null;
        }

        $signature = '';
        openssl_sign($dataToSign, $signature, $pkey, OPENSSL_ALGO_SHA256);
        // Null the key variable to allow prompt garbage collection in long-running processes
        $pkey = null;

        $b = base64_encode($signature);

        $dkimHeader = 'DKIM-Signature: ' . $dkimHeaderBase . '; b=' . $b;
        return $dkimHeader;
    }

    /**
     * Prepare text part: choose encoding (8bit or quoted-printable/base64) and return prepared content
     */
    private function prepareTextPart(string $text): array
    {
        // If server supports 8bit and text contains non-ASCII, use 8bit
        $containsNonAscii = preg_match('/[^\x00-\x7F]/', $text);
        if ($containsNonAscii && $this->serverSupports8bit) {
            // Normalize line endings
            $content = preg_replace("/\r\n|\n|\r/", "\r\n", $text);
            return ['encoding' => '8bit', 'content' => $content];
        }

        // Otherwise use quoted-printable for text with many non-ascii or long lines
        if ($containsNonAscii || strlen($text) > 998) {
            if (function_exists('quoted_printable_encode')) {
                $qp = quoted_printable_encode($text);
            } else {
                // Basic fallback
                $qp = rtrim(chunk_split(base64_encode($text), 76, "\r\n"), "\r\n");
                return ['encoding' => 'base64', 'content' => $qp];
            }
            return ['encoding' => 'quoted-printable', 'content' => $qp];
        }

        // Default: 7bit
        $content = preg_replace("/\r\n|\n|\r/", "\r\n", $text);
        return ['encoding' => '7bit', 'content' => $content];
    }

    private function formatAddress(array $address): string
    {
        if (empty($address['name'])) {
            return $address['email'];
        }
        return "\"{$address['name']}\" <{$address['email']}>";
    }

    private function encodeSubject(string $subject): string
    {
        if (mb_detect_encoding($subject, 'UTF-8', true)) {
            return '=?UTF-8?B?' . base64_encode($subject) . '?=';
        }
        return $subject;
    }
}
