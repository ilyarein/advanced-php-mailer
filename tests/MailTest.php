<?php

namespace AdvancedMailer\Tests;

use AdvancedMailer\Mail;
use AdvancedMailer\Validation\EmailValidator;
use AdvancedMailer\Exception\MailException;
use PHPUnit\Framework\TestCase;

class MailTest extends TestCase
{
    private array $testConfig;

    protected function setUp(): void
    {
        $this->testConfig = [
            'smtp_host' => 'localhost',
            'smtp_port' => 25,
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_encryption' => 'none'
        ];
    }

    public function testMailCreation(): void
    {
        $mail = new Mail($this->testConfig);
        $this->assertInstanceOf(Mail::class, $mail);
    }

    public function testSetFrom(): void
    {
        $mail = new Mail($this->testConfig);
        $mail->setFrom('sender@example.com', 'Sender Name');

        // Check through reflection, since properties are private
        $reflection = new \ReflectionClass($mail);
        $property = $reflection->getProperty('fromEmail');
        $property->setAccessible(true);

        $this->assertEquals('sender@example.com', $property->getValue($mail));
    }

    public function testAddAddress(): void
    {
        $mail = new Mail($this->testConfig);
        $mail->addAddress('recipient@example.com', 'Recipient Name');

        $reflection = new \ReflectionClass($mail);
        $property = $reflection->getProperty('recipients');
        $property->setAccessible(true);
        $recipients = $property->getValue($mail);

        $this->assertCount(1, $recipients);
        $this->assertEquals('recipient@example.com', $recipients[0]['email']);
        $this->assertEquals('Recipient Name', $recipients[0]['name']);
    }

    public function testInvalidEmailAddress(): void
    {
        $mail = new Mail($this->testConfig);

        $this->expectException(MailException::class);
        $mail->addAddress('invalid-email');
    }

    public function testSetSubject(): void
    {
        $mail = new Mail($this->testConfig);
        $mail->setSubject('Test Subject');

        $reflection = new \ReflectionClass($mail);
        $property = $reflection->getProperty('subject');
        $property->setAccessible(true);

        $this->assertEquals('Test Subject', $property->getValue($mail));
    }

    public function testSetHtmlBody(): void
    {
        $mail = new Mail($this->testConfig);
        $mail->setHtmlBody('<h1>Test</h1>');

        $reflection = new \ReflectionClass($mail);
        $bodyProperty = $reflection->getProperty('body');
        $htmlProperty = $reflection->getProperty('isHtml');

        $bodyProperty->setAccessible(true);
        $htmlProperty->setAccessible(true);

        $this->assertEquals('<h1>Test</h1>', $bodyProperty->getValue($mail));
        $this->assertTrue($htmlProperty->getValue($mail));
    }

    public function testSetTextBody(): void
    {
        $mail = new Mail($this->testConfig);
        $mail->setTextBody('Plain text body');

        $reflection = new \ReflectionClass($mail);
        $bodyProperty = $reflection->getProperty('body');
        $htmlProperty = $reflection->getProperty('isHtml');

        $bodyProperty->setAccessible(true);
        $htmlProperty->setAccessible(true);

        $this->assertEquals('Plain text body', $bodyProperty->getValue($mail));
        $this->assertFalse($htmlProperty->getValue($mail));
    }

    public function testValidationBeforeSend(): void
    {
        $mail = new Mail($this->testConfig);

        $this->expectException(MailException::class);
        $this->expectExceptionMessage('Sender email address is required');

        $mail->send();
    }

    public function testEmailValidator(): void
    {
        $validator = new EmailValidator();

        // Test valid emails
        $this->assertTrue($validator->isValidQuick('valid@example.com'));
        $this->assertTrue($validator->isValidQuick('user.name@domain.co.uk'));
        $this->assertTrue($validator->isValidQuick('test+tag@gmail.com'));

        // Test invalid emails
        $this->assertFalse($validator->isValidQuick('invalid-email'));
        $this->assertFalse($validator->isValidQuick(''));
        $this->assertFalse($validator->isValidQuick('test@'));
        $this->assertFalse($validator->isValidQuick('@domain.com'));

        // Test sanitization
        $this->assertEquals('test@example.com', $validator->sanitize('  TEST@EXAMPLE.COM  '));
        $this->assertEquals('user@domain.com', $validator->sanitize('user@domain.com'));

        // Test domain extraction
        $this->assertEquals('example.com', $validator->getDomain('user@example.com'));
        $this->assertEquals('', $validator->getDomain('invalid-email'));
    }

    public function testSanitization(): void
    {
        $mail = new Mail($this->testConfig);

        $reflection = new \ReflectionClass($mail);
        $method = $reflection->getMethod('sanitizeName');
        $method->setAccessible(true);

        $this->assertEquals('John Doe', $method->invoke($mail, "John\r\nDoe\t"));
        $this->assertEquals('Jane', $method->invoke($mail, ' Jane '));
    }

    public function testPriority(): void
    {
        $mail = new Mail($this->testConfig);

        $mail->setPriority(1);
        $reflection = new \ReflectionClass($mail);
        $property = $reflection->getProperty('priority');
        $property->setAccessible(true);

        $this->assertEquals(1, $property->getValue($mail));

        $this->expectException(MailException::class);
        $mail->setPriority(10);
    }
}
