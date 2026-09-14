<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AttendeeReadAccessTest extends TestCase
{
    public function testAttendeeLayoutsAuthoriseTheStoredEventBeforeRendering(): void
    {
        $view = $this->read('/site/views/attendees/view.html.php');
        $eventLookup = strpos($view, '$event = $model->getEvent();');
        $authorisation = strpos($view, 'if (!$model->canManageAttendees($user))');
        $printLayout = strpos($view, "getLayout() == 'print'");
        $addUsersLayout = strpos($view, "getLayout() == 'addusers'");

        self::assertNotFalse($eventLookup);
        self::assertNotFalse($authorisation);
        self::assertNotFalse($printLayout);
        self::assertNotFalse($addUsersLayout);
        self::assertLessThan($authorisation, $eventLookup);
        self::assertLessThan($printLayout, $authorisation);
        self::assertLessThan($addUsersLayout, $authorisation);
        self::assertStringContainsString("Text::_('JERROR_ALERTNOAUTHOR'), 403", $view);
    }

    public function testAttendeeModelUsesThePersistedOwnerAndFailsClosed(): void
    {
        $model = $this->read('/site/models/attendees.php');
        $setId = $this->method($model, 'setId');
        $permission = $this->method($model, 'canManageAttendees');
        $attendeesWhere = $this->method($model, '_buildContentWhere');
        $users = $this->method($model, 'getUsers');
        $registeredUsers = $this->method($model, 'getRegisteredUsers');
        $usersQuery = $this->method($model, '_buildQueryUsers');

        self::assertStringContainsString('$this->_total      = null;', $setId);
        self::assertStringContainsString('$this->_pagination = null;', $setId);
        self::assertStringContainsString('$event = $this->getEvent();', $permission);
        self::assertStringContainsString("\$user->can('edit', 'event', (int) \$event->id, (int) \$event->created_by)", $permission);
        self::assertStringNotContainsString("\$user->can('edit', 'event', \$this->_id, \$user->id)", $model);
        self::assertStringContainsString('$canManage = $this->canManageAttendees($user);', $attendeesWhere);
        self::assertStringContainsString("\$where[] = '1 = 0';", $attendeesWhere);
        self::assertStringContainsString('if (!$this->canManageAttendees())', $users);
        self::assertStringContainsString('if (!$this->canManageAttendees())', $registeredUsers);
        self::assertStringContainsString('$eventId = (int) $this->_id;', $registeredUsers);
        self::assertStringContainsString("\$where[] = '1 = 0';", $usersQuery);
        self::assertStringContainsString("array('u.name', 'u.username', 'r.uregdate', 'r.status', 'r.places')", $model);
        self::assertStringContainsString("strtoupper(\$filter_order_Dir) === 'DESC' ? 'DESC' : 'ASC'", $model);
    }

    public function testAttendeeWriteGuardReusesTheSameStoredEventPolicy(): void
    {
        $source = $this->read('/site/controllers/attendees.php');
        $controller = $this->method($source, 'assertCanManageAttendees');
        $add = $this->method($source, 'attendeeadd');

        self::assertStringContainsString('$event = $model->getEvent();', $controller);
        self::assertStringContainsString('$model->canManageAttendees($user)', $controller);
        self::assertStringContainsString('$modelAttendees->setId((int) $row->id);', $add);
        self::assertStringContainsString('$modelAttendees->getRegisteredUsers();', $add);
        self::assertStringNotContainsString('JemModelAttendees::getRegisteredUsers', $source);
    }

    public function testAttendeePrintLayoutsEscapeStoredValues(): void
    {
        foreach (array(
            '/site/views/attendees/tmpl/print.php',
            '/site/views/attendees/tmpl/responsive/print.php',
        ) as $path) {
            $source = $this->read($path);

            self::assertStringContainsString('$this->escape($regname ? $row->name : $row->username)', $source, $path);
            self::assertStringContainsString('$this->escape($row->email)', $source, $path);
            self::assertStringContainsString('$this->escape((string) $row->comment)', $source, $path);
            self::assertStringContainsString('(int) $row->places', $source, $path);
        }
    }

    public function testPublicAttendeeNamesRespectTheConfiguredAudience(): void
    {
        $view = $this->read('/site/views/event/view.html.php');

        self::assertStringContainsString("\$showAttendeeNames = (int) \$this->settings->get('event_show_attendeenames', 2);", $view);
        self::assertStringContainsString('$isAttending = is_object($registration) && (int) $registration->status === 1;', $view);
        self::assertStringContainsString('$userId && ($showAttendeeNames !== 2 || $isAttending)', $view);

        foreach (array(
            '/site/views/event/tmpl/default_attendees.php',
            '/site/views/event/tmpl/responsive/default_attendees.php',
        ) as $path) {
            $source = $this->read($path);

            self::assertStringContainsString('!is_object($this->registration) || (int) $this->registration->status !== 1', $source, $path);
            self::assertStringContainsString('$displayName = $this->escape((string) $register->name);', $source, $path);
            self::assertStringNotContainsString("' . \$register->name . '</span>", $source, $path);
        }
    }

    public function testGlobalRegistrationReadersKeepTheirDedicatedManagerGate(): void
    {
        foreach (array(
            '/site/views/attendeeregistrations/view.html.php',
            '/site/views/attendeeregistrations/view.raw.php',
            '/site/controllers/attendeeregistrations.php',
        ) as $path) {
            $source = $this->read($path);

            self::assertStringContainsString("authorise('core.manage', 'com_jem')", $source, $path);
            self::assertStringContainsString("authorise('jem.attendees.manage', 'com_jem')", $source, $path);
        }
    }

    private function method(string $php, string $name): string
    {
        $start = strpos($php, 'function ' . $name . '(');
        self::assertNotFalse($start, 'Method not found: ' . $name);

        $end = strpos($php, "\n    /**", $start);

        if ($end === false) {
            $end = strlen($php);
        }

        return substr($php, $start, $end - $start);
    }

    private function read(string $relativePath): string
    {
        $path = JEM_TEST_ROOT . $relativePath;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
