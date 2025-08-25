<?php
// Lightweight PHPUnit TestCase polyfill for environments without PHPUnit installed.
// Declares PHPUnit\Framework\TestCase only if it does not already exist.

namespace PHPUnit\Framework {
    if (!class_exists('PHPUnit\\Framework\\TestCase')) {
        class TestCase
        {
            protected function fail(string $message = ''): void
            {
                throw new \Exception($message ?: 'Test assertion failed');
            }

            public function assertTrue($condition, string $message = ''): void
            {
                if ($condition !== true) {
                    $this->fail($message ?: 'Failed asserting that condition is true.');
                }
            }

            public function assertFalse($condition, string $message = ''): void
            {
                if ($condition !== false) {
                    $this->fail($message ?: 'Failed asserting that condition is false.');
                }
            }

            public function assertNull($value, string $message = ''): void
            {
                if (!is_null($value)) {
                    $this->fail($message ?: 'Failed asserting that value is null.');
                }
            }

            public function assertNotNull($value, string $message = ''): void
            {
                if (is_null($value)) {
                    $this->fail($message ?: 'Failed asserting that value is not null.');
                }
            }

            public function assertIsArray($actual, string $message = ''): void
            {
                if (!is_array($actual)) {
                    $this->fail($message ?: 'Failed asserting that value is array.');
                }
            }

            public function assertIsString($actual, string $message = ''): void
            {
                if (!is_string($actual)) {
                    $this->fail($message ?: 'Failed asserting that value is string.');
                }
            }

            public function assertEquals($expected, $actual, string $message = ''): void
            {
                if ($expected != $actual) {
                    $this->fail($message ?: sprintf('Failed asserting that %s equals %s.', var_export($actual, true), var_export($expected, true)));
                }
            }

            public function assertSame($expected, $actual, string $message = ''): void
            {
                if ($expected !== $actual) {
                    $this->fail($message ?: 'Failed asserting that two variables reference the same value.');
                }
            }

            public function assertStringContainsString(string $needle, string $haystack, string $message = ''): void
            {
                if (strpos($haystack, $needle) === false) {
                    $this->fail($message ?: sprintf('Failed asserting that "%s" is contained in "%s".', $needle, $haystack));
                }
            }

            public function assertContains($needle, $haystack, string $message = ''): void
            {
                if (is_array($haystack)) {
                    if (!in_array($needle, $haystack, true)) {
                        $this->fail($message ?: 'Failed asserting that array contains value.');
                    }
                } else {
                    if (strpos((string)$haystack, (string)$needle) === false) {
                        $this->fail($message ?: 'Failed asserting that haystack contains needle.');
                    }
                }
            }

            public function assertNotEmpty($value, string $message = ''): void
            {
                if (empty($value)) {
                    $this->fail($message ?: 'Failed asserting that value is not empty.');
                }
            }

            public function assertGreaterThan($expected, $actual, string $message = ''): void
            {
                if (!($actual > $expected)) {
                    $this->fail($message ?: sprintf('Failed asserting that %s is greater than %s.', var_export($actual, true), var_export($expected, true)));
                }
            }

            public function assertLessThan($expected, $actual, string $message = ''): void
            {
                if (!($actual < $expected)) {
                    $this->fail($message ?: sprintf('Failed asserting that %s is less than %s.', var_export($actual, true), var_export($expected, true)));
                }
            }
        }
    }
}


