<?php
namespace AdvancedMailer\Transport;

interface TransportInterface
{
    public function setLogger(\AdvancedMailer\LoggerInterface $logger): void;
    public function getName(): string;
    public function send(array $message): bool;
    public function testConnection(): bool;
}
