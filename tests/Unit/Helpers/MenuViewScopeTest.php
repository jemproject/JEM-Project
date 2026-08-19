<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/classes/menuviewscope.class.php';

final class MenuViewScopeTest extends TestCase
{
    /**
     * @return iterable<string, array{array<string, mixed>, string, mixed, bool}>
     */
    public static function scopeProvider(): iterable
    {
        yield 'matching list view without record id' => array(
            array('option' => 'com_jem', 'view' => 'eventslist'),
            'eventslist',
            null,
            true,
        );
        yield 'event reached from events list' => array(
            array('option' => 'com_jem', 'view' => 'eventslist'),
            'event',
            42,
            false,
        );
        yield 'category reached from events list' => array(
            array('option' => 'com_jem', 'view' => 'eventslist'),
            'category',
            4,
            false,
        );
        yield 'direct event menu item' => array(
            array('option' => 'com_jem', 'view' => 'event', 'id' => 42),
            'event',
            '42:event-alias',
            true,
        );
        yield 'different event through an event menu item' => array(
            array('option' => 'com_jem', 'view' => 'event', 'id' => 42),
            'event',
            '43:other-event',
            false,
        );
        yield 'different category through a category menu item' => array(
            array('option' => 'com_jem', 'view' => 'category', 'id' => '4:parent'),
            'category',
            '5:child',
            false,
        );
        yield 'non-JEM menu item' => array(
            array('option' => 'com_content', 'view' => 'event'),
            'event',
            42,
            false,
        );
    }

    /**
     * @param array<string, mixed> $query
     */
    #[DataProvider('scopeProvider')]
    public function testPageTextOnlyMatchesItsOwningMenuView(
        array $query,
        string $view,
        mixed $requestId,
        bool $expected
    ): void {
        self::assertSame($expected, JemMenuViewScope::matches($query, $view, $requestId));
    }
}
