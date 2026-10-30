<?php

declare(strict_types=1);

use JemProject\Tests\VenueState\Application;
use JemProject\Tests\VenueState\Factory;
use JemProject\Tests\VenueState\Input;
use JemProject\Tests\VenueState\Model;
use JemProject\Tests\VenueState\TypeModel;
use JemProject\Tests\VenueState\ViewState;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class VenuesListStateTest extends TestCase
{
    /** @return void Load only production state methods with isolated dependencies. */
    public static function setUpBeforeClass(): void
    {
        require_once JEM_TEST_ROOT . '/tests/Support/venue_list_state_environment.php';
        $source = file_get_contents(JEM_TEST_ROOT . '/site/models/venueslist.php');
        $methods = '';
        foreach (['populateState', 'normaliseCountries', 'normaliseCountry'] as $method) {
            $methods .= self::methodSource($source, $method);
        }
        eval('namespace JemProject\Tests\VenueState; class Model extends ModelDependencies {' . $methods . '}');
        $source = file_get_contents(JEM_TEST_ROOT . '/site/models/typevenues.php');
        eval('namespace JemProject\Tests\VenueState; final class TypeModel extends Model {' . self::methodSource($source, 'populateState') . '}');
    }

    /** @return void Reset the isolated session for each test. */
    protected function setUp(): void
    {
        Factory::$application = new Application();
    }

    /** @param string $source Production PHP. @param string $name Method. @return string Complete declaration. */
    private static function methodSource(string $source, string $name): string
    {
        self::assertSame(1, preg_match('/(?:protected|private) function ' . preg_quote($name, '/')
            . '\([^\n]*\)\s*\{.*?^    \}/ms', $source, $match), $name);

        return $match[0] . "\n";
    }

    /**
     * Compare prepared controls to the actual model state on each synthetic request.
     *
     * @param string $view View/model name.
     * @param array<string, mixed> $request Request values.
     * @return array<string, mixed> Prepared list controls.
     */
    private function visit(string $view, array $request): array
    {
        Factory::$application->input = new Input($request);
        $model = $view === 'venueslist' ? new Model() : new TypeModel();
        $state = $model->getState();
        $session = Factory::$application->session;
        $lists = (new ViewState($state))->lists($view);
        self::assertSame((string) $state->get('filter.filter_search', ''), $lists['search']);
        self::assertSame($state->get('filter.orderby'), $lists['order'] . ' ' . $lists['order_Dir']);
        self::assertSame($session, Factory::$application->session, 'View preparation must not rewrite session state.');
        $type = (int) $state->get('filter.filter_type', 0);
        if (in_array($type, [2, 3, 5, 6], true)) {
            self::assertStringContainsString('value="' . $type . '" selected', $lists['filter']);
        }
        if ($view === 'typevenues') {
            self::assertSame((int) ($request['id'] ?? 0), $state->get('filter.type_id'));
        }

        return $lists;
    }

    /** @return iterable<string, array{string, string}> Menu/type and mixed-view transitions. */
    public static function contexts(): iterable
    {
        foreach (['venueslist', 'typevenues'] as $view) {
            foreach (['menu', 'type', 'mixed'] as $scope) {
                yield "$view:$scope" => [$view, $scope];
            }
        }
    }

    /** @param string $view First view. @param string $scope Context variation. @return void */
    #[DataProvider('contexts')]
    public function testMenuStateAndClearRemainIsolated(string $view, string $scope): void
    {
        $a = ['id' => 7, 'Itemid' => 101];
        $b = $scope === 'type' ? ['id' => 8, 'Itemid' => 101] : ['id' => 7, 'Itemid' => 102];
        $bView = $scope === 'mixed' ? ($view === 'venueslist' ? 'typevenues' : 'venueslist') : $view;
        $valuesA = ['filter_search' => 'Wolvega', 'filter_type' => 2, 'filter_order' => 'a.venue', 'filter_order_Dir' => 'ASC'];
        $valuesB = ['filter_search' => 'Sneek', 'filter_type' => 3, 'filter_order' => 'a.city', 'filter_order_Dir' => 'DESC'];
        $this->visit($view, $a + $valuesA);
        $this->visit($bView, $b + $valuesB);
        $restored = $this->visit($view, $a);
        self::assertSame('Wolvega', $restored['search']);
        self::assertSame('a.venue', $restored['order']);
        self::assertSame('ASC', $restored['order_Dir']);
        self::assertSame('', $this->visit($view, $a + ['filter_search' => ''])['search']);
        self::assertSame('Sneek', $this->visit($bView, $b)['search']);
        $fresh = $this->visit($view, ['id' => 9, 'Itemid' => 999]);
        self::assertSame('', $fresh['search']);
        self::assertSame('a.city', $fresh['order']);
        self::assertSame('ASC', $fresh['order_Dir']);
    }

    /** @return iterable<string, array{string, string, string}> Accepted and invalid ordering requests. */
    public static function orderingCases(): iterable
    {
        foreach (['venueslist', 'typevenues'] as $view) {
            foreach (['a.city', 'a.venue', 'a.state', 'a.country', 'a.ordering', 'a.id', 'unexpected'] as $order) {
                foreach (['ASC', 'DESC', 'desc', 'invalid'] as $direction) {
                    yield "$view:$order:$direction" => [$view, $order, $direction];
                }
            }
        }
    }

    /** @param string $view View. @param string $order Submitted column. @param string $direction Direction. @return void */
    #[DataProvider('orderingCases')]
    public function testControlsUseNormalizedOrdering(string $view, string $order, string $direction): void
    {
        $request = [
            'id' => 7, 'Itemid' => 101, 'filter_search' => 'North "Hall" & O\'Brien', 'filter_type' => 2,
            'filter_order' => $order, 'filter_order_Dir' => $direction,
        ];
        $lists = $this->visit($view, $request);
        self::assertSame($order === 'unexpected' ? 'a.city' : $order, $lists['order']);
        self::assertSame(strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC', $lists['order_Dir']);
        self::assertSame($request['filter_search'], $lists['search']);
    }

    /** @return iterable<string, array{string, string}> Parent-state regression cases. */
    public static function parentStates(): iterable
    {
        foreach (['venueslist', 'typevenues'] as $view) {
            foreach (['unexpected', '', 'a.id DESC'] as $order) {
                yield "$view:$order" => [$view, $order];
            }
        }
    }

    /** @param string $view View. @param string $genericOrder Generic parent filter. @return void */
    #[DataProvider('parentStates')]
    public function testValidatedOrderingSurvivesParentInitialization(string $view, string $genericOrder): void
    {
        $context = ['id' => 7, 'Itemid' => 101];
        $first = $this->visit($view, $context + [
            'filter_order' => 'a.city', 'filter_order_Dir' => 'ASC', 'filter' => ['orderby' => $genericOrder],
        ]);
        self::assertSame('a.city', $first['order']);
        self::assertSame('ASC', $first['order_Dir']);
        $second = $this->visit($view, $context);
        self::assertSame('a.city', $second['order']);
        self::assertSame('ASC', $second['order_Dir']);
    }

    /** @return iterable<string, array{string, string, string}> Both styles and filter positions. */
    public static function renderCases(): iterable
    {
        foreach (['venueslist', 'typevenues'] as $view) {
            foreach (['', 'responsive'] as $layout) {
                foreach (['', 'jem-filterbelow'] as $suffix) {
                    yield "$view:$layout:$suffix" => [$view, $layout, $suffix];
                }
            }
        }
    }

    /** @param string $view View. @param string $layout Style. @param string $suffix Position. @return void */
    #[DataProvider('renderCases')]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testActualTemplatesRenderRestoredState(string $view, string $layout, string $suffix): void
    {
        $context = ['id' => 7, 'Itemid' => 101];
        $search = 'North "Hall" & O\'Brien';
        $this->visit($view, $context + [
            'filter_search' => $search, 'filter_type' => 3, 'filter_order' => 'a.venue', 'filter_order_Dir' => 'DESC',
        ]);
        $this->visit($view, ['id' => 8, 'Itemid' => 102, 'filter_search' => 'Other', 'filter_type' => 2]);
        $lists = $this->visit($view, $context);
        require_once JEM_TEST_ROOT . '/tests/Support/venues_filter_fixture.php';
        defined('JPATH_SITE') || define('JPATH_SITE', JEM_TEST_ROOT);
        $fixture = new VenuesFilterFixture([
            'view' => $view, 'layout' => $layout, 'suffix' => $suffix,
            'search' => true, 'country' => true, 'limit' => true, 'all' => false, 'rows' => true,
        ]);
        $fixture->lists = $lists;
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<!doctype html><html><body>' . $fixture->render() . '</body></html>');
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new DOMXPath($document);
        self::assertSame($search, $xpath->evaluate('string(//input[@name="filter_search"]/@value)'));
        self::assertSame('3', $xpath->evaluate('string(//select[@name="filter_type"]/option[@selected]/@value)'));
        self::assertSame('a.venue', $xpath->evaluate('string(//input[@name="filter_order"]/@value)'));
        self::assertSame('DESC', $xpath->evaluate('string(//input[@name="filter_order_Dir"]/@value)'));
        self::assertSame(1, $xpath->query('//form')->length);
    }
}
