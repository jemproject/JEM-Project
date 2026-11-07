<?php

declare(strict_types=1);

namespace JemProject\Tests\ScalarFilters;

use JemFilterInput;
use Joomla\Input\Input;
use Joomla\Registry\Registry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Native Joomla filtering with controlled request and session-state boundaries. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ScalarFilterInputTest extends TestCase
{
    protected function setUp(): void
    {
        $root = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
        if (!is_file($root . '/libraries/vendor/autoload.php')) {
            self::markTestSkipped('Set JEM_TEST_JOOMLA_ROOT to Joomla 5/6; no database is required.');
        }
        require_once $root . '/libraries/vendor/autoload.php';
        require_once JEM_TEST_ROOT . '/site/classes/filterinput.class.php';
    }

    /** Read through Joomla's actual GET/POST input objects as well as merged input. */
    private function input(array $values, string $channel = 'request'): Input
    {
        if ($channel === 'request') {
            return new Input($values);
        }
        $global = '_' . strtoupper($channel);
        $previous = $GLOBALS[$global] ?? [];
        $GLOBALS[$global] = $values;
        $input = (new Input([]))->$channel;
        $GLOBALS[$global] = $previous;

        return $input;
    }

    public static function channels(): iterable
    {
        foreach (['get', 'post', 'request'] as $channel) {
            yield $channel => [$channel];
        }
    }

    #[DataProvider('channels')]
    public function testMalformedRequestDoesNotPoisonPersistentState(string $channel): void
    {
        $app = new FilterApplication();
        foreach ([['alpha', 'alpha'], [['x'], 'alpha'], [null, 'alpha'], ['beta', 'beta'], [null, 'beta'], ['', ''], [null, '']] as [$value, $expected]) {
            $app->input = $this->input($value === null ? [] : ['filter_search' => $value], $channel);
            $search = JemFilterInput::state($app, 'com_jem.eventslist.101.filter_search', 'filter_search');
            self::assertSame($expected, $search);
            self::assertSame($expected, $app->getUserState('com_jem.eventslist.101.filter_search', ''));
            self::assertIsInt(mb_strlen($search));
            self::assertFalse(stripos($search, 'id:'));
        }
        foreach ($app->writes as $value) {
            self::assertIsScalar($value);
        }
    }

    #[DataProvider('channels')]
    public function testAlreadyInvalidSessionRecoversWithoutAValidRequest(string $channel): void
    {
        foreach ([['x'], [['nested']], new \stdClass()] as $badState) {
            foreach ([[], ['filter_search' => ['bad']]] as $request) {
                $app = new FilterApplication();
                $app->setUserState('view.filter_search', $badState);
                $app->writes = [];
                $app->input = $this->input($request, $channel);
                self::assertSame('', JemFilterInput::state($app, 'view.filter_search', 'filter_search'));
                self::assertSame('', $app->getUserState('view.filter_search', ''));
                self::assertSame([''], $app->writes);
            }
        }
    }

    public static function scalarTypes(): iterable
    {
        yield 'unicode and punctuation' => ['string', "Málaga O'Brien & jazz", ''];
        yield 'clear text' => ['string', '', ''];
        yield 'zero text' => ['string', '0', ''];
        yield 'integer' => ['int', '12', 0];
        yield 'integer zero' => ['int', '0', 5];
        yield 'word' => ['word', 'DESC', 'ASC'];
        yield 'command' => ['cmd', 'a.title', 'a.dates'];
    }

    #[DataProvider('scalarTypes')]
    public function testValidValuesKeepJoomlaFiltering(string $type, mixed $value, mixed $default): void
    {
        $app = new FilterApplication();
        $app->input = new Input(['field' => $value]);
        $expected = $app->input->get('field', $default, $type);
        self::assertSame($expected, JemFilterInput::read($app->input, 'field', $default, $type));
        self::assertSame($expected, JemFilterInput::state($app, 'view.field', 'field', $default, $type));
        self::assertSame($expected, $app->getUserState('view.field', $default));
        $app->input = new Input(['field' => ['bad']]);
        self::assertSame($expected, JemFilterInput::state($app, 'view.field', 'field', $default, $type));
        self::assertSame($default, JemFilterInput::read($app->input, 'field', $default, $type));
    }

    public function testNestedFiltersAndPaginationRemainCompatibleWithListModel(): void
    {
        $app = new FilterApplication();
        $app->input = new Input(['filter' => ['search' => 'next'], 'limitstart' => 20]);
        self::assertSame('next', JemFilterInput::state($app, 'view.search', 'filter_search', '', 'string', true));
        self::assertSame(0, $app->input->getInt('limitstart'));
        $app->input = new Input(['filter' => ['search' => ['bad']], 'limitstart' => 20]);
        self::assertSame('next', JemFilterInput::state($app, 'view.search', 'filter_search', '', 'string', true));
        self::assertSame(20, $app->input->getInt('limitstart'));
        $app->input = new Input(['filter_search' => '', 'limitstart' => 20]);
        self::assertSame('', JemFilterInput::state($app, 'view.search', 'filter_search', '', 'string', true));
        self::assertSame(0, $app->input->getInt('limitstart'));
    }

    public function testSessionRepairDoesNotClearAnotherMenusFilters(): void
    {
        $app = new FilterApplication();
        $app->setUserState('menu101.search', ['bad']);
        $app->setUserState('menu202.search', 'keep this');
        self::assertSame('', JemFilterInput::state($app, 'menu101.search', 'filter_search'));
        self::assertSame('keep this', $app->getUserState('menu202.search'));
    }

    public static function searchModels(): iterable
    {
        foreach (['eventslist', 'day', 'category', 'venue', 'venueslist', 'myevents', 'myvenues', 'myattendances', 'attendees', 'attendeeregistrations', 'editevent'] as $model) {
            yield $model => [$model];
        }
    }

    #[DataProvider('searchModels')]
    public function testActualModelSearchReadRetainsValidStateAndRecoversArrays(string $model): void
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/site/models/' . $model . '.php');
        self::assertSame(1, preg_match('/\$search\s*=\s*(JemFilterInput::state\([^;]+);/', $source, $matches));
        $read = eval('return static function ($app, $itemid) { return \\' . $matches[1] . '; };');
        $app = new FilterApplication();
        foreach ([['valid', 'valid'], [['bad'], 'valid'], [null, 'valid'], ['', '']] as [$value, $expected]) {
            $app->input = new Input($value === null ? [] : ['filter_search' => $value]);
            self::assertSame($expected, $read($app, '101'));
        }
        // Reuse the actual session key from that model to simulate a pre-fix session.
        $app->setUserState($app->lastKey, ['previously saved array']);
        $app->input = new Input([]);
        self::assertSame('', $read($app, '101'));
    }

    public function testSearchStillIgnoresDisabledAndUnavailableLocationFilters(): void
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/site/models/search.php');
        self::assertSame(1, preg_match('/(public function getSearchFilter\(.*?)(?=\r?\n    \/\*\*)/s', $source, $match));
        eval('namespace ' . __NAMESPACE__ . '; use \\JemFilterInput; final class SearchModel extends SearchBoundary {' . $match[1] . '}');
        Factory::$app = new FilterApplication();
        Factory::$app->input = new Input(['filter_search' => ['bad'], 'filter_country' => 'ES']);
        $model = new SearchModel();
        self::assertSame('', $model->getSearchFilter('filter_search'));
        $model->enabled = false;
        self::assertSame('', $model->getSearchFilter('filter_country'));
        self::assertSame('', Factory::$app->getUserState('com_jem.search.filter_country', ''));
        $model->enabled = true;
        $model->available = false;
        self::assertSame('', $model->getSearchFilter('filter_country'));
        $model->available = true;
        self::assertSame('ES', $model->getSearchFilter('filter_country'));
    }

    public function testDayCsvInputsReachExplodeAsStrings(): void
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/site/models/day.php');
        foreach (['pub', 'catids', 'locids'] as $name) {
            $pattern = '/explode\(\x27,\x27, (JemFilterInput::read\(\$app->input, \x27' . $name . '\x27, \x27\x27\))\)/';
            self::assertSame(1, preg_match($pattern, $source, $matches));
            $read = eval('return static function ($app) { return explode(",", \\' . $matches[1] . '); };');
            $app = new FilterApplication();
            $app->input = new Input([$name => [['1']]]);
            self::assertSame([''], $read($app));
            $app->input = new Input([$name => '1,2']);
            self::assertSame(['1', '2'], $read($app));
        }
    }

    public function testExportFilterDirectionCannotReachStrtoupperAsAnArray(): void
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/site/views/attendeeregistrations/view.raw.php');
        self::assertSame(1, preg_match('/\$direction = (strtoupper\([^;]+);/', $source, $matches));
        $read = eval('namespace ' . __NAMESPACE__ . '; use \\JemFilterInput; return static function ($app) { return ' . $matches[1] . '; };');
        $app = new FilterApplication();
        foreach ([[['DESC'], 'DESC'], ['ASC', 'ASC'], ['DESC', 'DESC']] as [$input, $expected]) {
            $app->input = new Input(['filter_order_Dir' => $input]);
            self::assertSame($expected, $read($app));
        }
    }

    public function testLegitimateCountryMultiselectIsNotConvertedToScalar(): void
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/site/models/venueslist.php');
        self::assertStringContainsString("'filter_country', array(), 'array'", $source);
        self::assertMatchesRegularExpression('/\$filterCountries = \$app->getUserStateFromRequest\(/', $source);
        self::assertSame(['ES', 'DE'], (new Input(['filter_country' => ['ES', 'DE']]))->get('filter_country', [], 'array'));
    }

    public function testFrontendStateReadersAreExplicitlyScalarOrArray(): void
    {
        foreach (['models', 'views'] as $folder) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(JEM_TEST_ROOT . '/site/' . $folder));
            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $source = '';
                foreach (token_get_all(file_get_contents($file->getPathname())) as $token) {
                    if (is_array($token)) {
                        if (!in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                            $source .= $token[1];
                        }
                    } else {
                        $source .= $token;
                    }
                }
                if (str_contains($source, 'getUserStateFromRequest(')) {
                    self::assertSame('venueslist.php', $file->getFilename());
                    self::assertSame(1, substr_count($source, 'getUserStateFromRequest('));
                }
                if (str_contains($source, 'JemFilterInput::')) {
                    self::assertStringContainsString("/components/com_jem/classes/filterinput.class.php", $source, $file->getPathname());
                }
            }
        }
        self::assertStringContainsString('<folder>classes</folder>', file_get_contents(JEM_TEST_ROOT . '/jem.xml'));
    }
}

final class FilterApplication
{
    public Input $input;
    public Registry $state;
    public array $writes = [];
    public string $lastKey = '';

    public function __construct()
    {
        $this->input = new Input([]);
        $this->state = new Registry();
    }

    public function getUserState($key, $default = null)
    {
        return $this->state->get($key, $default);
    }

    public function setUserState($key, $value)
    {
        $this->lastKey = $key;
        $this->writes[] = $value;
        return $this->state->set($key, $value);
    }
}

class SearchBoundary
{
    public bool $enabled = true;
    public bool $available = true;
    public function isSearchFilterEnabled($name): bool { return $this->enabled; }
    public function isLocationFilterAvailable($name): bool { return $this->available; }
}

final class Factory
{
    public static FilterApplication $app;
    public static function getApplication(): FilterApplication { return self::$app; }
}
