<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\CMS\Pagination\Pagination;
use Joomla\Input\Input;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Focused selector validation using Joomla code and synthetic sessions/SQLite, not site data. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class FrontendListLimitTest extends TestCase
{
    private ListLimitTestApplication $app;

    /** @return void Load the actual Joomla framework and checkout model. */
    protected function setUp(): void
    {
        $root = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
        if (!is_file($root . '/libraries/vendor/autoload.php')) {
            self::markTestSkipped('Requires JEM_TEST_JOOMLA_ROOT; no installed site is modified.');
        }
        if (!defined('JPATH_SITE')) {
            define('JPATH_SITE', $root);
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
        require_once JEM_TEST_ROOT . '/site/models/typevenues.php';
        require_once JEM_TEST_ROOT . '/site/models/specialdays.php';
        require_once JEM_TEST_ROOT . '/tests/Support/list_limit_environment.php';
        $this->app = new ListLimitTestApplication();
        Factory::$application = $this->app;
        $container = new Joomla\DI\Container();
        $container->set('SiteRouter', new stdClass());
        $container->set(Joomla\CMS\HTML\Registry::class, new Joomla\CMS\HTML\Registry());
        Factory::$container = $container;
        Factory::$language = new class {
            /** @param string $text Key. @param bool $jsSafe JS flag. @return string Label. */
            public function _($text, $jsSafe = false): string { return $text; }
        };
    }

    /** @return iterable<string, array{mixed, int}> Raw selector values and expected limit. */
    public static function sizes(): iterable
    {
        foreach ([5, 10, 15, 20, 25, 30, 50, 100, 200, 500, 0] as $size) {
            yield 'allowed ' . $size => [(string) $size, $size];
        }
        foreach ([-1, '-500', 1, 2, 12, 999999, '', 'garbage', '10garbage', '5.5', '1e1', '+10', '010', '99999999999999999999999', ['10']] as $i => $value) {
            yield 'invalid ' . $i => [$value, 10];
        }
    }

    /** @param mixed $value Raw request. @param int $expected Selected page size. @return void */
    #[DataProvider('sizes')]
    public function testRequestAndSavedStateMatchSelector(mixed $value, int $expected): void
    {
        foreach (['eventslist', 'category', 'venue', 'day', 'venueslist', 'search', 'categories',
            'myattendances', 'myevents', 'myvenues', 'attendees', 'attendeeregistrations',
            'selectvenue', 'selectcontact', 'selectarticle', 'addusers', 'specialdays'] as $view) {
            $context = 'com_jem.' . $view . '.test';
            $this->app->input = new Input(['limit' => $value, 'limitstart' => 0]);
            self::assertSame([$expected, 0], JemListLimit::resolve($this->app, $context, 10), $view);
            self::assertSame($expected, $this->app->session[$context . '.limit']);
            $this->app->input = new Input([]);
            self::assertSame([$expected, 0], JemListLimit::resolve($this->app, $context, 10), $view . ' next request');
            $this->app->session[$context . '.limit'] = $value;
            self::assertSame([$expected, 0], JemListLimit::resolve($this->app, $context, 10), $view . ' legacy session');
        }
    }

    /** @return void Negative/malformed starts, All and hidden controls remain consistent. */
    public function testOffsetsDefaultsAndDisabledSelector(): void
    {
        foreach ([-3, ['5'], '10bad', '99999999999999999999999'] as $start) {
            $this->app->input = new Input(['limit' => 10, 'limitstart' => $start]);
            self::assertSame([10, 0], JemListLimit::resolve($this->app, 'v', 20));
        }
        $this->app->input = new Input(['limit' => 10, 'limitstart' => 29]);
        self::assertSame([10, 20], JemListLimit::resolve($this->app, 'v', 20));
        self::assertSame([20, 20], JemListLimit::resolve($this->app, 'v', 20, false));
        $this->app->input = new Input(['limit' => -1, 'limitstart' => 29]);
        self::assertSame([20, 0], JemListLimit::resolve($this->app, 'v', 20));
        self::assertSame([0, 0], JemListLimit::resolve($this->app, 'v', 0));
        self::assertSame([10, 0], JemListLimit::resolve($this->app, 'v', -5));
        self::assertSame([4, 0], JemListLimit::resolve($this->app, 'category', 4, true, true));
        $this->app->input = new Input(['limit' => 4]);
        self::assertSame([4, 0], JemListLimit::resolve($this->app, 'category', 4, true, true));
        self::assertSame([10, 0], JemListLimit::resolve($this->app, 'other', 10));
    }

    /** @return void Actual Joomla parent cannot override state or poison another list's session. */
    public function testJoomlaParentAndTypeVenuesIgnoreNestedPagination(): void
    {
        foreach ([JemModelVenueslist::class, JemModelTypevenues::class, JemModelSpecialdays::class] as $class) {
            foreach ([-1, ['10'], 'garbage', 0, 5, 20] as $value) {
                $this->app->session = ['global.list.limit' => 50];
                $this->app->input = new Input([
                    'limit' => $value, 'limitstart' => -8, 'Itemid' => 123,
                    'list' => ['limit' => [-1], 'start' => -8, 'links' => 99],
                ]);
                $model = (new ReflectionClass($class))->newInstanceWithoutConstructor();
                $context = $class === JemModelSpecialdays::class ? 'com_jem.specialdays' : 'com_jem.venueslist';
                (new ReflectionProperty($model, 'context'))->setValue($model, $context);
                $fallback = $class === JemModelSpecialdays::class ? 20 : 10;
                $expected = in_array($value, [0, 5, 20], true) ? $value : $fallback;
                self::assertSame($expected, $model->getState('list.limit'));
                self::assertSame(0, $model->getState('list.start'));
                self::assertSame(0, $model->getState('list.links'));
                self::assertSame(50, $this->app->session['global.list.limit']);
                self::assertSame([], $this->app->session[$context . '.list']);
                self::assertSame($value, $this->app->input->get('limit', null, 'raw'));
                $this->app->input = new Input(['Itemid' => 123]);
                $next = (new ReflectionClass($class))->newInstanceWithoutConstructor();
                (new ReflectionProperty($next, 'context'))->setValue($next, $context);
                self::assertSame($expected, $next->getState('list.limit'));
            }
        }
    }

    /** @return void The actual query, selected option and subsequent request agree. */
    public function testSqlPageCountsAndRenderedSelector(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('Requires pdo_sqlite.');
        }
        $db = new ListLimitTestDatabase(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $db->setQuery('CREATE TABLE page_rows (id INTEGER PRIMARY KEY)')->execute();
        for ($i = 1; $i <= 30; $i++) {
            $db->setQuery('INSERT INTO page_rows VALUES (' . $i . ')')->execute();
        }
        foreach ([[-1, 10, 0, 10], [1, 10, 0, 10], [5, 5, 5, 5], [20, 20, 20, 10], [0, 0, 0, 30]] as [$raw, $limit, $start, $count]) {
            $this->app->input = new Input(['limit' => $raw, 'limitstart' => $start, 'Itemid' => 123]);
            $model = (new ReflectionClass(ListLimitVenueModel::class))->newInstanceWithoutConstructor();
            $model->setDatabase($db);
            (new ReflectionProperty($model, 'context'))->setValue($model, 'com_jem.venueslist');
            self::assertCount($count, $model->getItems());
            self::assertSame($start + 1, (int) $model->getItems()[0]->id);
            $pagination = $model->getPagination();
            self::assertStringContainsString('value="' . $limit . '" selected="selected"', $pagination->getLimitBox());
        }
        $html = (new Pagination(30, 0, 10))->getLimitBox();
        preg_match_all('/<option value="([0-9]+)"/', $html, $options);
        self::assertSame(JemListLimit::OPTIONS, array_map('intval', $options[1]));
        $custom = JemListLimit::getLimitBox(new Pagination(30, 0, 10), 4);
        self::assertStringContainsString('value="10" selected="selected"', $custom);
        self::assertStringNotContainsString('value="4"', $custom);
        $all = JemListLimit::getLimitBox(new Pagination(30, 0, 0), 4);
        self::assertStringContainsString('value="0" selected="selected"', $all);
    }

    /** @return void Every request-reading list/modal uses the common policy; intentional internal limits stay separate. */
    public function testModelIntegrationCoverage(): void
    {
        foreach (['eventslist' => 1, 'category' => 1, 'venue' => 1, 'day' => 1, 'venueslist' => 1,
            'categories' => 1, 'search' => 1, 'myattendances' => 1, 'myevents' => 1, 'myvenues' => 1,
            'attendees' => 2, 'attendeeregistrations' => 1, 'editevent' => 3, 'specialdays' => 1] as $model => $calls) {
            $source = file_get_contents(JEM_TEST_ROOT . '/site/models/' . $model . '.php');
            self::assertSame($calls, substr_count($source, '\\JemListLimit::resolve('), $model);
            self::assertStringContainsString("require_once __DIR__ . '/../classes/listlimit.class.php';", $source);
        }
        $search = file_get_contents(JEM_TEST_ROOT . '/site/models/search.php');
        self::assertStringContainsString("\$this->isSearchFilterEnabled('limit')", $search);
        $blog = file_get_contents(JEM_TEST_ROOT . '/site/models/eventsblog.php');
        self::assertStringNotContainsString('JemListLimit::resolve', $blog);
        $modal = file_get_contents(JEM_TEST_ROOT . '/site/views/editevent/view.html.php');
        self::assertDoesNotMatchRegularExpression("/getUserStateFromRequest\\([^\\n]+\\.limit'/", $modal);
    }
}
