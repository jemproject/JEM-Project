<?php
/**
 * PHPUnit bootstrap for repository-level tests.
 */

declare(strict_types=1);

define('JEM_TEST_ROOT', dirname(__DIR__));

if (!defined('_JEXEC')) {
    define('_JEXEC', 1);
}
