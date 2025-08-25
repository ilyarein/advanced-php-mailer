<?php

namespace AdvancedMailer\Transport;

use AdvancedMailer\Exception\MailException;

/**
 * Exception thrown by transport implementations
 */
class TransportException extends MailException
{
    protected array $context = [];

    public function __construct(
        string $message = "",
        int $code = 0,
        ?\Throwable $previous = null,
        array $context = []
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    /**
     * Get additional context data
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Set additional context data
     */
    public function setContext(array $context): self
    {
        $this->context = $context;
        return $this;
    }
}
