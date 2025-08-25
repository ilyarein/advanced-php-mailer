<?php

namespace AdvancedMailer;

/**
 * Simple Promise implementation for async mail sending
 */
class Promise
{
    private $successCallback = null;
    private $failureCallback = null;
    private $finallyCallback = null;
    private $result = null;
    private $error = null;
    private $resolved = false;
    private $rejected = false;

    /**
     * Create a new Promise
     */
    public static function create(callable $executor): self
    {
        $promise = new self();
        $executor(
            function($value) use ($promise) {
                $promise->resolve($value);
            },
            function($error) use ($promise) {
                $promise->reject($error);
            }
        );
        return $promise;
    }

    /**
     * Execute Promise
     */
    public function __invoke(callable $resolve, callable $reject): void
    {
        $this->successCallback = $resolve;
        $this->failureCallback = $reject;
    }

    /**
     * Resolve the Promise
     */
    public function resolve($value): void
    {
        if ($this->resolved || $this->rejected) {
            return;
        }

        $this->result = $value;
        $this->resolved = true;

        if ($this->successCallback) {
            call_user_func($this->successCallback, $value);
        }

        if ($this->finallyCallback) {
            call_user_func($this->finallyCallback);
        }
    }

    /**
     * Reject the Promise
     */
    public function reject($error): void
    {
        if ($this->resolved || $this->rejected) {
            return;
        }

        $this->error = $error;
        $this->rejected = true;

        if ($this->failureCallback) {
            call_user_func($this->failureCallback, $error);
        }

        if ($this->finallyCallback) {
            call_user_func($this->finallyCallback);
        }
    }

    /**
     * Add success callback
     */
    public function then(callable $callback): self
    {
        if ($this->resolved) {
            call_user_func($callback, $this->result);
        } else {
            $this->successCallback = $callback;
        }

        return $this;
    }

    /**
     * Add error callback
     */
    public function catch(callable $callback): self
    {
        if ($this->rejected) {
            call_user_func($callback, $this->error);
        } else {
            $this->failureCallback = $callback;
        }

        return $this;
    }

    /**
     * Add finally callback
     */
    public function finally(callable $callback): self
    {
        if ($this->resolved || $this->rejected) {
            call_user_func($callback);
        } else {
            $this->finallyCallback = $callback;
        }

        return $this;
    }

    /**
     * Get the result (blocking)
     */
    public function wait()
    {
        // Simple spin lock - in real implementation you'd want something better
        while (!$this->resolved && !$this->rejected) {
            usleep(1000); // 1ms
        }

        if ($this->rejected) {
            throw $this->error;
        }

        return $this->result;
    }

    /**
     * Check if Promise is resolved
     */
    public function isResolved(): bool
    {
        return $this->resolved;
    }

    /**
     * Check if Promise is rejected
     */
    public function isRejected(): bool
    {
        return $this->rejected;
    }

    /**
     * Get the result value
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * Get the error
     */
    public function getError()
    {
        return $this->error;
    }
}
