<?php

declare(strict_types=1);

namespace JemProject\Tests\Unit\Helpers;

use PHPUnit\Framework\TestCase;

/** Exercise URL contracts without starting Joomla or altering an installed site. */
final class CalendarDayLinkTest extends TestCase
{
    /** @return void Load the production helper. */
    public static function setUpBeforeClass(): void
    {
        require_once JEM_TEST_ROOT . '/site/helpers/calendardaylink.php';
    }

    /** @return void Menu, archive, scope and explicit layouts survive entry into Day. */
    public function testDrillDownContext(): void
    {
        foreach (['', '&catid=7', '&locid=9', '&topcat=3'] as $scope) {
            foreach (['', 'archive', 'event.remove'] as $task) {
                foreach (['', '&layout=timeline&mode=timeline'] as $layout) {
                    $link = \JemCalendarDayLink::build(
                        'index.php?option=com_jem&view=day&id=20270115' . $scope . $layout,
                        101, $task
                    );
                    parse_str(parse_url($link, PHP_URL_QUERY), $query);
                    self::assertSame('101', $query['Itemid']);
                    self::assertSame('20270115', $query['id']);
                    self::assertSame($task === 'archive' ? 'archive' : null, $query['task'] ?? null);
                    self::assertSame($layout ? 'timeline' : 'default', $query['layout']);
                    self::assertSame('1', $query['timeline_days_to_show']);
                    self::assertSame('0', $query['limitstart']);
                    self::assertSame('', $query['filter_search']);
                    self::assertSame('1', $query['jemcal']);
                    if ($scope) {
                        parse_str(ltrim($scope, '&'), $expected);
                        self::assertSame($expected, array_intersect_key($query, $expected));
                    }
                }
            }
        }
    }

    /** @return void Only explicit positive numeric scope survives; arbitrary tasks/tokens never do. */
    public function testScopeIsWhitelisted(): void
    {
        $input = new class {
            public function getInt($key, $default) { return ['topcat' => 7, 'jemcal' => 1, 'calhide' => 1][$key] ?? $default; }
            public function get($key, $default, $type) { return ['calcats' => '2,2,-3,4 OR 1=1,5', 'calvenues' => '9', 'task' => 'event.remove', 'token' => 'secret'][$key] ?? $default; }
        };
        self::assertSame(['topcat' => 7, 'jemcal' => 1, 'calhide' => 1, 'calcats' => '2,5', 'calvenues' => '9'],
            \JemCalendarDayLink::context($input));
    }

    /** @return void Only actual calendar menus identify source policy. */
    public function testSourceMenuRecognition(): void
    {
        foreach (['calendar', 'weekcal', 'annualcalendar', 'category', 'venue'] as $view) {
            self::assertSame($view, \JemCalendarDayLink::sourceView([
                'option' => 'com_jem', 'view' => $view, 'layout' => 'calendar',
            ]));
        }
        self::assertSame('', \JemCalendarDayLink::sourceView(['option' => 'com_content', 'view' => 'calendar']));
        self::assertSame('', \JemCalendarDayLink::sourceView(['option' => 'com_jem', 'view' => 'venue']));
        self::assertSame('', \JemCalendarDayLink::sourceView([]));
    }

    /** @return void Reject non-Day targets. */
    public function testRejectUnrelatedTarget(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        \JemCalendarDayLink::build('index.php?option=com_content&view=article&id=7', 101, '');
    }
}

