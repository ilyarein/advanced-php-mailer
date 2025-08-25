<?php
namespace AdvancedMailer\Tests;

use AdvancedMailer\Transport\SmtpTransport;
use AdvancedMailer\Mail;
require_once __DIR__ . '/Compat/PhpUnitPolyfill.php';
use PHPUnit\Framework\TestCase;

class SmtpTransportTest extends TestCase
{
    public function testPrepareTextPartAsciiReturns7bit()
    {
        $transport = new SmtpTransport(['smtp_host' => 'localhost']);
        $ref = new \ReflectionClass($transport);
        $method = $ref->getMethod('prepareTextPart');
        $method->setAccessible(true);

        $result = $method->invoke($transport, "hello world");
        $this->assertIsArray($result);
        $this->assertEquals('7bit', $result['encoding']);
    }

    public function testPrepareTextPartNonAsciiWith8bit()
    {
        $transport = new SmtpTransport(['smtp_host' => 'localhost']);
        $ref = new \ReflectionClass($transport);
        $prop = $ref->getProperty('serverSupports8bit');
        $prop->setAccessible(true);
        $prop->setValue($transport, true);

        $method = $ref->getMethod('prepareTextPart');
        $method->setAccessible(true);

        $result = $method->invoke($transport, "Привет");
        $this->assertEquals('8bit', $result['encoding']);
        $this->assertStringContainsString('Привет', $result['content']);
    }

    public function testPrepareTextPartNonAsciiWithout8bitFallsBack()
    {
        $transport = new SmtpTransport(['smtp_host' => 'localhost']);
        $ref = new \ReflectionClass($transport);
        $prop = $ref->getProperty('serverSupports8bit');
        $prop->setAccessible(true);
        $prop->setValue($transport, false);

        $method = $ref->getMethod('prepareTextPart');
        $method->setAccessible(true);

        $result = $method->invoke($transport, "Привет");
        $this->assertIsArray($result);
        $this->assertContains($result['encoding'], ['quoted-printable', 'base64']);
        $this->assertNotEmpty($result['content']);
    }

    public function testBuildEmailContentIncludesMultipartAlternative()
    {
        $mail = new Mail(['smtp_host' => 'localhost']);
        $mail->setFrom('from@example.com', 'Sender');
        $mail->addAddress('to@example.com', 'Recipient');
        $mail->setSubject('Unit test');
        $mail->setHtmlBody('<h1>Hi</h1>');
        $mail->setAltBody('Hi');

        $transport = new SmtpTransport(['smtp_host' => 'localhost']);

        $refMail = new \ReflectionClass($mail);
        $buildMsg = $refMail->getMethod('buildMessage');
        $buildMsg->setAccessible(true);
        $message = $buildMsg->invoke($mail);

        $refTrans = new \ReflectionClass($transport);
        $buildContent = $refTrans->getMethod('buildEmailContent');
        $buildContent->setAccessible(true);
        $content = $buildContent->invoke($transport, $message);

        $this->assertStringContainsString('Subject: ', $content);
        $this->assertStringContainsString('Content-Type: multipart/alternative', $content);
        $this->assertStringContainsString('Content-Transfer-Encoding', $content);
    }
}


