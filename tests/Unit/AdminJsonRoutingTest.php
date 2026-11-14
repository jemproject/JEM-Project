<?php

declare(strict_types=1);

use JemProject\Tests\AttendeeForm\Application;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AdminJsonRoutingTest extends TestCase
{
    /** @return void Load native Joomla routing without changing the installed site. */
    protected function setUp(): void
    {
        if (!getenv('JEM_TEST_JOOMLA_ROOT')) {
            self::markTestSkipped('Set JEM_TEST_JOOMLA_ROOT to installed Joomla libraries.');
        }
        require JEM_TEST_ROOT . '/tests/Support/ics_import_environment.php';
        require JEM_TEST_ROOT . '/tests/Support/attendee_form_environment.php';
        define('JPATH_COMPONENT', JPATH_ADMINISTRATOR . '/components/com_jem');
        define('JPATH_COMPONENT_SITE', JEM_TEST_ROOT . '/site');
    }

    /** @return iterable AJAX tasks that must reach the original controller methods. */
    public static function allowedTasks(): iterable
    {
        yield ['attendee', 'pricingOptions'];
        yield ['attendee', 'relocationPreview'];
        yield ['attendee', 'relocationConfirm'];
        yield ['event', 'venueConfigurations'];
        yield ['event', 'repairImage'];
    }

    /** @param string $name Controller. @param string $task Action. @return void */
    #[DataProvider('allowedTasks')]
    public function testNativeDiscovery(string $name, string $task): void
    {
        Factory::$application = new Application(['task' => $name . '.' . $task, 'format' => 'json']);
        $controller = BaseController::getInstance('Jem', [
            'base_path' => JEM_TEST_ROOT . '/admin',
            'view_path' => JPATH_COMPONENT . '/views',
        ]);
        self::assertSame('JemController' . ucfirst($name), get_class($controller));
        self::assertSame($task, Factory::getApplication()->input->getCmd('task'));
        self::assertSame(realpath(JEM_TEST_ROOT . '/admin/controllers/' . $name . '.php'),
            (new ReflectionMethod($controller, $task))->getFileName());
    }

    /** @return iterable Ordinary form actions must not become JSON routes. */
    public static function forbiddenTasks(): iterable
    {
        yield ['attendee.save'];
        yield ['attendee.cancel'];
        yield ['attendee.relocationNotify'];
        yield ['event.save'];
        yield ['event.cancel'];
        yield ['event.apply'];
    }

    /** @param string $task Action outside the JSON contract. @return void */
    #[DataProvider('forbiddenTasks')]
    public function testOtherActionsAreRejected(string $task): void
    {
        Factory::$application = new Application(['task' => $task, 'format' => 'json']);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(403);
        BaseController::getInstance('Jem', ['base_path' => JEM_TEST_ROOT . '/admin']);
    }
}
