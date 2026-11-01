<?php

declare(strict_types=1);

namespace JemProject\Tests\Unit\ListOrdering;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/classes/listorder.class.php';

/** Ordering validation independent of SQL drivers and installation state. */
final class ListOrderTest extends TestCase
{
    /** @return iterable<string, array{mixed}> Malformed request and stale-session values. */
    public static function invalidValues(): iterable
    {
        foreach (['invalid', '', 'a.not_a_column', 'a.title DESC', 'a.title;SELECT', 'ASC, DESC',
            ['a.dates'], new \stdClass(), 1, false] as $index => $value) {
            yield 'invalid-' . $index => [$value];
        }
    }

    /**
     * Repair invalid request/session state without affecting another list context.
     *
     * @param mixed $value Invalid field and direction.
     * @return void
     */
    #[DataProvider('invalidValues')]
    public function testInvalidValuesAreRepaired(mixed $value): void
    {
        foreach ([false, true] as $archive) {
            foreach (['request', 'session'] as $source) {
                $app = new Application();
                $context = 'category.2:100';
                if ($source === 'request') {
                    $app->input->values = ['filter_order' => $value, 'filter_order_Dir' => $value];
                } else {
                    $app->session[$context . '.filter_order'] = $value;
                    $app->session[$context . '.filter_order_Dir'] = $value;
                }
                $app->session['other.filter_order'] = 'l.city';
                $expected = ['a.dates', $archive ? 'DESC' : 'ASC'];
                self::assertSame($expected, \JemListOrder::resolve($app, $context, \JemListOrder::eventFields(), 'a.dates', 'ASC', $archive));
                self::assertSame($expected[0], $app->session[$context . '.filter_order']);
                self::assertSame($expected[1], $app->session[$context . '.filter_order_Dir']);
                $app->input->values = [];
                self::assertSame($expected, \JemListOrder::resolve($app, $context, \JemListOrder::eventFields(), 'a.dates', 'ASC', $archive));
                self::assertSame('l.city', $app->session['other.filter_order']);
            }
        }
    }

    /** @return iterable<string, array{string, string, string}> Every accepted field and direction. */
    public static function allowedValues(): iterable
    {
        foreach (\JemListOrder::eventFields() as $input => $column) {
            foreach (['ASC', 'DESC', 'asc', 'desc'] as $direction) {
                yield $input . '-' . $direction => [$input, $column, $direction];
            }
        }
    }

    /**
     * Preserve all legitimate sorts, including County and explicit archive direction.
     *
     * @param string $input Public or legacy input name.
     * @param string $column Real canonical column.
     * @param string $direction User-selected direction.
     * @return void
     */
    #[DataProvider('allowedValues')]
    public function testAllowedValuesRemainSelectable(string $input, string $column, string $direction): void
    {
        $app = new Application();
        $app->input->values = ['filter_order' => $input, 'filter_order_Dir' => $direction];
        self::assertSame([$column, strtoupper($direction)], \JemListOrder::resolve(
            $app, 'category.2:100', \JemListOrder::eventFields(), 'a.dates', 'ASC', true
        ));
        self::assertSame($column, $app->session['category.2:100.filter_order']);
        self::assertSame(strtoupper($direction), $app->session['category.2:100.filter_order_Dir']);
    }

    /** @return void Defaults are calculated after field validation and stay query-specific. */
    public function testDefaultsAndQuerySpecificFields(): void
    {
        $app = new Application();
        $app->input->values = ['filter_order' => 'invalid'];
        self::assertSame(['a.dates', 'DESC'], \JemListOrder::resolve(
            $app, 'archive', \JemListOrder::eventFields(), 'a.dates', 'ASC', true
        ));
        $app->input->values = ['filter_order' => 'l.state'];
        self::assertSame(['l.state', 'ASC'], \JemListOrder::resolve(
            $app, 'county', \JemListOrder::eventFields(), 'a.dates', 'ASC', true
        ));
        $app->input->values = ['filter_order' => 'a.title', 'filter_order_Dir' => ['DESC']];
        self::assertSame(['l.venue', 'ASC'], \JemListOrder::resolve($app, 'venues', ['l.venue', 'l.city'], 'l.venue'));
        self::assertSame('cat.title', \JemListOrder::field('c.title', ['c.title' => 'cat.title'], 'con.ordering'));
        self::assertSame('ASC', \JemListOrder::direction(null, 'INVALID'));
    }

    /** @return void Every exposed shared-table sort remains supported by the event policy. */
    public function testTemplateColumnsMatchPolicy(): void
    {
        foreach (['site/common/views/tmpl/default_events_table.php',
            'site/common/views/tmpl/responsive/default_jem_eventslist.php'] as $path) {
            preg_match_all("/'grid.sort', '[^']+', '([^']+)'/", file_get_contents(JEM_TEST_ROOT . '/' . $path), $matches);
            self::assertNotEmpty($matches[1]);
            foreach ($matches[1] as $column) {
                self::assertSame($column, \JemListOrder::field($column, \JemListOrder::eventFields(), 'INVALID'));
            }
        }
    }

    /** @return void Model validation must not be undone by another raw read in the list view. */
    public function testViewsUseValidatedModelState(): void
    {
        foreach (['category', 'day', 'venue', 'myevents', 'myvenues', 'myattendances'] as $view) {
            $source = file_get_contents(JEM_TEST_ROOT . '/site/views/' . $view . '/view.html.php');
            self::assertStringContainsString("getState('list.ordering'", $source);
            self::assertStringContainsString("getState('list.direction'", $source);
            self::assertDoesNotMatchRegularExpression("/getUserStateFromRequest\\([^\\n]+['\"]filter_order(?:_Dir)?['\"]/", $source);
        }
    }
}

/** Minimal input boundary retains raw malformed values just like a request can. */
final class Input
{
    public array $values = [];

    /** @param string $key Name. @param mixed $default Fallback. @param string $type Filter. @return mixed Raw value. */
    public function get(string $key, mixed $default = null, string $type = 'raw'): mixed
    {
        return $this->values[$key] ?? $default;
    }
}

/** In-memory session boundary: tests never modify Joomla users or sessions. */
final class Application
{
    public Input $input;
    public array $session = [];

    /** Initialise an empty request. */
    public function __construct()
    {
        $this->input = new Input();
    }

    /** @param string $key State key. @param mixed $default Fallback. @return mixed Saved value. */
    public function getUserState(string $key, mixed $default = null): mixed
    {
        return $this->session[$key] ?? $default;
    }

    /** @param string $key State key. @param mixed $value Validated value. @return void */
    public function setUserState(string $key, mixed $value): void
    {
        $this->session[$key] = $value;
    }
}
