<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class RecurrenceContentPropagationTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once getenv('JEM_TEST_JOOMLA_ROOT') . '/libraries/vendor/autoload.php';
        require_once JEM_TEST_ROOT . '/site/classes/seriesinheritance.class.php';
    }

    public function testCompleteOccurrenceReceivesVenueStatusAndOnlineFields(): void
    {
        $child = ['id'=>20,'alias'=>'own','dates'=>'2030-02-03','enddates'=>null,'times'=>'10:00:00',
            'endtimes'=>null,'created_by'=>7,'recurrence_first_id'=>10,'locid'=>1,'title'=>'Old',
            'event_status'=>'scheduled','online_meeting_url'=>'','online_meeting_label'=>'','cats'=>[2]];
        $changes = ['locid'=>4,'event_status'=>'postponed','online_meeting_url'=>'https://example.test/join',
            'online_meeting_label'=>'Join','title'=>'New','cats'=>[3,4],'alias'=>'parent-alias','dates'=>'2030-01-01',
            'times'=>'15:00:00','created_by'=>9];
        $actual = JemSeriesInheritance::propagate($child, [], $changes, false);
        foreach (['locid','event_status','online_meeting_url','online_meeting_label','title','cats'] as $field) {
            self::assertSame($changes[$field], $actual[$field]);
        }
        foreach (['id','alias','dates','enddates','times','endtimes','created_by','recurrence_first_id'] as $field) {
            self::assertSame($child[$field], $actual[$field]);
        }
    }
}
