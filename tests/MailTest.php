<?php

namespace AdvancedMailer\Tests;

use AdvancedMailer\Mail;
use AdvancedMailer\Validation\EmailValidator;
use AdvancedMailer\Exception\MailException;

// Простая тестовая заглушка без PHPUnit зависимостей
class SimpleTestCase {
    protected function assertInstanceOf(string $class, $object): void {
        if (!$object instanceof $class) {
            throw new \Exception("Object is not instance of $class");
        }
        echo "✓ assertInstanceOf passed\n";
    }

    protected function assertEquals($expected, $actual): void {
        if ($expected !== $actual) {
            throw new \Exception("Expected $expected, got $actual");
        }
        echo "✓ assertEquals passed\n";
    }

    protected function assertCount(int $expected, array $array): void {
        if (count($array) !== $expected) {
            throw new \Exception("Expected count $expected, got " . count($array));
        }
        echo "✓ assertCount passed\n";
    }

    protected function assertTrue($value): void {
        if (!$value) {
            throw new \Exception("Expected true, got false");
        }
        echo "✓ assertTrue passed\n";
    }

    protected function assertFalse($value): void {
        if ($value) {
            throw new \Exception("Expected false, got true");
        }
        echo "✓ assertFalse passed\n";
    }

    protected function expectException(string $exceptionClass): void {
        $this->expectedException = $exceptionClass;
        echo "✓ Exception expected: $exceptionClass\n";
    }

    protected function expectExceptionMessage(string $message): void {
        $this->expectedMessage = $message;
        echo "✓ Exception message expected: $message\n";
    }

    private $expectedException = null;
    private $expectedMessage = null;
}

class MailTest extends SimpleTestCase
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

        // Проверка через отражение, так как свойства приватные
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

        // Тест валидных email
        $this->assertTrue($validator->isValidQuick('valid@example.com'));
        $this->assertTrue($validator->isValidQuick('user.name@domain.co.uk'));
        $this->assertTrue($validator->isValidQuick('test+tag@gmail.com'));

        // Тест невалидных email
        $this->assertFalse($validator->isValidQuick('invalid-email'));
        $this->assertFalse($validator->isValidQuick(''));
        $this->assertFalse($validator->isValidQuick('test@'));
        $this->assertFalse($validator->isValidQuick('@domain.com'));

        // Тест санитизации
        $this->assertEquals('test@example.com', $validator->sanitize('  TEST@EXAMPLE.COM  '));
        $this->assertEquals('user@domain.com', $validator->sanitize('user@domain.com'));

        // Тест извлечения домена
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

    // Простой способ запуска тестов без PHPUnit
    public function runTests(): void {
        echo "=== Запуск тестов Advanced Mailer ===\n\n";

        $testMethods = array_filter(get_class_methods($this), function($method) {
            return str_starts_with($method, 'test');
        });

        foreach ($testMethods as $method) {
            echo "Тест: $method\n";
            try {
                $this->setUp();
                $this->$method();
                echo "✅ Пройден\n\n";
            } catch (\Exception $e) {
                echo "❌ Ошибка: " . $e->getMessage() . "\n\n";
            }
        }
    }
}

// Запуск тестов
if (basename(__FILE__) === basename($_SERVER['PHP_SELF'] ?? __FILE__)) {
    $test = new MailTest();
    $test->runTests();
}
