<?php

declare(strict_types=1);

namespace JemProject\Tests\DetailMenu;

use JemDetailMenu;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Identity checks and production menu/view preparation, independent of an installed Joomla. */
final class DetailMenuIdentityTest extends TestCase
{
    /** @return void Load the shared identity policy. */
    public static function setUpBeforeClass(): void
    {
        require_once JEM_TEST_ROOT . '/site/helpers/detailmenu.php';
    }

    /** @return iterable<string, array{mixed, int|null}> Valid slugs and malformed IDs. */
    public static function ids(): iterable
    {
        yield 'integer' => [7, 7];
        yield 'numeric string' => ['7', 7];
        yield 'legacy alias' => ['7:music-festival', 7];
        yield 'Unicode alias' => ['7:música-en-españa', 7];
        yield 'leading zero' => ['007:alias', 7];
        yield 'list root' => [0, 0];
        foreach (['7wrong', '7:', '7:foo:bar', '-7', '7.5', '7e1', '7/alias', ' 7', '7 ',
            '7:alias?x=1', '9999999999999999999999999', '', null, [], true, 7.0] as $key => $value) {
            yield 'invalid ' . $key => [$value, null];
        }
    }

    /** @param mixed $value Route value. @param int|null $expected Valid ID or null. @return void */
    #[DataProvider('ids')]
    public function testStrictIdentity(mixed $value, ?int $expected): void
    {
        self::assertSame($expected, JemDetailMenu::id($value));
    }

    /** @return void Menu-specific titles/settings apply only to the correct entity, including legacy aliases. */
    public function testRealViewParameterConditions(): void
    {
        foreach (['event', 'venue', 'category'] as $view) {
            $source = file_get_contents(JEM_TEST_ROOT . '/site/views/' . $view . '/view.html.php');
            self::assertSame(1, preg_match('/\$useMenuItemParams\s*=.*?;/s', $source, $match));
            $check = eval('namespace JemProject\\Tests\\DetailMenu; use JemDetailMenu;'
                . 'return static function ($menuitem) { $item = $venue = $category = (object) ["id" => 7];'
                . $match[0] . 'return $useMenuItemParams; };');
            foreach ([7 => true, '7:alias' => true, '8:other' => false, '7wrong' => false] as $id => $expected) {
                self::assertSame($expected, $check((object) ['query' => [
                    'option' => 'com_jem', 'view' => $view, 'id' => $id,
                ]]), $view . ':' . $id);
            }
            self::assertFalse($check(null));
            self::assertFalse($check((object) ['query' => ['option' => 'com_jem', 'view' => 'search']]));
        }
    }

    /** @return void Execute the actual route-helper lookup, including raw alias keys and invalid menu records. */
    public function testRouteHelperLooksUpLegacyMenuIds(): void
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/site/helpers/route.php');
        self::assertSame(1, preg_match('/protected static function _findItem\([^)]*\)\s*\{.*?\n    \}/s', $source, $match));
        if (!class_exists(RouteLookupProbe::class, false)) {
            eval('namespace JemProject\\Tests\\DetailMenu; use JemDetailMenu; class RouteLookupProbe {'
                . 'protected static $lookup; const ARTIFICALID = 0;'
                . 'public static function find($needles) { self::$lookup = null; return self::_findItem($needles); }'
                . $match[0] . '}');
        }
        foreach (['event', 'venue', 'category'] as $view) {
            Factory::$items = [
                (object) ['id' => 301, 'query' => ['view' => $view, 'id' => '7:old-alias']],
                (object) ['id' => 302, 'query' => ['view' => $view, 'id' => '7wrong']],
                (object) ['id' => 303, 'query' => ['view' => $view, 'id' => '7:calendar', 'layout' => 'calendar']],
                (object) ['id' => 304, 'query' => ['view' => $view, 'id' => '7:custom', 'layout' => 'custom']],
            ];
            self::assertSame(301, RouteLookupProbe::find([$view => [7]]));
            self::assertNull(RouteLookupProbe::find([$view => [8]]));
            Factory::$items = array_slice(Factory::$items, 1);
            self::assertNull(RouteLookupProbe::find([$view => [7]]));
        }
    }

    /** @return void Create JEM Menu stores stable IDs even if the record already has an alias. */
    public function testGeneratedMenuIdsAreNumeric(): void
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/admin/controllers/frontendmenu.php');
        self::assertSame(1, preg_match('/protected function slug\([^)]*\)\s*\{.*?\n    \}/s', $source, $match));
        $method = str_replace('protected function slug', 'public function slug', $match[0]);
        $generator = eval('return new class { ' . $method . ' };');
        self::assertSame('7', $generator->slug((object) ['id' => 7, 'alias' => 'changed-title']));
        self::assertSame('9', $generator->slug((object) ['id' => 9]));
    }

    /** @return void The deprecated helper must also reject numeric prefixes in malformed detail IDs. */
    public function testDeprecatedRouteDoesNotTurnMalformedIdIntoDirectMenu(): void
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/site/helpers/route.php');
        self::assertSame(1, preg_match('/public static function getRoute\([^)]*\)\s*\{.*?\n    \}/s', $source, $match));
        $body = preg_replace('/Log::add\([^;]+;/', '', $match[0]);
        if (!class_exists(LegacyRouteProbe::class, false)) {
            eval('namespace JemProject\\Tests\\DetailMenu; use JemDetailMenu; class LegacyRouteProbe {'
                . 'protected static function _findItem($needles = null) {'
                . 'return ($needles["event"][0] ?? null) === 7 ? 301 : null; }'
                . $body . '}');
        }
        self::assertSame('index.php?Itemid=301', LegacyRouteProbe::getRoute('7:alias'));
        self::assertSame('index.php?option=com_jem&view=event&id=7wrong', LegacyRouteProbe::getRoute('7wrong'));
    }
}

/** Isolated application boundary for the unchanged lookup method's Joomla calls. */
final class Factory
{
    public static array $items = [];

    public static function getApplication(): object
    {
        return new class {
            public function getMenu(string $client): object
            {
                return new class {
                    public function getItems(string $attribute, int $component): array
                    {
                        return Factory::$items;
                    }
                };
            }
        };
    }
}

/** Component identity only; no installed database needed. */
final class ComponentHelper
{
    public static function getComponent(string $name): object
    {
        return (object) ['id' => 42];
    }
}
