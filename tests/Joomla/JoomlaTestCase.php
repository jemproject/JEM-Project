<?php

declare(strict_types=1);

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Session\Session as CmsSession;
use Joomla\CMS\Table\Table;
use Joomla\Session\Session;
use Joomla\Session\SessionInterface;
use PHPUnit\Framework\TestCase;

abstract class JoomlaTestCase extends TestCase
{
    private static bool $booted = false;

    protected static function joomlaRoot(): string
    {
        $root = getenv('JEM_TEST_JOOMLA_ROOT');

        if (!$root) {
            self::markTestSkipped('Set JEM_TEST_JOOMLA_ROOT to run Joomla integration tests.');
        }

        $root = rtrim(str_replace('\\', '/', (string) $root), '/');

        if (!is_dir($root)) {
            self::markTestSkipped('JEM_TEST_JOOMLA_ROOT does not point to an existing directory: ' . $root);
        }

        return $root;
    }

    protected static function bootJoomlaSite(): void
    {
        if (self::$booted) {
            return;
        }

        $root = self::joomlaRoot();

        self::assertFileExists($root . '/includes/defines.php', 'Joomla site defines.php is required.');
        self::assertFileExists($root . '/includes/framework.php', 'Joomla framework.php is required.');
        self::assertFileExists($root . '/configuration.php', 'Joomla configuration.php is required.');

        self::prepareServerGlobals($root);

        if (!defined('JPATH_BASE')) {
            define('JPATH_BASE', $root);
        }

        require_once $root . '/includes/defines.php';
        require_once JPATH_BASE . '/includes/framework.php';
        restore_error_handler();
        restore_error_handler();
        restore_exception_handler();

        $container = Factory::getContainer();

        $container->alias('session.web', 'session.web.site')
            ->alias('session', 'session.web.site')
            ->alias('JSession', 'session.web.site')
            ->alias(CmsSession::class, 'session.web.site')
            ->alias(Session::class, 'session.web.site')
            ->alias(SessionInterface::class, 'session.web.site');

        Factory::$application = $container->get(SiteApplication::class);
        Table::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_jem/tables');

        self::$booted = true;
    }

    private static function prepareServerGlobals(string $root): void
    {
        $_SERVER['HTTP_HOST'] ??= 'localhost';
        $_SERVER['SERVER_NAME'] ??= 'localhost';
        $_SERVER['REQUEST_METHOD'] ??= 'GET';
        $_SERVER['REQUEST_URI'] ??= '/';
        $_SERVER['SCRIPT_NAME'] ??= '/index.php';
        $_SERVER['PHP_SELF'] ??= '/index.php';
        $_SERVER['DOCUMENT_ROOT'] ??= $root;
    }
}
