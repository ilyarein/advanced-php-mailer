# Tests

This folder contains unit tests for the project. Basic PHPUnit polyfill is included to allow running tests in environments where PHPUnit is not installed globally.

Running tests
------------

1. Preferred: install PHPUnit via Composer (recommended)

```bash
# from repository root
composer install
vendor/bin/phpunit
```

2. If Composer/PHPUnit is not available, run tests using the included lightweight polyfill (limited functionality):

```bash
# run tests via PHP CLI (ensure php is in PATH)
php -f tests/SmtpTransportTest.php
```

Notes
-----
- The polyfill provides a minimal `PHPUnit\\Framework\\TestCase` replacement. It is intended for quick checks only and does not replace full PHPUnit functionality.
- For CI and reliable testing, install PHPUnit via Composer and add a `phpunit.xml` configuration file.


