<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class RecurrenceChangeTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $root = getenv('JEM_TEST_JOOMLA_ROOT');
        require_once $root . '/libraries/vendor/autoload.php';
        spl_autoload_register(static function ($class) use ($root) {
            if (str_starts_with($class, 'Joomla\\CMS\\')) {
                $file = $root . '/libraries/src/' . str_replace('\\', '/', substr($class, 11)) . '.php';
                if (is_file($file)) { require_once $file; }
            }
        });
        if (!defined('JPATH_ROOT')) { define('JPATH_ROOT', $root); }
        if (!defined('JPATH_BASE')) { define('JPATH_BASE', $root); }
        require_once $root . '/includes/defines.php';
        Joomla\CMS\Factory::$language = new Joomla\CMS\Language\Language('en-GB');
        require_once JEM_TEST_ROOT . '/site/classes/recurrencechange.class.php';
    }

    private function snapshot(): array
    {
        $root = ['id'=>100, 'recurrence_first_id'=>0, 'recurrence_type'=>2, 'recurrence_number'=>1,
            'dates'=>'2030-11-10','enddates'=>null,'times'=>'10:00:00','endtimes'=>null,
            'recurrence_limit_date'=>'2030-11-24','recurrence_byday'=>'','attribs'=>'{}'];
        return ['root'=>$root, 'children'=>[
            array_replace($root,['id'=>101,'recurrence_first_id'=>100,'dates'=>'2020-11-17']),
            array_replace($root,['id'=>102,'recurrence_first_id'=>100,'dates'=>'2030-11-24']),
        ], 'registrations'=>[]];
    }

    private function plan(array $snapshot, array $changes = [], ?callable $can = null): array
    {
        return JemRecurrenceChange::plan($snapshot, array_replace($snapshot['root'], $changes),
            $can ?? static fn()=>true, '2026-11-07');
    }

    public function testUnchangedAndContentOnlyEditsDoNotNeedConfirmation(): void
    {
        self::assertSame(['required'=>false],$this->plan($this->snapshot()));
        self::assertSame(['required'=>false],$this->plan($this->snapshot(),['title'=>'New title','recurrence_counter'=>20,'recurrence_first_id'=>999]));
    }

    public static function changedRules(): array
    {
        return array_map(static fn($x)=>[$x], [ ['recurrence_type'=>1],['recurrence_type'=>0],
            ['recurrence_number'=>2],['recurrence_limit_date'=>'2030-12-01'],['recurrence_byday'=>'MO,WE'],
            ['dates'=>'2030-11-11'],['times'=>'11:00'],['endtimes'=>'12:00'],['enddates'=>'2030-11-11'],
            ['attribs'=>'{"recurrence_exclusions":["day:3"]}'] ]);
    }

    #[DataProvider('changedRules')]
    public function testEachEffectiveScheduleChangeRequiresAnExplicitDecision(array $change): void
    {
        $plan=$this->plan($this->snapshot(),$change);
        self::assertTrue($plan['required']);
        self::assertSame(2,$plan['count']);
        self::assertSame(1,$plan['past']);
        self::assertSame(1,$plan['future']);
        JemRecurrenceChange::assertDecision($plan,'keep',$plan['fingerprint']);
        JemRecurrenceChange::assertDecision($plan,'delete',$plan['fingerprint']);
        self::assertTrue($plan['deleteAllowed']);
    }

    public static function statuses(): array
    {
        return [[1,0,true],[1,1,true],[0,0,true],[-1,0,false],[-1,1,false],[null,0,true],[2,0,true]];
    }

    #[DataProvider('statuses')]
    public function testOnlyCancelledRegistrationsAllowDeletion($status,int $waiting,bool $blocked): void
    {
        $snapshot=$this->snapshot();
        $snapshot['registrations'][]=['id'=>1,'event'=>101,'status'=>$status,'waiting'=>$waiting];
        $plan=$this->plan($snapshot,['recurrence_type'=>1]);
        self::assertSame(!$blocked,$plan['deleteAllowed']);
        JemRecurrenceChange::assertDecision($plan,'keep',$plan['fingerprint']);
        if($blocked) { $this->expectException(RuntimeException::class); }
        JemRecurrenceChange::assertDecision($plan,'delete',$plan['fingerprint']);
    }

    public function testStalePreviewIsRejectedAfterAnotherRegistration(): void
    {
        $snapshot=$this->snapshot();
        $before=$this->plan($snapshot,['recurrence_type'=>1]);
        $snapshot['registrations'][]=['id'=>1,'event'=>101,'status'=>1];
        $after=$this->plan($snapshot,['recurrence_type'=>1]);
        $this->expectException(RuntimeException::class);
        JemRecurrenceChange::assertDecision($after,'keep',$before['fingerprint']);
    }

    public function testChildDoesNotOfferSiblingDeletion(): void
    {
        $snapshot=$this->snapshot();
        $snapshot['root']=$snapshot['children'][0];
        $snapshot['root']['inherit']=0;
        $snapshot['children']=[];
        $plan=$this->plan($snapshot,['recurrence_type'=>1]);
        self::assertTrue($plan['child']);
        self::assertFalse($plan['deleteAllowed']);
        JemRecurrenceChange::assertDecision($plan,'keep',$plan['fingerprint']);
        $this->expectException(RuntimeException::class);
        JemRecurrenceChange::assertDecision($plan,'delete',$plan['fingerprint']);
    }

    public function testNoChoiceCannotSave(): void
    {
        $plan=$this->plan($this->snapshot(),['recurrence_type'=>1]);
        $this->expectException(RuntimeException::class);
        JemRecurrenceChange::assertDecision($plan,'',$plan['fingerprint']);
    }

    public function testPermissionsAreCheckedForEveryChild(): void
    {
        $plan=$this->plan($this->snapshot(),['recurrence_type'=>1],static fn($op,$row)=>$row['id']!==102);
        self::assertFalse($plan['keepAllowed']);
        self::assertFalse($plan['deleteAllowed']);
        $this->expectException(RuntimeException::class);
        JemRecurrenceChange::assertDecision($plan,'keep',$plan['fingerprint']);
    }

    public function testEquivalentTimesAndDayOrderDoNotRestart(): void
    {
        $snapshot=$this->snapshot();
        $snapshot['root']['recurrence_byday']='MO,WE';
        self::assertFalse($this->plan($snapshot,['times'=>'10:00','recurrence_byday'=>'WE,MO'])['required']);
    }
}
