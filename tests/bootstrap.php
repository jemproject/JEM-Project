<?php
/**
 * PHPUnit bootstrap for repository-level tests.
 */

declare(strict_types=1);

define('JEM_TEST_ROOT', dirname(__DIR__));

if (!defined('_JEXEC')) {
    define('_JEXEC', 1);
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'JemProject\\Tests\\Support\\';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = __DIR__ . '/Support/' . $relative . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});
