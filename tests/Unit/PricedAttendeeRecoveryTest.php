<?php

declare(strict_types=1);

use JemProject\Tests\AttendeeForm\Application;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Explicit permission fixture; pricing policy and Joomla routing remain actual code. */
final class JemHelperBackend
{
    public static bool $allowed = false;
    /** @param string $permission Required capability. @return bool Fixture permission. */
    public static function canManage($permission): bool { return self::$allowed; }
}

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class PricedAttendeeRecoveryTest extends TestCase
{
    /** @return void Load Joomla and checkout helpers without SQL. */
    protected function setUp(): void
    {
        if (!getenv('JEM_TEST_JOOMLA_ROOT')) {
            self::markTestSkipped('Set JEM_TEST_JOOMLA_ROOT to installed Joomla libraries.');
        }
        require JEM_TEST_ROOT . '/tests/Support/ics_import_environment.php';
        require JEM_TEST_ROOT . '/tests/Support/attendee_form_environment.php';
        require JEM_TEST_ROOT . '/admin/helpers/attendeeform.php';
        require JEM_TEST_ROOT . '/site/classes/featurepolicy.class.php';
        define('JPATH_COMPONENT', JPATH_ADMINISTRATOR . '/components/com_jem');
        Factory::$application = new Application(['task' => 'attendee.pricingOptions', 'format' => 'json']);
    }

    /** @return void Native Joomla discovery must find the existing controller via the narrow JSON entry. */
    public function testJsonDiscovery(): void
    {
        $controller = BaseController::getInstance('Jem', ['base_path' => JEM_TEST_ROOT . '/admin']);
        self::assertInstanceOf(JemControllerAttendee::class, $controller);
        self::assertSame('pricingOptions', Factory::getApplication()->input->getCmd('task'));
        self::assertSame(realpath(JEM_TEST_ROOT . '/admin/controllers/attendee.php'), (new ReflectionMethod($controller, 'pricingOptions'))->getFileName());
    }

    /** @return iterable Other tasks must not be enabled through the new JSON route. */
    public static function forbiddenTasks(): iterable
    {
        yield ['save'];
        yield ['cancel'];
        yield ['relocationConfirm'];
    }

    /** @param string $task Forbidden action. @return void */
    #[DataProvider('forbiddenTasks')]
    public function testOtherJsonTasksRemainUnavailable(string $task): void
    {
        Factory::getApplication()->input->set('task', 'attendee.' . $task);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(403);
        BaseController::getInstance('Jem', ['base_path' => JEM_TEST_ROOT . '/admin']);
    }

    /** @return iterable Denied attendee permission or disallowed Essential profile. */
    public static function guards(): iterable
    {
        yield 'permission denied' => [false];
        yield 'pricing profile denied' => [true];
    }

    /** @param bool $permission Attendee permission. @return void */
    #[DataProvider('guards')]
    public function testLookupRetainsGuards(bool $permission): void
    {
        JemHelperBackend::$allowed = $permission;
        $controller = BaseController::getInstance('Jem', ['base_path' => JEM_TEST_ROOT . '/admin']);
        $this->expectException(Exception::class);
        $this->expectExceptionCode(403);
        $controller->pricingOptions();
    }

    /** @return void Restore quantities only on current catalogue entries, retaining server inventory and prices. */
    public function testViewRestoresQuantitiesWithoutChangingInventory(): void
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/admin/views/attendee/view.html.php');
        $start = strpos($source, '        if ($this->formDraft) {');
        $end = strpos($source, '        $this->registrationChanges', $start);
        $body = substr($source, $start, $end - $start);
        $view = new class {
            public array $formDraft = ['admissions' => [4 => 3, 999 => 9], 'capacity_areas' => ['3:8' => 2]];
            public object $pricing;
            public object $capacity;
        };
        $view->pricing = (object) ['options' => [(object) ['id' => 4, 'quantity' => 1, 'available' => 5, 'unit_gross' => 12]]];
        $view->capacity = (object) ['options' => [['key' => '3:8', 'current_quantity' => 0, 'remaining' => 5, 'input_max' => 5]]];
        $apply = eval('return function () {' . $body . '};');
        $apply->call($view);
        self::assertCount(1, $view->pricing->options);
        self::assertSame(3, $view->pricing->options[0]->quantity);
        self::assertSame(5, $view->pricing->options[0]->available);
        self::assertSame(12, $view->pricing->options[0]->unit_gross);
        self::assertSame(2, $view->capacity->options[0]['current_quantity']);
        self::assertSame(5, $view->capacity->options[0]['remaining']);
    }

    /** @return void A rejected change of priced-order holder must not unlock or display another holder. */
    public function testViewKeepsExistingPricedHolder(): void
    {
        JemAttendeeFormHelper::remember(['uid' => 99, 'comment' => 'Retry'], 10, 9);
        $source = file_get_contents(JEM_TEST_ROOT . '/admin/views/attendee/view.html.php');
        $start = strpos($source, '        $this->formDraft =');
        $end = strpos($source, '        $model =', $start);
        $body = substr($source, $start, $end - $start);
        $view = new class { public array $formDraft = []; public int $event = 10; };
        $row = (object) ['id' => 9, 'event' => 10, 'uid' => 20, 'event_pricing_mode' => 'single'];
        $apply = eval('return function ($row, $id) {' . $body . 'return $row; };');
        $restored = $apply->call($view, $row, 9);
        self::assertSame(20, $restored->uid);
        self::assertSame('Retry', $restored->comment);
        self::assertArrayNotHasKey('uid', $view->formDraft);
    }
}
