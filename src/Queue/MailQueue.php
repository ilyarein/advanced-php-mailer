<?php

namespace AdvancedMailer\Queue;

use AdvancedMailer\Mail;
use AdvancedMailer\Transport\TransportInterface;
use AdvancedMailer\Exception\MailException;
use AdvancedMailer\LoggerInterface;
use AdvancedMailer\NullLogger;

/**
 * Mail queue for batch processing and retry logic
 */
class MailQueue
{
    private array $queue = [];
    private array $failed = [];
    private TransportInterface $transport;
    private LoggerInterface $logger;
    private int $maxRetries = 3;
    private int $retryDelay = 60; // seconds
    private bool $processing = false;

    public function __construct(
        TransportInterface $transport,
        ?LoggerInterface $logger = null
    ) {
        $this->transport = $transport;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Add mail to queue
     */
    public function add(Mail $mail, int $priority = 5): void
    {
        $this->queue[] = [
            'mail' => $mail,
            'priority' => $priority,
            'added_at' => time(),
            'retries' => 0,
            'next_retry' => 0,
            'id' => uniqid('mail_', true)
        ];

        // Sort by priority (lower number = higher priority)
        usort($this->queue, function($a, $b) {
            return $a['priority'] <=> $b['priority'];
        });

        $this->logger->info('Mail added to queue', [
            'queue_size' => count($this->queue),
            'id' => end($this->queue)['id']
        ]);
    }

    /**
     * Add multiple mails to queue
     */
    public function addMultiple(array $mails, int $priority = 5): void
    {
        foreach ($mails as $mail) {
            if ($mail instanceof Mail) {
                $this->add($mail, $priority);
            }
        }
    }

    /**
     * Process the queue
     */
    public function process(int $batchSize = 10): array
    {
        if ($this->processing) {
            throw new MailException('Queue is already being processed');
        }

        $this->processing = true;

        try {
            $results = [
                'processed' => 0,
                'successful' => 0,
                'failed' => 0,
                'retried' => 0
            ];

            $now = time();
            $processed = 0;

            foreach ($this->queue as $key => $item) {
                // Check if we've processed enough for this batch
                if ($processed >= $batchSize) {
                    break;
                }

                // Skip if not ready for retry
                if ($item['next_retry'] > $now) {
                    continue;
                }

                try {
                    $this->logger->info('Processing queued mail', [
                        'id' => $item['id'],
                        'retries' => $item['retries']
                    ]);

                    // Get the message from mail object
                    $mail = $item['mail'];
                    $message = $this->extractMessageFromMail($mail);

                    // Send the message
                    $success = $this->transport->send($message);

                    if ($success) {
                        $results['successful']++;
                        $this->logger->info('Queued mail sent successfully', [
                            'id' => $item['id']
                        ]);
                        unset($this->queue[$key]);
                    } else {
                        $this->handleFailure($item, $key);
                        $results['failed']++;
                    }

                } catch (\Exception $e) {
                    $this->handleFailure($item, $key, $e);
                    $results['failed']++;
                    $this->logger->error('Error processing queued mail', [
                        'id' => $item['id'],
                        'error' => $e->getMessage()
                    ]);
                }

                $processed++;
                $results['processed']++;
            }

            // Reindex array after removals
            $this->queue = array_values($this->queue);

            $this->logger->info('Queue processing completed', $results);

            return $results;

        } finally {
            $this->processing = false;
        }
    }

    /**
     * Get queue statistics
     */
    public function getStats(): array
    {
        $total = count($this->queue);
        $pending = 0;
        $waiting = 0;
        $now = time();

        foreach ($this->queue as $item) {
            if ($item['next_retry'] <= $now) {
                $pending++;
            } else {
                $waiting++;
            }
        }

        return [
            'total' => $total,
            'pending' => $pending,
            'waiting' => $waiting,
            'failed' => count($this->failed),
            'processing' => $this->processing
        ];
    }

    /**
     * Get failed mails
     */
    public function getFailed(): array
    {
        return $this->failed;
    }

    /**
     * Clear the queue
     */
    public function clear(): void
    {
        $this->queue = [];
        $this->failed = [];
        $this->logger->info('Mail queue cleared');
    }

    /**
     * Set maximum retry attempts
     */
    public function setMaxRetries(int $maxRetries): self
    {
        $this->maxRetries = $maxRetries;
        return $this;
    }

    /**
     * Set retry delay in seconds
     */
    public function setRetryDelay(int $delay): self
    {
        $this->retryDelay = $delay;
        return $this;
    }

    /**
     * Handle failed mail sending
     */
    private function handleFailure(array $item, int $key, ?\Exception $e = null): void
    {
        $item['retries']++;

        if ($item['retries'] >= $this->maxRetries) {
            // Move to failed queue
            $this->failed[] = $item;
            unset($this->queue[$key]);

            $this->logger->error('Mail moved to failed queue after max retries', [
                'id' => $item['id'],
                'retries' => $item['retries'],
                'error' => $e?->getMessage()
            ]);
        } else {
            // Schedule retry
            $item['next_retry'] = time() + ($this->retryDelay * $item['retries']);
            $this->queue[$key] = $item;

            $this->logger->warning('Mail scheduled for retry', [
                'id' => $item['id'],
                'retries' => $item['retries'],
                'next_retry' => date('Y-m-d H:i:s', $item['next_retry'])
            ]);
        }
    }

    /**
     * Extract message data from Mail object
     * This is a helper method - in real implementation you'd need to
     * access the private properties or add a method to Mail class
     */
    private function extractMessageFromMail(Mail $mail): array
    {
        // This is a simplified version - in practice you'd need to
        // modify the Mail class to expose this data or use reflection
        return [
            'from' => ['email' => 'extracted@example.com', 'name' => ''],
            'recipients' => [],
            'cc' => [],
            'bcc' => [],
            'reply_to' => ['email' => '', 'name' => ''],
            'subject' => 'Extracted Subject',
            'body' => 'Extracted Body',
            'alt_body' => '',
            'is_html' => false,
            'attachments' => [],
            'embedded_images' => [],
            'headers' => [],
            'priority' => 3,
            'charset' => 'UTF-8',
            'message_id' => uniqid()
        ];
    }

    /**
     * Get queue size
     */
    public function getSize(): int
    {
        return count($this->queue);
    }

    /**
     * Check if queue is empty
     */
    public function isEmpty(): bool
    {
        return empty($this->queue);
    }

    /**
     * Get next mail to process
     */
    public function getNext(): ?array
    {
        $now = time();

        foreach ($this->queue as $item) {
            if ($item['next_retry'] <= $now) {
                return $item;
            }
        }

        return null;
    }
}
