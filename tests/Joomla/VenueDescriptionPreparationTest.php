<?php

declare(strict_types=1);

use Joomla\CMS\Application\EventAware;
use Joomla\CMS\Event\Content\ContentPrepareEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Event\Dispatcher;
use Joomla\Registry\Registry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/** Exact view preparation blocks with native Joomla dispatch, not whole-view/browser acceptance. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class VenueDescriptionPreparationTest extends TestCase
{
    /** @return void Load native Joomla services without reading the installed plugin catalogue. */
    protected function setUp(): void
    {
        $root = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
        if (!is_file($root . '/libraries/vendor/autoload.php')) {
            self::markTestSkipped('Requires JEM_TEST_JOOMLA_ROOT; no installed site is modified.');
        }
        require_once $root . '/libraries/vendor/autoload.php';
        spl_autoload_register(static function (string $class) use ($root): void {
            if (str_starts_with($class, 'Joomla\\CMS\\')) {
                $file = $root . '/libraries/src/' . str_replace('\\', '/', substr($class, 11)) . '.php';
                if (is_file($file)) {
                    require_once $file;
                }
            }
        });
        (new ReflectionProperty(PluginHelper::class, 'plugins'))->setValue(null, []);
    }

    /** @return iterable<string, array{string, array, bool, string}> Both views and empty/nonempty descriptions. */
    public static function descriptions(): iterable
    {
        $cases = [
            'missing' => [[], false],
            'null' => [['locdescription' => null], false],
            'empty' => [['locdescription' => ''], false],
            'legacy-break' => [['locdescription' => '<br>'], false],
            'zero' => [['locdescription' => '0'], true],
            'html' => [['locdescription' => '<p>Venue & text</p>'], true],
            'image' => [['locdescription' => '<img src="room.png" alt="Room">'], true],
            'shortcode' => [['locdescription' => '{loadposition venue}'], true],
            'space' => [['locdescription' => ' '], true],
            'other-break' => [['locdescription' => '<br />'], true],
            'editor-html' => [['locdescription' => '<p><br></p>'], true],
        ];
        foreach (['venues', 'venue'] as $view) {
            foreach (['none', 'passive', 'mutating'] as $pluginMode) {
                foreach ($cases as $name => [$properties, $prepare]) {
                    yield $view . '/' . $pluginMode . '/' . $name => [$view, $properties, $prepare, $pluginMode];
                }
            }
        }
    }

    /**
     * Execute the exact description block, preserving native plugin dispatch and output references.
     *
     * @param string $view List or detail view.
     * @param array $properties Description property, including absence/null cases.
     * @param bool $prepare Whether content preparation is expected.
     * @param string $pluginMode No listener, passive listener or text-mutating listener.
     * @return void
     */
    #[DataProvider('descriptions')]
    public function testDescriptionPreparation(string $view, array $properties, bool $prepare, string $pluginMode): void
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/site/views/' . $view . '/view.html.php');
        self::assertSame(1, preg_match('/\/\/ Generate Venuedescription\s*(.*?)\s*\/\/\s*build the url/s', $source, $block));

        $app = new class {
            use EventAware {
                triggerEvent as private dispatchNativeEvent;
            }

            public Dispatcher $dispatcher;
            public int $calls = 0;

            /** @return Dispatcher Isolated native event dispatcher. */
            public function getDispatcher(): Dispatcher
            {
                return $this->dispatcher;
            }

            /** @return NullLogger Logger required by the native application event trait. */
            public function getLogger(): NullLogger
            {
                return new NullLogger();
            }

            /**
             * Count requests, then use native Joomla event conversion and dispatch.
             *
             * @param string $eventName Event name.
             * @param mixed $args Legacy argument list or event.
             * @return array Listener results.
             */
            public function triggerEvent($eventName, $args = []): array
            {
                ++$this->calls;

                return $this->dispatchNativeEvent($eventName, $args);
            }
        };
        $app->dispatcher = new Dispatcher();
        Factory::$application = $app;
        $params = new Registry(['sentinel' => 'preserved']);
        $record = (object) ($properties + ['venue' => 'Test venue', 'id' => 17]);
        $listenerCalls = 0;
        if ($pluginMode !== 'none') {
            $app->dispatcher->addListener('onContentPrepare', static function (ContentPrepareEvent $event) use (
                &$listenerCalls,
                $pluginMode,
                $record,
                $params,
                $properties
            ): void {
                ++$listenerCalls;
                self::assertSame('com_jem.venue', $event->getContext());
                self::assertSame($record, $event->getItem());
                self::assertSame($params, $event->getParams());
                self::assertSame(0, $event->getPage());
                self::assertSame('Test venue', $event->getItem()->title);
                self::assertSame($properties['locdescription'] ?? null, $event->getItem()->text);
                if ($pluginMode === 'mutating') {
                    $event->getItem()->text = (string) $event->getItem()->text . '[prepared]';
                }
            });
        }

        $errorReporting = error_reporting(E_ALL);
        set_error_handler(static function (int $level, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $level)) {
                return false;
            }
            throw new ErrorException($message, 0, $level, $file, $line);
        });
        try {
            $run = static function (string $code, string $view, object $record, object $app, Registry $params) {
                $item = $venue = $record;
                eval('use Joomla\\CMS\\Plugin\\PluginHelper;' . $code);

                // A skipped detail branch must still initialize its output variable.
                return $view === 'venue' ? $venuedescription : ($item->locdescription ?? null);
            };
            $result = $run($block[1], $view, $record, $app, $params);
        } finally {
            restore_error_handler();
            error_reporting($errorReporting);
        }

        $expected = $properties['locdescription'] ?? null;
        if ($view === 'venue') {
            $expected = (string) $expected;
        }
        if ($prepare && $pluginMode === 'mutating') {
            $expected = (string) $expected . '[prepared]';
        }
        self::assertSame((int) $prepare, $app->calls);
        self::assertSame((int) ($prepare && $pluginMode !== 'none'), $listenerCalls);
        self::assertSame($expected, $result);
        self::assertSame($prepare, property_exists($record, 'text'));
        self::assertSame('preserved', $params->get('sentinel'));
        self::assertSame(17, $record->id);
    }
}
