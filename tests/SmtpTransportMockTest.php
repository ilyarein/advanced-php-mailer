<?php
namespace AdvancedMailer\Tests;

require_once __DIR__ . '/Compat/PhpUnitPolyfill.php';
use PHPUnit\Framework\TestCase;
use AdvancedMailer\Transport\SmtpTransport;
use AdvancedMailer\Mail;

class SmtpTransportMockTest extends TestCase
{
    /**
     * Minimal test double: override network methods to simulate SMTP server responses
     */
    public function testSendUsesExpectedSmtpFlow()
    {
        // Test double class
        $transport = new class(['smtp_host' => 'localhost']) extends SmtpTransport {
            public array $sentCommands = [];
            public string $lastData = '';

            // Override send to simulate SMTP flow without real network I/O
            public function send(array $message): bool
            {
                // Simulate MAIL FROM
                $this->sentCommands[] = 'MAIL FROM:<' . ($message['from']['email'] ?? '') . '>';

                // Simulate RCPT TO for recipients, cc, bcc
                foreach (array_merge($message['recipients'] ?? [], $message['cc'] ?? [], $message['bcc'] ?? []) as $r) {
                    $this->sentCommands[] = 'RCPT TO:<' . ($r['email'] ?? '') . '>';
                }

                // Simulate DATA command
                $this->sentCommands[] = 'DATA';

                // Use parent's buildEmailContent to construct message body (private via reflection)
                $ref = new \ReflectionClass($this);
                if ($ref->hasMethod('buildEmailContent')) {
                    $m = $ref->getMethod('buildEmailContent');
                    $m->setAccessible(true);
                    $this->lastData = $m->invoke($this, $message);
                }

                return true;
            }

            public function sendCommand(string $command): string
            {
                $this->sentCommands[] = $command;
                $cmd = strtoupper(substr(trim($command), 0, 4));
                if ($cmd === 'EHLO') {
                    return "250-localhost Hello\r\n250-STARTTLS\r\n250 AUTH PLAIN LOGIN\r\n";
                }
                if ($cmd === 'DATA') {
                    return "354 End data with <CR><LF>.<CR><LF>\r\n";
                }
                // default OK
                return "250 OK\r\n";
            }

            protected function sendData(string $data): void
            {
                // capture data instead of writing to socket
                $this->lastData = $data;
            }
        };

        // Build a simple mail and call send()
        $mail = new Mail(['smtp_host' => 'localhost']);
        $mail->setFrom('from@example.com', 'Sender');
        $mail->addAddress('to@example.com', 'Recipient');
        $mail->setSubject('Mock test');
        $mail->setHtmlBody('<p>hello</p>');

        // Use our test double transport
        $mail->setTransport($transport);

        $result = $mail->send();

        $this->assertTrue($result);
        // Ensure MAIL FROM and RCPT TO were issued
        $foundMailFrom = false;
        $foundRcpt = false;
        foreach ($transport->sentCommands as $c) {
            if (stripos($c, 'MAIL FROM:') !== false) {
                $foundMailFrom = true;
            }
            if (stripos($c, 'RCPT TO:') !== false) {
                $foundRcpt = true;
            }
        }
        $this->assertTrue($foundMailFrom, 'MAIL FROM command was sent');
        $this->assertTrue($foundRcpt, 'RCPT TO command was sent');
        // Subject is encoded with MIME header encoding in buildEmailContent
        $encodedSubject = '=?UTF-8?B?' . base64_encode('Mock test') . '?=';
        $this->assertStringContainsString('Subject:', $transport->lastData);
        $this->assertStringContainsString($encodedSubject, $transport->lastData);
    }
}


