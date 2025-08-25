<?php

namespace AdvancedMailer\Transport;

use AdvancedMailer\LoggerInterface;
use AdvancedMailer\NullLogger;

/**
 * SendGrid API transport implementation
 */
class SendGridTransport implements TransportInterface
{
    private string $apiKey;
    private LoggerInterface $logger;
    private string $apiUrl = 'https://api.sendgrid.com/v3/mail/send';

    public function __construct(string $apiKey, ?LoggerInterface $logger = null)
    {
        $this->apiKey = $apiKey;
        $this->logger = $logger ?? new NullLogger();
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function getName(): string
    {
        return 'SendGrid';
    }

    public function send(array $message): bool
    {
        $payload = $this->buildSendGridPayload($message);

        try {
            if (!function_exists('curl_init')) {
                throw new TransportException('cURL extension is required for SendGridTransport but not available');
            }

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json'
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);

            curl_close($ch);

            if ($error) {
                throw new TransportException("SendGrid API request failed: {$error}");
            }

            if ($httpCode >= 200 && $httpCode < 300) {
                $this->logger->info('Email sent successfully via SendGrid', [
                    'to' => count($message['recipients']),
                    'subject' => $message['subject']
                ]);
                return true;
            } else {
                $errorMessage = $this->parseSendGridError($response);
                throw new TransportException("SendGrid API error: {$errorMessage}");
            }

        } catch (\Exception $e) {
            $this->logger->error('SendGrid send failed', [
                'error' => $e->getMessage(),
                'subject' => $message['subject']
            ]);
            throw new TransportException(
                'SendGrid send failed: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    public function testConnection(): bool
    {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.sendgrid.com/v3/user/profile');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->apiKey
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            curl_close($ch);

            return $httpCode === 200;

        } catch (\Exception $e) {
            $this->logger->error('SendGrid connection test failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    private function buildSendGridPayload(array $message): array
    {
        $payload = [
            'personalizations' => [],
            'from' => [
                'email' => $message['from']['email']
            ],
            'subject' => $message['subject'],
            'content' => []
        ];

        // Add from name if provided
        if (!empty($message['from']['name'])) {
            $payload['from']['name'] = $message['from']['name'];
        }

        // Add reply-to if provided
        if (!empty($message['reply_to']['email'])) {
            $payload['reply_to'] = [
                'email' => $message['reply_to']['email']
            ];

            if (!empty($message['reply_to']['name'])) {
                $payload['reply_to']['name'] = $message['reply_to']['name'];
            }
        }

        // Build personalization for all recipients
        $personalization = [
            'to' => [],
            'cc' => [],
            'bcc' => []
        ];

        // Add recipients
        foreach ($message['recipients'] as $recipient) {
            $to = ['email' => $recipient['email']];
            if (!empty($recipient['name'])) {
                $to['name'] = $recipient['name'];
            }
            $personalization['to'][] = $to;
        }

        // Add CC
        foreach ($message['cc'] as $recipient) {
            $cc = ['email' => $recipient['email']];
            if (!empty($recipient['name'])) {
                $cc['name'] = $recipient['name'];
            }
            $personalization['cc'][] = $cc;
        }

        // Add BCC
        foreach ($message['bcc'] as $recipient) {
            $bcc = ['email' => $recipient['email']];
            if (!empty($recipient['name'])) {
                $bcc['name'] = $recipient['name'];
            }
            $personalization['bcc'][] = $bcc;
        }

        $payload['personalizations'][] = $personalization;

        // Add content
        if ($message['is_html'] && !empty($message['alt_body'])) {
            // Both HTML and plain text
            $payload['content'] = [
                [
                    'type' => 'text/plain',
                    'value' => $message['alt_body']
                ],
                [
                    'type' => 'text/html',
                    'value' => $message['body']
                ]
            ];
        } elseif ($message['is_html']) {
            // HTML only
            $payload['content'][] = [
                'type' => 'text/html',
                'value' => $message['body']
            ];
        } else {
            // Plain text only
            $payload['content'][] = [
                'type' => 'text/plain',
                'value' => $message['body']
            ];
        }

        // Add attachments
        if (!empty($message['attachments'])) {
            $payload['attachments'] = [];
            foreach ($message['attachments'] as $attachment) {
                $payload['attachments'][] = [
                    'content' => base64_encode(file_get_contents($attachment['path'])),
                    'filename' => $attachment['name'],
                    'type' => $attachment['mime_type'],
                    'disposition' => 'attachment'
                ];
            }
        }

        // Add custom headers
        if (!empty($message['headers'])) {
            $payload['headers'] = $message['headers'];
        }

        return $payload;
    }

    private function parseSendGridError(string $response): string
    {
        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return 'Unknown SendGrid error: ' . $response;
        }

        if (isset($data['errors']) && is_array($data['errors'])) {
            $messages = [];
            foreach ($data['errors'] as $error) {
                $messages[] = $error['message'] ?? 'Unknown error';
            }
            return implode('; ', $messages);
        }

        return $data['message'] ?? 'Unknown SendGrid error';
    }
}
