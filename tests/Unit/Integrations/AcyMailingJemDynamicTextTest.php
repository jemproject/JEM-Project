<?php

declare(strict_types=1);

namespace AcyMailing\Helpers {
    final class PluginHelper
    {
        public function getStandardDisplay(object $format): string
        {
            return '{title}{description}';
        }
    }

    final class TabHelper
    {
    }
}

namespace AcyMailing\Core {
    use AcyMailing\Helpers\PluginHelper;

    class AcymPlugin
    {
        public string $cms = 'all';
        public string $name = '';
        public bool $installed = true;
        public object $pluginDescription;
        public array $settings = [];
        protected array $addonDefinition = [];
        protected PluginHelper $pluginHelper;
        protected int $rootCategoryId = 1;
        protected array $displayOptions = [];
        protected array $replaceOptions = [];
        protected array $elementOptions = [];

        public function __construct()
        {
            $this->pluginHelper = new PluginHelper();
            $this->pluginDescription = (object) ['plugin' => static::class];
            $this->name = strtolower(substr(static::class, 7));
        }

        protected function initCustomView(): void
        {
            $this->elementOptions = [];
            $this->initReplaceOptionsCustomView();
            $this->initElementOptionsCustomView();
            $customView = '';
            $this->getStandardStructure($customView);
        }

        protected function getParam(string $name, mixed $default = null): mixed
        {
            return $GLOBALS['acymJemTestParams'][$name] ?? $default;
        }
    }
}

namespace {
    use PHPUnit\Framework\TestCase;

    defined('_JEXEC') || define('_JEXEC', 1);
    defined('ACYM_DYNAMICS_URL') || define('ACYM_DYNAMICS_URL', '/administrator/components/com_acym/dynamics/');
    defined('JPATH_SITE') || define('JPATH_SITE', '/site');
    defined('JPATH_ADMINISTRATOR') || define('JPATH_ADMINISTRATOR', '/administrator');

    function acym_isExtensionActive(string $extension): bool
    {
        return $extension === 'com_jem';
    }

    function acym_loadLanguageFile(string $extension, string $path): void
    {
    }

    function acym_loadObject(string $query): ?object
    {
        return null;
    }

    function acym_isAdmin(): bool
    {
        return (bool) ($GLOBALS['acymJemTestIsAdmin'] ?? true);
    }

    final class AcyMailingJemDynamicTextTest extends TestCase
    {
        public static function setUpBeforeClass(): void
        {
            require_once JEM_TEST_ROOT.'/3rd/acymailing_jem/jem/plugin.php';
        }

        protected function tearDown(): void
        {
            $GLOBALS['acymJemTestParams'] = [];
            $GLOBALS['acymJemTestIsAdmin'] = true;
        }

        public function testAddonReturnsTheDescriptorUsedByDynamicTextType(): void
        {
            $addon = new \plgAcymJem();
            $descriptor = $addon->getPossibleIntegrations();

            self::assertNotNull($descriptor);
            self::assertSame('plgAcymJem', $descriptor->plugin);
            self::assertSame('JEM Events', $descriptor->name);
            self::assertSame('Insert JEM events', $descriptor->title);
            self::assertSame(
                '/administrator/components/com_acym/dynamics/jem/icon.svg',
                $descriptor->icon
            );
        }

        public function testAddonCanBeHiddenFromFrontendDynamicTextType(): void
        {
            $GLOBALS['acymJemTestIsAdmin'] = false;
            $GLOBALS['acymJemTestParams'] = ['front' => 'hide'];

            $addon = new \plgAcymJem();

            self::assertNull($addon->getPossibleIntegrations());
        }
    }
}
