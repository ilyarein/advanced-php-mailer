<?php

namespace AdvancedMailer\Template;

use AdvancedMailer\Exception\MailException;

/**
 * Simple template engine for email templates
 */
class TemplateEngine
{
    private string $templateDir;
    private array $globalVars = [];

    public function __construct(?string $templateDir = null)
    {
        $this->templateDir = $templateDir ?: __DIR__ . '/../../templates';
        $this->ensureTemplateDirExists();
    }

    /**
     * Set template directory
     */
    public function setTemplateDir(string $dir): self
    {
        $this->templateDir = $dir;
        $this->ensureTemplateDirExists();
        return $this;
    }

    /**
     * Set global variables available in all templates
     */
    public function setGlobalVars(array $vars): self
    {
        $this->globalVars = $vars;
        return $this;
    }

    /**
     * Add a global variable
     */
    public function addGlobalVar(string $name, $value): self
    {
        $this->globalVars[$name] = $value;
        return $this;
    }

    /**
     * Render a template with data
     */
    public function render(string $template, array $data = []): string
    {
        $templatePath = $this->getTemplatePath($template);

        if (!file_exists($templatePath)) {
            throw new MailException("Template not found: {$template}");
        }

        $templateContent = file_get_contents($templatePath);

        // Merge global vars with template data
        $vars = array_merge($this->globalVars, $data);

        return $this->processTemplate($templateContent, $vars);
    }

    /**
     * Render template from string
     */
    public function renderString(string $templateString, array $data = []): string
    {
        $vars = array_merge($this->globalVars, $data);
        return $this->processTemplate($templateString, $vars);
    }

    /**
     * Get full path to template
     */
    private function getTemplatePath(string $template): string
    {
        // Add .html extension if not provided
        if (!str_contains($template, '.')) {
            $template .= '.html';
        }

        return $this->templateDir . '/' . $template;
    }

    /**
     * Process template with variables
     */
    private function processTemplate(string $template, array $vars): string
    {
        // Replace simple variables {{variable}}
        $template = preg_replace_callback(
            '/\{\{(\w+)\}\}/',
            function($matches) use ($vars) {
                $key = $matches[1];
                return isset($vars[$key]) ? htmlspecialchars($vars[$key], ENT_QUOTES) : '';
            },
            $template
        );

        // Replace raw variables {!variable!} (no escaping)
        $template = preg_replace_callback(
            '/\{!(\w+)!\}/',
            function($matches) use ($vars) {
                $key = $matches[1];
                return isset($vars[$key]) ? $vars[$key] : '';
            },
            $template
        );

        // Process conditionals {if variable}...{else}...{endif}
        $template = $this->processConditionals($template, $vars);

        // Process loops {foreach items as item}...{endforeach}
        $template = $this->processLoops($template, $vars);

        return $template;
    }

    /**
     * Process conditional statements
     */
    private function processConditionals(string $template, array $vars): string
    {
        return preg_replace_callback(
            '/\{if\s+(\w+)\}(.*?)(?:\{else\}(.*?))?\{endif\}/s',
            function($matches) use ($vars) {
                $condition = $matches[1];
                $ifContent = $matches[2];
                $elseContent = $matches[3] ?? '';

                if (isset($vars[$condition]) && $vars[$condition]) {
                    return $this->processTemplate($ifContent, $vars);
                } elseif (!empty($elseContent)) {
                    return $this->processTemplate($elseContent, $vars);
                }

                return '';
            },
            $template
        );
    }

    /**
     * Process foreach loops
     */
    private function processLoops(string $template, array $vars): string
    {
        return preg_replace_callback(
            '/\{foreach\s+(\w+)\s+as\s+(\w+)\}(.*?)\{endforeach\}/s',
            function($matches) use ($vars) {
                $arrayName = $matches[1];
                $itemName = $matches[2];
                $loopContent = $matches[3];

                if (!isset($vars[$arrayName]) || !is_array($vars[$arrayName])) {
                    return '';
                }

                $result = '';
                foreach ($vars[$arrayName] as $item) {
                    $loopVars = $vars;
                    $loopVars[$itemName] = $item;
                    $result .= $this->processTemplate($loopContent, $loopVars);
                }

                return $result;
            },
            $template
        );
    }

    /**
     * Ensure template directory exists
     */
    private function ensureTemplateDirExists(): void
    {
        if (!is_dir($this->templateDir)) {
            mkdir($this->templateDir, 0755, true);
        }
    }

    /**
     * Get list of available templates
     */
    public function getAvailableTemplates(): array
    {
        $templates = [];

        if (!is_dir($this->templateDir)) {
            return $templates;
        }

        $files = glob($this->templateDir . '/*.{html,txt}', GLOB_BRACE);
        foreach ($files as $file) {
            $templates[] = basename($file);
        }

        return $templates;
    }

    /**
     * Save a template
     */
    public function saveTemplate(string $name, string $content): void
    {
        $templatePath = $this->getTemplatePath($name);
        file_put_contents($templatePath, $content);
    }

    /**
     * Delete a template
     */
    public function deleteTemplate(string $name): bool
    {
        $templatePath = $this->getTemplatePath($name);
        if (file_exists($templatePath)) {
            return unlink($templatePath);
        }
        return false;
    }
}
