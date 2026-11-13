<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class SeriesInheritanceTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $root = getenv('JEM_TEST_JOOMLA_ROOT');
        require_once $root . '/libraries/vendor/autoload.php';
        require_once JEM_TEST_ROOT . '/site/classes/seriesinheritance.class.php';
    }

    private function event(): array
    {
        return ['id' => 20, 'recurrence_first_id' => 10, 'inherit' => 1, 'title' => 'Shared',
            'introtext' => 'Description', 'fulltext' => '', 'article_id' => 0, 'locid' => 3,
            'cats' => [2, 3], 'times' => '10:00:00', 'endtimes' => '11:00:00', 'dates' => '2030-02-10',
            'alias' => 'child', 'online_meeting_url' => 'https://example.test/one', 'datimage' => 'one.jpg',
            'attribs' => ['series_inheritance' => ['overrides' => []], 'registration_intro' => 'Own notice']];
    }

    public static function fieldGroups(): array
    {
        return [['title','title','Own title'], ['description','introtext','Own description'],
            ['images','datimage','own.jpg'], ['categories','cats',[7]], ['venue','locid',8],
            ['times','times','14:00:00'], ['online','online_meeting_url','https://example.test/own']];
    }

    #[DataProvider('fieldGroups')]
    public function testEditedGroupStaysIndependentAndCanBeRestored(string $group, string $field, $value): void
    {
        $root = $this->event();
        $child = JemSeriesInheritance::submission([$field => $value], $root, $root);
        $child = array_replace($root, $child);
        self::assertContains($group, JemSeriesInheritance::policy($child)['overrides']);
        $updated = JemSeriesInheritance::propagate($child, $root, [$field => $root[$field]]);
        self::assertSame($value, $updated[$field]);
        $restored = JemSeriesInheritance::submission(['series_inheritance_present' => 1, 'series_inherit' => 1,
            'series_inherit_fields' => array_keys(JemSeriesInheritance::GROUPS), $field => $value], $child, $root);
        self::assertSame($root[$field], $restored[$field]);
        self::assertNotContains($group, JemSeriesInheritance::policy($restored)['overrides']);
    }

    public function testGlobalOffPreservesMembershipAndSkipsAllParentChanges(): void
    {
        $root = $this->event();
        $child = array_replace($root, JemSeriesInheritance::submission(['series_inheritance_present' => 1,
            'series_inherit' => 0, 'series_inherit_fields' => array_keys(JemSeriesInheritance::GROUPS)], $root, $root));
        self::assertSame(0, $child['inherit']);
        self::assertSame(10, $child['recurrence_first_id']);
        self::assertSame($child, JemSeriesInheritance::propagate($child, $root, ['title' => 'New', 'locid' => 9]));
    }

    public function testParentCannotReplaceOccurrenceIdentityOrOtherAttributes(): void
    {
        $child = $this->event();
        $updated = JemSeriesInheritance::propagate($child, $child,
            ['id'=>1,'recurrence_first_id'=>0,'dates'=>'2032-01-01','alias'=>'root','inherit'=>0,'title'=>'Updated',
             'attribs'=>['registration_intro'=>'Wrong notice']]);
        foreach (['id','recurrence_first_id','dates','alias','inherit'] as $field) {
            self::assertSame($child[$field], $updated[$field]);
        }
        self::assertSame('Own notice', $updated['attribs']['registration_intro']);
        self::assertSame('Updated', $updated['title']);
    }

    public function testLegacyCustomDescriptionIsPreservedWithoutTreatingCategoryOrderAsAnOverride(): void
    {
        $root = $this->event(); $child = $root;
        $child['attribs'] = []; $child['introtext'] = 'Existing customization'; $child['cats'] = '3,2';
        self::assertSame(['description'], JemSeriesInheritance::policy($child, $root)['overrides']);
    }
}
