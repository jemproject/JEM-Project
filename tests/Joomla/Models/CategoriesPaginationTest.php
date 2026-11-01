<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\CMS\Pagination\Pagination;
use Joomla\Input\Input;
use Joomla\Registry\Registry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Real Categories state/query pagination and Joomla selectors; no installed-site writes. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class CategoriesPaginationTest extends TestCase
{
    private ListLimitTestApplication $app;

    /** @return void Load framework services and a private SQLite fixture with 37 categories. */
    protected function setUp(): void
    {
        $root = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
        if (!is_file($root . '/libraries/vendor/autoload.php') || !extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('Requires local Joomla libraries and PDO SQLite; no site data is used.');
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
        require_once JEM_TEST_ROOT . '/site/models/venueslist.php';
        require_once JEM_TEST_ROOT . '/site/models/categories.php';
        require_once JEM_TEST_ROOT . '/tests/Support/list_limit_environment.php';
        require_once JEM_TEST_ROOT . '/tests/Support/categories_pagination_environment.php';
        $this->app = new ListLimitTestApplication();
        Factory::$application = $this->app;
        $db = new ListLimitTestDatabase(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        Factory::$database = $db;
        $db->setQuery("CREATE TABLE page_rows (id INTEGER PRIMARY KEY, catname TEXT, description TEXT)")->execute();
        for ($id = 1; $id <= 37; $id++) {
            $db->setQuery("INSERT INTO page_rows VALUES ($id, 'Category $id', '')")->execute();
        }
        $container = new Joomla\DI\Container();
        $container->set('DatabaseDriver', $db);
        $container->set('SiteRouter', new class {
            /** @param string $url Input route. @return Joomla\CMS\Uri\Uri Synthetic route. */
            public function build(string $url): Joomla\CMS\Uri\Uri
            {
                return new Joomla\CMS\Uri\Uri($url);
            }
        });
        $container->set(Joomla\CMS\HTML\Registry::class, new Joomla\CMS\HTML\Registry());
        Factory::$container = $container;
        Factory::$language = new class {
            /** @param string $text Key. @param bool $safe JS flag. @return string Label. */
            public function _($text, $safe = false): string
            {
                return $text;
            }
        };
    }

    /** @return iterable<string, array{int, mixed, mixed, int, int}> Default, request, session, selected size and start. */
    public static function selections(): iterable
    {
        yield 'global default replaces menu 4' => [10, null, null, 10, 10];
        yield 'changed global default' => [20, null, null, 20, 0];
        yield 'global All' => [0, null, null, 0, 0];
        yield 'invalid global default' => [4, null, null, 10, 10];
        yield 'request 5' => [20, '5', null, 5, 15];
        yield 'request All' => [20, '0', null, 0, 0];
        yield 'saved 15' => [20, null, 15, 15, 15];
        yield 'saved All' => [20, null, 0, 0, 0];
        yield 'legacy saved 4' => [20, null, 4, 20, 0];
        yield 'legacy request 4' => [20, '4', null, 20, 0];
        yield 'invalid 999' => [10, '999', null, 10, 0];
        yield 'malformed array' => [10, ['5'], null, 10, 0];
        yield 'request overrides session' => [20, '10', 5, 10, 10];
    }

    /**
     * Verify the actual model, query result, selected option and retained per-menu session.
     *
     * @param int $default Global JEM page size.
     * @param mixed $request Visitor selection.
     * @param mixed $saved Previous session selection.
     * @param int $expected Validated size.
     * @param int $start Expected aligned offset.
     * @return void
     */
    #[DataProvider('selections')]
    public function testModelAndSelectorsUseSharedLimit(int $default, mixed $request, mixed $saved, int $expected, int $start): void
    {
        JemHelper::$defaultLimit = $default;
        $key = 'com_jem.categories.1:123';
        foreach (['normal', 'print', 'archive', 'archive-print'] as $route) {
            $values = ['id' => 1, 'Itemid' => 123, 'limitstart' => 17,
                'filter_search' => 'example', 'filter_typeid' => 3];
            if ($request !== null) {
                $values['limit'] = $request;
            }
            if (str_contains($route, 'archive')) {
                $values['task'] = 'archive';
            }
            if (str_contains($route, 'print')) {
                $values['print'] = 1;
                $values['tmpl'] = 'component';
            }
            $this->app->params = new Registry(['cat_num' => 4, 'usecat' => 0, 'id' => 1]);
            $this->app->session = $saved === null ? [] : [$key . '.limit' => $saved];
            $this->app->input = new Input($values);
            $model = new CategoriesPaginationModel();
            self::assertSame($expected, $model->getState('limit'), $route);
            self::assertSame($start, $model->getState('limitstart'));
            self::assertSame('example', (new ReflectionProperty($model, '_search'))->getValue($model));
            self::assertSame(3, $model->getFilterTypeId());
            $rows = $model->getData();
            $count = $expected === 0 ? 37 : min($expected, 37 - $start);
            self::assertCount($count, $rows);
            self::assertSame($start + 1, (int) $rows[0]->id);
            $pagination = $model->getPagination();
            $html = $pagination->getLimitBox();
            self::assertStringContainsString('value="' . $expected . '" selected="selected"', $html);
            self::assertStringNotContainsString('value="4"', $html);
            self::assertSame($html, JemListLimit::getLimitBox($pagination, 4), 'Legacy override shim.');
            self::assertSame($expected, $this->app->session[$key . '.limit']);
            $this->app->input = new Input(['id' => 1, 'Itemid' => 123]);
            self::assertSame($expected, (new CategoriesPaginationModel())->getState('limit'));
            $this->app->input = new Input(['id' => 1, 'Itemid' => 124]);
            self::assertSame(in_array($default, JemListLimit::OPTIONS, true) ? $default : 10,
                (new CategoriesPaginationModel())->getState('limit'), 'Another menu keeps its own state.');
        }
    }

    /** @return void All remains selected with empty or nonstandard record counts. */
    public function testLegacyHelperPreservesAll(): void
    {
        foreach ([0, 3, 30, 97] as $total) {
            $pagination = new Pagination($total, 0, 0);
            $html = JemListLimit::getLimitBox($pagination, 4);
            self::assertSame($pagination->getLimitBox(), $html);
            self::assertStringContainsString('value="0" selected="selected"', $html);
            self::assertStringNotContainsString('value="4"', $html);
        }
    }

    /** @return void Both templates use Joomla and the obsolete menu field is absent. */
    public function testMenuAndTemplateContract(): void
    {
        $xml = simplexml_load_file(JEM_TEST_ROOT . '/site/views/categories/tmpl/default.xml');
        self::assertSame([], $xml->xpath('//field[@name="cat_num"]'));
        self::assertCount(1, $xml->xpath('//field[@name="detcat_nr"]'));
        $fields = array_map(static fn($field) => (string) $field['name'],
            $xml->xpath('//field[@name and not(@type="spacer")]'));
        $prefix = 'site/views/categories/tmpl/default.xml|';
        $inventory = [];
        foreach (file(JEM_TEST_ROOT . '/tests/Static/Xml/Shared/site-menu-fields.txt', FILE_IGNORE_NEW_LINES) as $line) {
            if (str_starts_with($line, $prefix)) {
                $inventory[] = substr($line, strlen($prefix));
            }
        }
        sort($fields);
        sort($inventory);
        self::assertSame($inventory, $fields, 'Categories field inventory is synchronized.');
        foreach (['default.php', 'responsive/default.php'] as $layout) {
            $source = file_get_contents(JEM_TEST_ROOT . '/site/views/categories/tmpl/' . $layout);
            self::assertStringNotContainsString('cat_num', $source);
            self::assertStringContainsString('$this->pagination->getLimitBox()', $source);
        }
        $pdf = file_get_contents(JEM_TEST_ROOT . '/site/views/categories/view.raw.php');
        self::assertStringContainsString("\$model->setState('limit', 0);", $pdf);
    }
}
