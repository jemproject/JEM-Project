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

    function acym_loadObjectList(string $query, string $key = ''): array
    {
        return $GLOBALS['acymJemTestObjectLists'][$key] ?? [];
    }

    function acym_escapeDB(mixed $value): string
    {
        return "'".addslashes((string) $value)."'";
    }

    function acym_selectOption(mixed $value, string $text): object
    {
        return (object) ['value' => $value, 'text' => $text];
    }

    function acym_isAdmin(): bool
    {
        return (bool) ($GLOBALS['acymJemTestIsAdmin'] ?? true);
    }

    final class JemOutput
    {
        public static function formatLongDateTime(
            mixed $dateStart,
            mixed $timeStart,
            mixed $dateEnd,
            mixed $timeEnd
        ): string {
            return '<span class="jem_date-1">Sa, 19. Dezember 2026</span>'
                .'<span class="jem_time-1">, 18:00 h</span>';
        }
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
            $GLOBALS['acymJemTestObjectLists'] = [];
            $GLOBALS['acymJemTestIsAdmin'] = true;
        }

        public function testAddonReturnsTheDescriptorUsedByDynamicTextType(): void
        {
            $addon = new \plgAcymJem();
            $descriptor = $addon->getPossibleIntegrations();

            self::assertNotNull($descriptor);
            self::assertSame('plgAcymJem', $descriptor->plugin);
            self::assertSame('JEM - Events for AcyMailing', $descriptor->name);
            self::assertSame('Insert JEM events', $descriptor->title);
            self::assertSame(
            '/administrator/components/com_acym/dynamics/jem/icon.png',
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

        public function testEventDateIsConvertedFromJemMarkupToPlainText(): void
        {
            $addon = new \plgAcymJem();
            $method = new \ReflectionMethod($addon, 'formatEventDate');
            $event = (object) [
                'dates' => '2026-12-19',
                'times' => '18:00:00',
                'enddates' => null,
                'endtimes' => null,
            ];

            self::assertSame(
                'Sa, 19. Dezember 2026, 18:00 h',
                $method->invoke($addon, $event)
            );
        }

        public function testMenuSelectorAcceptsOnlyLoadedPublishedJemItems(): void
        {
            $GLOBALS['acymJemTestObjectLists'] = [
                'id' => [
                    42 => (object) [
                        'id' => 42,
                        'title' => 'Events',
                        'menutype' => 'mainmenu',
                        'link' => 'index.php?option=com_jem&view=eventslist',
                        'language' => '*',
                    ],
                ],
            ];
            $addon = new \plgAcymJem();
            $validate = new \ReflectionMethod($addon, 'validateJemMenuItemId');

            self::assertSame(42, $validate->invoke($addon, 42));
            self::assertSame(0, $validate->invoke($addon, 99));
            self::assertSame('select', $addon->settings['itemid']['type']);
            self::assertStringContainsString('does not render a JEM module', $addon->settings['itemid']['info']);
        }
    }
}
