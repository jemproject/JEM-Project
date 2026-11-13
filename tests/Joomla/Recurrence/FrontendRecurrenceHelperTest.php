<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Exercise the frontend recurrence permission callback with the real backend helper. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class FrontendRecurrenceHelperTest extends TestCase
{
    /** Load the event model without an administrator application or preloaded backend helper. */
    protected function setUp(): void
    {
        if (!is_file((string) getenv('JEM_TEST_JOOMLA_ROOT') . '/libraries/vendor/autoload.php')) {
            self::markTestSkipped('Set JEM_TEST_JOOMLA_ROOT to a Joomla 5/6 tree.');
        }

        require JEM_TEST_ROOT . '/tests/Support/specialday_calendar_bootstrap.php';
        require_once JEM_TEST_ROOT . '/site/factory.php';
        require_once JEM_TEST_ROOT . '/site/classes/frontendaccess.class.php';
        self::assertFalse(class_exists('JemHelperBackend', false));
        require_once JEM_TEST_ROOT . '/admin/models/event.php';
    }

    /** @return iterable<string, array{array, array, array, bool}> Schedule changes and delete constraints. */
    public static function scenarios(): iterable
    {
        $policies = [
            'publisher without deletion' => [['jem.events.access'], [], false],
            'authorised deletion' => [['jem.events.access', 'jem.events.delete'], [], true],
            'delete without component resource access' => [['jem.events.delete'], [], false],
            'active registration blocks deletion' => [
                ['jem.events.access', 'jem.events.delete'],
                [['id' => 1, 'event' => 2, 'status' => 1]],
                false,
            ],
        ];

        foreach (['time' => ['times' => '19:30:00'], 'date' => ['dates' => '2030-12-07']] as $kind => $change) {
            foreach ($policies as $name => [$grants, $registrations, $deleteAllowed]) {
                yield $kind . ': ' . $name => [$change, $grants, $registrations, $deleteAllowed];
            }
        }
    }

    /**
     * Build a confirmation using the actual model callback and retain deletion safeguards.
     *
     * @param array $change Changed date or time.
     * @param array $grants Component permissions granted to the fixture user.
     * @param array $registrations Existing child registrations.
     * @param bool $deleteAllowed Expected availability of the delete decision.
     * @return void
     */
    #[DataProvider('scenarios')]
    public function testFrontendConfirmationKeepsDeleteRestrictions(
        array $change,
        array $grants,
        array $registrations,
        bool $deleteAllowed
    ): void {
        $user = $this->getMockBuilder(JemUser::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['authorise', 'getAccessDecision', 'getAuthorisedViewLevels'])
            ->getMock();
        $user->id = 17;
        $user->guest = 0;
        $user->method('getAuthorisedViewLevels')->willReturn([1]);
        $user->method('authorise')->willReturnCallback(
            static fn ($action, $asset = null): bool => $asset === 'com_jem' && in_array($action, $grants, true)
        );
        $user->method('getAccessDecision')->willReturn(JemAccessDecision::allow('test', 'test', 'edit', 'event'));
        (new ReflectionProperty(JemUserAbstract::class, 'instances_jemuser'))->setValue(null, [17 => $user]);
        Factory::$application = new class ($user) {
            public function __construct(private object $user)
            {
            }

            public function isClient($client): bool
            {
                return $client === 'site';
            }

            public function getSession(): object
            {
                return $this;
            }

            public function get($key): ?object
            {
                return $key === 'user' ? $this->user : null;
            }

            public function getLanguage(): object
            {
                return Factory::$language;
            }
        };

        $model = (new ReflectionClass(JemModelEvent::class))->newInstanceWithoutConstructor();
        $permission = new ReflectionMethod($model, 'canReplaceRecurrenceChild');
        $root = ['id' => 1, 'created_by' => 17, 'access' => 1, 'recurrence_type' => 2, 'recurrence_number' => 1,
            'recurrence_first_id' => 0, 'dates' => '2030-12-06', 'enddates' => null,
            'times' => '20:00:00', 'endtimes' => null, 'recurrence_limit_date' => '2030-12-27'];
        $children = [];
        foreach ([13, 20, 27] as $index => $day) {
            $children[] = array_replace($root, ['id' => $index + 2, 'recurrence_first_id' => 1,
                'dates' => '2030-12-' . $day]);
        }
        $plan = JemRecurrenceChange::plan(
            ['root' => $root, 'children' => $children, 'registrations' => $registrations],
            array_replace($root, $change),
            static fn (string $operation, array $record): bool => $permission->invoke($model, $operation, $record),
            '2030-12-01'
        );

        self::assertTrue($plan['required']);
        self::assertSame(3, $plan['count']);
        self::assertTrue($plan['keepAllowed']);
        self::assertSame($deleteAllowed, $plan['deleteAllowed']);
        JemRecurrenceChange::assertDecision($plan, 'keep', $plan['fingerprint']);
        if (!$deleteAllowed) {
            $this->expectException(RuntimeException::class);
        }
        JemRecurrenceChange::assertDecision($plan, 'delete', $plan['fingerprint']);
    }
}
