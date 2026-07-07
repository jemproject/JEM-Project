<?php

declare(strict_types=1);

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Version;
use Joomla\Database\DatabaseDriver;

require_once dirname(__DIR__) . '/JoomlaTestCase.php';

final class JoomlaBootstrapTest extends JoomlaTestCase
{
    protected function setUp(): void
    {
        self::bootJoomlaSite();
    }

    public function testJoomlaVersionIsAvailableAndMatchesSupportedMajor(): void
    {
        $version = new Version();

        self::assertSame(5, Version::MAJOR_VERSION);
        self::assertMatchesRegularExpression('/^5\./', $version->getShortVersion());
    }

    public function testJoomlaSiteApplicationIsBootstrappedWithoutExecutingRequest(): void
    {
        $app = Factory::getApplication();

        self::assertInstanceOf(SiteApplication::class, $app);
        self::assertTrue($app->isClient('site'));
    }

    public function testJoomlaDatabaseConnectionIsAvailable(): void
    {
        $db = Factory::getContainer()->get(DatabaseDriver::class);

        self::assertInstanceOf(DatabaseDriver::class, $db);
        self::assertNotSame('', (string) $db->getPrefix());
    }

    public function testJemComponentIsInstalledInJoomlaDatabase(): void
    {
        $db = Factory::getContainer()->get(DatabaseDriver::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName(array('extension_id', 'element', 'enabled')))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_jem'));

        $db->setQuery($query);
        $component = $db->loadObject();

        self::assertIsObject($component, 'com_jem should be installed in the configured Joomla site.');
        self::assertSame('com_jem', $component->element);
        self::assertSame(1, (int) $component->enabled);
    }
}
