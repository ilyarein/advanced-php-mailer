<?php
namespace AdvancedMailer\Tests;

require_once __DIR__ . '/Compat/PhpUnitPolyfill.php';
use PHPUnit\Framework\TestCase;
use AdvancedMailer\Template\TemplateEngine;

class TemplateEngineTest extends TestCase
{
    public function testRenderSimplePlaceholder()
    {
        $engine = new TemplateEngine(__DIR__ . '/../templates');
        $result = $engine->renderString('Hello, {{name}}', ['name' => 'John']);
        $this->assertStringContainsString('John', $result);
    }

    public function testSaveAndLoadTemplate()
    {
        $engine = new TemplateEngine(sys_get_temp_dir());
        $name = 'am_test_template_' . uniqid();
        $engine->saveTemplate($name, 'Hi {{name}}');
        $content = $engine->render($name, ['name' => 'Alice']);
        $this->assertStringContainsString('Alice', $content);
    }
}


