<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class RegistrationWindowContractsTest extends TestCase
{
    public function testViewUsesSharedRegistrationWindowDecisions(): void
    {
        $view = $this->read('/site/views/event/view.html.php');

        self::assertStringContainsString('getEventRegistrationWindowState($item, $timeNow)', $view);
        self::assertStringContainsString('getEventUnregistrationWindowState($item, $timeNow)', $view);
        self::assertStringContainsString('$this->showRegistrationAction = $this->allowRegistration || $hasActiveRegistration', $view);
    }

    public function testClosedWindowSuppressesRegistrationActionsInBothLayouts(): void
    {
        foreach (array(
            '/site/views/event/tmpl/default_attendees.php',
            '/site/views/event/tmpl/responsive/default_attendees.php',
        ) as $path) {
            $layout = $this->read($path);

            self::assertStringContainsString('$showRegistrationAction = $this->print == 0', $layout);
            self::assertStringContainsString('if ($showRegistrationAction)', $layout);
            self::assertStringContainsString('jem-registration-login-prompt', $layout);
        }
    }

    public function testDirectRegistrationRequestsEnforceServerSideWindows(): void
    {
        $model = $this->read('/site/models/event.php');
        $policy = $this->read('/site/classes/registrationaccesspolicy.class.php');

        self::assertStringContainsString('JemHelper::isEventRegistrationOpen($e)', $model);
        self::assertStringContainsString('JemHelper::isEventUnregistrationOpen($e)', $model);
        self::assertStringContainsString('COM_JEM_EVENT_REGISTRATION_CLOSED', $policy);
        self::assertStringContainsString('COM_JEM_ERROR_ANNULATION_NOT_ALLOWED', $policy);
        self::assertStringContainsString('Validate every selected event before writing any series registration', $model);

        $preflight = strpos($model, 'Validate every selected event before writing any series registration');
        $writeLoop = strpos($model, '$reg = $registrations[(int) $e->id]', $preflight);
        self::assertIsInt($preflight);
        self::assertIsInt($writeLoop);
        self::assertLessThan($writeLoop, $preflight);
    }

    public function testRegistrationWindowHelperUsesUtcBoundaries(): void
    {
        $helper = $this->read('/site/helpers/helper.php');

        self::assertStringContainsString('function getEventRegistrationWindowState', $helper);
        self::assertStringContainsString('self::getUtcTimestamp($event->registra_from', $helper);
        self::assertStringContainsString('self::getUtcTimestamp($event->registra_until', $helper);
        self::assertStringContainsString('function getEventUnregistrationWindowState', $helper);
        self::assertStringContainsString('self::getUtcTimestamp($event->unregistra_until', $helper);
        self::assertStringContainsString('self::getEventStartTimestamp($event)', $helper);
        self::assertStringContainsString('function getEventUnregistrationDeadline', $helper);
        self::assertStringContainsString('JemRegistrationAccessPolicy::registrationWindowState(', $helper);
        self::assertStringContainsString('JemRegistrationAccessPolicy::unregistrationWindowState(', $helper);
    }

    public function testEffectiveCancellationDeadlineIsAlwaysShownWhenAvailable(): void
    {
        $view = $this->read('/site/views/event/view.html.php');
        self::assertStringContainsString('getEventUnregistrationDeadline($item)', $view);

        foreach (array(
            '/site/views/event/tmpl/default.php',
            '/site/views/event/tmpl/responsive/default.php',
        ) as $layout) {
            $source = $this->read($layout);
            self::assertStringContainsString(
                '$showCancellationInfo = (int) $this->item->unregistra > 0 && $this->dateUnregistationUntil;',
                $source
            );
        }
    }

    private function read(string $relativePath): string
    {
        $path = JEM_TEST_ROOT . $relativePath;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
