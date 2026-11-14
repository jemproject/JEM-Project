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

    /**
     * Cover independent permissions, active registrations and both editor languages.
     *
     * @return iterable Named permission and registration scenarios.
     */
    public static function confirmationMessageCases(): iterable
    {
        $permissions = [
            'both actions' => [true, true, ''],
            'keep only' => [true, false, 'COM_JEM_RECURRENCE_CHANGE_KEEP_ONLY'],
            'delete only' => [false, true, 'COM_JEM_RECURRENCE_CHANGE_KEEP_PERMISSION'],
            'neither action' => [false, false, 'COM_JEM_RECURRENCE_CHANGE_PERMISSION'],
        ];
        foreach (['site', 'admin'] as $client) {
            foreach ($permissions as $name => [$edit, $delete, $message]) {
                foreach ([false, true] as $active) {
                    yield $client . ' ' . $name . ($active ? ' with registrations' : '') =>
                        [$client, $edit, $delete, $active, $message];
                }
            }
        }
    }

    /**
     * Explain each blocked action without changing the valid confirmation choices.
     *
     * @param string $client Editor language directory.
     * @param bool $edit Whether the second child can be edited.
     * @param bool $delete Whether the second child can be deleted.
     * @param bool $active Whether a child has an active registration.
     * @param string $permissionMessage Expected permission explanation key, or empty.
     * @return void
     */
    #[DataProvider('confirmationMessageCases')]
    public function testConfirmationExplainsAvailableActions(
        string $client,
        bool $edit,
        bool $delete,
        bool $active,
        string $permissionMessage
    ): void
    {
        $language = new Joomla\CMS\Language\Language('en-GB');
        self::assertTrue($language->load('com_jem', JEM_TEST_ROOT . '/' . $client, 'en-GB', true));
        Joomla\CMS\Factory::$language = $language;

        $snapshot = $this->snapshot();
        if ($active) {
            $snapshot['registrations'][] = ['id' => 1, 'event' => 101, 'status' => 1, 'waiting' => 0];
        }
        // One restricted child must be enough, even if all others allow both actions.
        $can = static fn ($operation, $row) => $row['id'] === 101 || ($operation === 'edit' ? $edit : $delete);
        $plan = $this->plan($snapshot, ['times' => '11:00'], $can);
        self::assertTrue($plan['required']);
        self::assertSame($edit, $plan['keepAllowed']);
        self::assertSame($delete && !$active, $plan['deleteAllowed']);
        $unrestricted = $this->plan($snapshot, ['times' => '11:00']);
        self::assertSame($unrestricted['fingerprint'], $plan['fingerprint']);

        $expected = [];
        if ($active) {
            $expected[] = Joomla\CMS\Language\Text::_('COM_JEM_RECURRENCE_CHANGE_ACTIVE');
        }
        if ($permissionMessage !== '') {
            $text = Joomla\CMS\Language\Text::_($permissionMessage);
            self::assertNotSame($permissionMessage, $text, 'The editor must have a translated explanation.');
            $expected[] = $text;
        }
        self::assertSame(implode(' ', $expected), $plan['blocked']);

        foreach (['keep' => $edit, 'delete' => $delete && !$active] as $choice => $allowed) {
            $error = null;
            try {
                JemRecurrenceChange::assertDecision($plan, $choice, $plan['fingerprint']);
            } catch (RuntimeException $exception) {
                $error = $exception;
            }
            if ($allowed) {
                self::assertNull($error, $choice . ' must remain available.');
            } else {
                self::assertInstanceOf(RuntimeException::class, $error, $choice . ' must remain blocked.');
                $expectedError = $choice === 'delete' ? $plan['blocked']
                    : Joomla\CMS\Language\Text::_('COM_JEM_RECURRENCE_CHANGE_PERMISSION');
                self::assertSame($expectedError, $error->getMessage());
            }
        }
    }

    /**
     * Keep frontend and backend wording aligned for partial permission restrictions.
     *
     * @return void
     */
    public function testPartialPermissionMessagesDescribeTheAffectedAction(): void
    {
        $expected = [
            'COM_JEM_RECURRENCE_CHANGE_KEEP_ONLY' => 'You can keep the existing occurrences, but you do not have permission to delete every affected event.',
            'COM_JEM_RECURRENCE_CHANGE_KEEP_PERMISSION' => 'You do not have permission to keep all existing occurrences as independent events.',
        ];
        foreach (['site', 'admin'] as $client) {
            $strings = parse_ini_file(JEM_TEST_ROOT . '/' . $client . '/language/en-GB/com_jem.ini', false, INI_SCANNER_RAW);
            self::assertIsArray($strings);
            foreach ($expected as $key => $text) {
                self::assertSame($text, $strings[$key] ?? null);
            }
        }
    }

    /**
     * A hidden Delete choice for a series without children is not a permission failure.
     *
     * @return void
     */
    public function testNoChildrenDoNotProduceAPermissionWarning(): void
    {
        $snapshot = $this->snapshot();
        $snapshot['children'] = [];
        $plan = $this->plan($snapshot, ['times' => '11:00'], static fn () => false);
        self::assertSame(0, $plan['count']);
        self::assertTrue($plan['keepAllowed']);
        self::assertFalse($plan['deleteAllowed']);
        self::assertSame('', $plan['blocked']);
        JemRecurrenceChange::assertDecision($plan, 'keep', $plan['fingerprint']);
    }

    /**
     * Cancelled registrations remove only the registration block, never an ACL block.
     *
     * @return void
     */
    public function testCancelledRegistrationsPreservePermissionExplanations(): void
    {
        $snapshot = $this->snapshot();
        $snapshot['registrations'][] = ['id' => 1, 'event' => 101, 'status' => -1, 'waiting' => 0];
        $plan = $this->plan($snapshot, ['times' => '11:00']);
        self::assertSame('', $plan['blocked']);
        self::assertTrue($plan['deleteAllowed']);
        JemRecurrenceChange::assertDecision($plan, 'delete', $plan['fingerprint']);

        $plan = $this->plan($snapshot, ['times' => '11:00'], static fn ($operation) => $operation === 'edit');
        self::assertTrue($plan['keepAllowed']);
        self::assertFalse($plan['deleteAllowed']);
        self::assertSame(Joomla\CMS\Language\Text::_('COM_JEM_RECURRENCE_CHANGE_KEEP_ONLY'), $plan['blocked']);
        JemRecurrenceChange::assertDecision($plan, 'keep', $plan['fingerprint']);
    }
}
