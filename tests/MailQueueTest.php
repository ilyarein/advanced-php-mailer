<?php
namespace AdvancedMailer\Tests;

require_once __DIR__ . '/Compat/PhpUnitPolyfill.php';
use PHPUnit\Framework\TestCase;
use AdvancedMailer\Queue\MailQueue;
use AdvancedMailer\Transport\SmtpTransport;
use AdvancedMailer\Mail;

class MailQueueTest extends TestCase
{
    public function testQueueAddAndProcess()
    {
        $transport = new SmtpTransport(['smtp_host' => 'localhost']);
        $queue = new MailQueue($transport);

        $mail = new Mail(['smtp_host' => 'localhost']);
        $mail->setFrom('from@example.com', 'Sender');
        $mail->addAddress('to@example.com', 'Recipient');
        $mail->setSubject('Queue test');
        $mail->setHtmlBody('Test');

        $queue->add($mail, 3);
        $this->assertEquals(1, $queue->getSize());

        // Process with maxAttempts small so it uses transport->send (no real SMTP)
        $results = $queue->process(1);
        $this->assertArrayHasKey('processed', $results);
    }
}


