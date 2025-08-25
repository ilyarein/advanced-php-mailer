<?php

namespace AdvancedMailer\Validation;

/**
 * Email address validation service - без внешних зависимостей
 */
class EmailValidator
{
    // RFC 5322 Official Standard Email Regex (упрощенная версия)
    private const EMAIL_REGEX = '/^[a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/';

    public function __construct()
    {
        // Нет внешних зависимостей
    }

    /**
     * Validate email address with format and DNS checks
     */
    public function isValid(string $email): bool
    {
        if (empty($email)) {
            return false;
        }

        // Базовая валидация формата
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        // Проверка с помощью регулярного выражения (RFC 5322)
        if (!preg_match(self::EMAIL_REGEX, $email)) {
            return false;
        }

        // Извлечение домена и проверка MX записей
        $domain = $this->getDomain($email);
        if (empty($domain)) {
            return false;
        }

        return $this->hasValidMX($domain);
    }

    /**
     * Quick validation without DNS check
     */
    public function isValidQuick(string $email): bool
    {
        if (empty($email)) {
            return false;
        }

        // Базовая валидация формата
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        // Проверка с помощью регулярного выражения (RFC 5322)
        return (bool) preg_match(self::EMAIL_REGEX, $email);
    }

    /**
     * Sanitize email address
     */
    public function sanitize(string $email): string
    {
        return trim(strtolower($email));
    }

    /**
     * Extract domain from email
     */
    public function getDomain(string $email): string
    {
        $parts = explode('@', $email);
        return $parts[1] ?? '';
    }

    /**
     * Check if domain has valid MX records
     */
    public function hasValidMX(string $domain): bool
    {
        return checkdnsrr($domain, 'MX');
    }

    /**
     * Validate multiple emails at once
     */
    public function validateMultiple(array $emails, bool $strict = false): array
    {
        $results = [];

        foreach ($emails as $email) {
            $method = $strict ? 'isValid' : 'isValidQuick';
            $results[$email] = $this->$method($email);
        }

        return $results;
    }
}
