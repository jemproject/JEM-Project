<?php

declare(strict_types=1);

use Joomla\CMS\Application\ConsoleApplication;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\Component\Finder\Administrator\Indexer\Result;
use Joomla\Registry\Registry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Exercise the actual JEM plugin and native Joomla Finder/console classes without DB writes. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class FinderCliContextTest extends TestCase
{
    private plgFinderJEM $plugin;
    private FinderContextRecorder $recorder;

    /** @return void Load native classes and isolate external plugin/index side effects. */
    protected function setUp(): void
    {
        $root = getenv('JEM_TEST_JOOMLA_ROOT');
        if (!$root || !is_file($root . '/libraries/vendor/autoload.php')) {
            self::markTestSkipped('Set JEM_TEST_JOOMLA_ROOT to a Joomla 5 or 6 installation.');
        }

        $loader = require $root . '/libraries/vendor/autoload.php';
        $loader->addPsr4(
            'Joomla\\Component\\Finder\\Administrator\\',
            $root . '/administrator/components/com_finder/src'
        );
        require_once JEM_TEST_ROOT . '/tests/Support/finder_context_environment.php';

        $this->plugin = (new ReflectionClass(plgFinderJEM::class))->newInstanceWithoutConstructor();
        $this->recorder = new FinderContextRecorder();
        (new ReflectionProperty($this->plugin, 'indexer'))->setValue($this->plugin, $this->recorder);
        (new ReflectionProperty($this->plugin, 'params'))->setValue($this->plugin, new Registry());
    }

    /** @return iterable<string, array{bool, ?array<int>}> CLI and web identity combinations. */
    public static function contexts(): iterable
    {
        yield 'CLI without identity' => [false, null];
        yield 'CLI with guest identity' => [false, [1]];
        yield 'CLI with explicit view levels' => [false, [1, 2, 7]];
        yield 'web without identity' => [true, null];
        yield 'web with guest identity' => [true, [1]];
        yield 'web with explicit view levels' => [true, [1, 2, 7]];
    }

    /**
     * Retain web routing and identity levels while allowing an uninitialised CLI user.
     * @param bool $web Whether the application offers a web menu.
     * @param array<int>|null $levels Explicit user levels, or no current identity.
     * @return void
     */
    #[DataProvider('contexts')]
    public function testIndexHandlesApplicationContext(bool $web, ?array $levels): void
    {
        $identity = $levels === null ? null : new FinderContextUser($levels);
        if ($web) {
            $application = new FinderContextWebApplication($identity);
        } else {
            $application = (new ReflectionClass(ConsoleApplication::class))->newInstanceWithoutConstructor();
            if ($identity !== null) {
                $application->loadIdentity($identity);
            }
        }
        Factory::$application = $application;
        $item = $this->event();

        (new ReflectionMethod($this->plugin, 'index'))->invoke($this->plugin, $item);

        self::assertSame([$item], $this->recorder->items);
        self::assertSame($levels ?? [1], JemHelper::$levels);
        self::assertSame($identity, $application->getIdentity(), 'Indexing must not replace the identity.');
        self::assertSame('index.php?option=com_jem&view=event&id=42', $item->url);
        self::assertSame(3, $item->access, 'The event access level must remain unchanged.');
        self::assertSame(2, $item->cat_access);
        self::assertSame(1, $item->state);
        self::assertSame('en-GB', $item->language);
        self::assertSame('CLI probe', $item->title);

        if ($web) {
            self::assertSame([['42:cli-probe', '7:demo']], JEMHelperRoute::$calls);
            self::assertSame(['site'], $application->menuCalls);
            self::assertSame(
                'index.php?option=com_jem&view=event&id=42:cli-probe&catid=7:demo&Itemid=99',
                $item->route
            );
        } else {
            self::assertFalse(method_exists($application, 'getMenu'));
            self::assertSame([], JEMHelperRoute::$calls);
            self::assertSame($item->url, $item->route);
        }
    }

    /** @return void Keep the early exit for disabled JEM, without consulting an application. */
    public function testDisabledComponentDoesNotIndex(): void
    {
        ComponentHelper::$enabled = false;
        Factory::$application = null;
        (new ReflectionMethod($this->plugin, 'index'))->invoke($this->plugin, $this->event());

        self::assertSame([], $this->recorder->items);
        self::assertSame([], JemHelper::$levels);
        self::assertSame([], JEMHelperRoute::$calls);
    }

    /** @return Result A published event with restricted access, alias and category context. */
    private function event(): Result
    {
        $item = new Result();
        foreach ([
            'id' => 42, 'alias' => 'cli-probe', 'title' => 'CLI probe', 'article_id' => 0,
            'introtext' => 'Introduction', 'fulltext' => 'Body', 'params' => '{}', 'metadata' => '{}',
            'venue' => '', 'city' => '', 'countryname' => '', 'catslug' => '7:demo',
            'state' => 1, 'cat_state' => 1, 'cat_access' => 2, 'access' => 3,
            'category' => 'Demo', 'language' => 'en-GB',
        ] as $key => $value) {
            $item->$key = $value;
        }

        return $item;
    }
}
