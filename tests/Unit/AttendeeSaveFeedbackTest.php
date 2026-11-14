<?php

declare(strict_types=1);

use JemProject\Tests\AttendeeForm\Application;
use JemProject\Tests\AttendeeForm\Controller;
use JemProject\Tests\AttendeeForm\Dispatcher;
use JemProject\Tests\AttendeeForm\Model;
use Joomla\CMS\Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Actual save/cancel methods and draft helper; SQL, session authentication and delivery are fixture boundaries. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AttendeeSaveFeedbackTest extends TestCase
{
    private Application $app;

    /** @return void Load checkout code with installed Joomla libraries. */
    protected function setUp(): void
    {
        if (!getenv('JEM_TEST_JOOMLA_ROOT')) {
            self::markTestSkipped('Set JEM_TEST_JOOMLA_ROOT to installed Joomla libraries.');
        }
        require JEM_TEST_ROOT . '/tests/Support/ics_import_environment.php';
        require JEM_TEST_ROOT . '/tests/Support/attendee_form_environment.php';
        require JEM_TEST_ROOT . '/admin/helpers/attendeeform.php';
        Factory::$language->load('com_jem', JEM_TEST_ROOT . '/admin', 'en-GB', true);
        $this->app = new Application();
        Factory::$application = $this->app;
        JemProject\Tests\AttendeeForm\loadController();
    }

    /** @param array $post Submitted form. @return Controller Controller with controlled store boundary. */
    private function controller(array $post): Controller
    {
        $state = $this->app->state;
        $messages = $this->app->messages;
        $this->app = new Application($post);
        $this->app->state = $state;
        $this->app->messages = $messages;
        Factory::$application = $this->app;
        $controller = new Controller();
        $controller->model = new Model();
        return $controller;
    }

    /** @return iterable All save actions for new and existing registrations. */
    public static function tasks(): iterable
    {
        foreach (['save', 'apply', 'save2new', 'save2copy'] as $task) {
            foreach ([0, 9] as $id) {
                yield "$task id=$id" => [$task, $id];
            }
        }
    }

    /** @param string $task Save action. @param int $id Source registration. @return void */
    #[DataProvider('tasks')]
    public function testFailureReturnsToCorrectEditorWithoutNotifications(string $task, int $id): void
    {
        $post = ['task' => $task, 'id' => $id, 'event' => 10, 'uid' => 20, 'status' => 1,
            'places' => 2, 'comment' => 'Correct this <value>', 'sendemail' => 1, 'seriesbooking' => 1,
            'admissions' => [4 => 2], 'capacity_areas' => ['3:8' => 2], 'csrf-secret' => 'never retain',
            'total_paid' => 999, 'eventtitle' => 'Untrusted title'];
        $controller = $this->controller($post);
        $controller->save();
        $target = $task === 'save2copy' ? 0 : $id;
        self::assertSame('index.php?option=com_jem&view=attendee&hidemainmenu=1&id=' . $target . '&eventid=10', $controller->redirect[0]);
        self::assertSame([['message' => 'Choose an admission type.', 'type' => 'error']], $this->app->messages);
        $draft = JemAttendeeFormHelper::getDraft(10, $target);
        self::assertSame(20, $draft['uid']);
        self::assertSame(2, $draft['places']);
        self::assertSame($post['comment'], $draft['comment']);
        self::assertSame([4 => 2], $draft['admissions']);
        self::assertSame(['3:8' => 2], $draft['capacity_areas']);
        self::assertSame(1, $draft['sendemail']);
        self::assertSame(1, $draft['seriesbooking']);
        self::assertArrayNotHasKey('csrf-secret', $draft);
        self::assertArrayNotHasKey('total_paid', $draft);
        self::assertArrayNotHasKey('eventtitle', $draft);
        self::assertArrayNotHasKey('task', $draft);
        self::assertCount(1, $controller->model->stored);
        self::assertSame([], Dispatcher::$events);
    }

    /** @return void Do not overwrite the requested status with the internal waiting-list staging value. */
    public function testFailedPromotionRetainsRequestedStatus(): void
    {
        $controller = $this->controller(['task' => 'save', 'id' => 9, 'event' => 10, 'uid' => 20, 'status' => 1]);
        $controller->model->old = (object) ['id' => 9, 'event' => 10, 'uid' => 20, 'status' => 1, 'waiting' => 1];
        $controller->save();
        self::assertSame(2, $controller->model->stored[0]['status']);
        self::assertSame(1, JemAttendeeFormHelper::getDraft(10, 9)['status']);
    }

    /** @return iterable Already queued errors, model exceptions and fallbacks. */
    public static function errors(): iterable
    {
        yield 'duplicate' => ['Rejected', 'Rejected', [], [['message' => 'Rejected', 'type' => 'warning']]];
        yield 'queued only' => ['', 'Rejected', [], [['message' => 'Rejected', 'type' => 'warning']]];
        yield 'fallback' => ['', null, [], [['message' => 'The registration could not be saved. Review the form and try again.', 'type' => 'error']]];
        yield 'old warning is not this error' => ['', null, [['message' => 'Old', 'type' => 'warning']], [['message' => 'Old', 'type' => 'warning'], ['message' => 'The registration could not be saved. Review the form and try again.', 'type' => 'error']]];
        yield 'exception' => ['exception', null, [], [['message' => 'Rejected', 'type' => 'error']]];
    }

    /** @param mixed $error Model result. @param string|null $queued Message emitted during store. @param array $before Old queue. @param array $expected Final queue. @return void */
    #[DataProvider('errors')]
    public function testErrorFeedback($error, ?string $queued, array $before, array $expected): void
    {
        $controller = $this->controller(['task' => 'save', 'event' => 10]);
        $this->app->messages = $before;
        $controller->model->error = $error === 'exception' ? new RuntimeException('Rejected') : $error;
        $controller->model->queued = $queued;
        $controller->save();
        self::assertSame($expected, $this->app->messages);
    }

    /** @return void Drafts cannot leak into a different editor, actor or persisted row. */
    public function testDraftIsolationAndDisplayClone(): void
    {
        JemAttendeeFormHelper::remember(['uid' => 20, 'status' => 1, 'places' => 3, 'comment' => '<b>Literal</b>', 'event' => 99, 'id' => 50], 10, 9);
        self::assertSame([], JemAttendeeFormHelper::getDraft(11, 9));
        self::assertSame([], JemAttendeeFormHelper::getDraft(10, 0));
        $this->app->actor = 8;
        self::assertSame([], JemAttendeeFormHelper::getDraft(10, 9));
        $this->app->actor = 7;
        JemAttendeeFormHelper::clear(10, 0);
        $draft = JemAttendeeFormHelper::getDraft(10, 9);
        $row = (object) ['id' => 9, 'event' => 10, 'uid' => 20, 'status' => 0, 'places' => 1, 'comment' => '', 'seriesbooking' => 1];
        $formRow = JemAttendeeFormHelper::restoreRow($row, $draft);
        self::assertSame(1, $row->places);
        self::assertSame(3, $formRow->places);
        self::assertSame('<b>Literal</b>', $formRow->comment);
        self::assertSame(9, $formRow->id);
        self::assertSame(10, $formRow->event);
        self::assertSame(1, $formRow->seriesbooking);
        self::assertSame(0, $draft['sendemail']);
        self::assertSame(0, $draft['seriesbooking']);
    }

    /** @return void Cancel clears only the current form and uses its normal destination. */
    public function testCancelClearsDraft(): void
    {
        $controller = $this->controller(['event' => 10, 'id' => 9]);
        JemAttendeeFormHelper::remember(['uid' => 20], 10, 9);
        $controller->cancel();
        self::assertSame([], JemAttendeeFormHelper::getDraft(10, 9));
        self::assertStringContainsString('view=attendees&eventid=10', $controller->redirect[0]);
    }

    /** @return iterable Successful action destinations remain unchanged. */
    public static function successTasks(): iterable
    {
        yield ['save', 'view=attendees&eventid=10'];
        yield ['apply', 'view=attendee&hidemainmenu=1&id=22&eventid=10'];
        yield ['save2new', 'view=attendee&hidemainmenu=1&eventid=10'];
        yield ['save2copy', 'view=attendee&hidemainmenu=1&id=22&eventid=10'];
    }

    /** @param string $task Action. @param string $destination Expected route. @return void */
    #[DataProvider('successTasks')]
    public function testSuccessClearsDraftWithoutChangingDestination(string $task, string $destination): void
    {
        $controller = $this->controller(['task' => $task, 'event' => 10, 'uid' => 20, 'status' => 0, 'places' => 1]);
        JemAttendeeFormHelper::remember(['uid' => 20], 10, 0);
        $controller->model->success = true;
        $controller->save();
        self::assertSame([], JemAttendeeFormHelper::getDraft(10, 0));
        self::assertStringContainsString($destination, $controller->redirect[0]);
        self::assertSame([], $this->app->messages);
        self::assertSame(['onJemAfterAttendeeSave'], Dispatcher::$events);
    }

    /** @return void A successful copy also clears the failed original form's draft. */
    public function testSuccessfulCopyClearsOriginalDraft(): void
    {
        $controller = $this->controller(['task' => 'save2copy', 'id' => 9, 'event' => 10, 'uid' => 20, 'status' => 0, 'places' => 1]);
        JemAttendeeFormHelper::remember(['uid' => 20], 10, 9);
        $controller->model->success = true;
        $controller->save();
        self::assertSame([], JemAttendeeFormHelper::getDraft(10, 9));
        self::assertSame(0, $controller->model->stored[0]['id']);
        self::assertStringContainsString('id=22&eventid=10', $controller->redirect[0]);
    }

    /** @return void Render the actual checkbox snippets with default, checked and unchecked drafts. */
    public function testNotificationAndSeriesCheckboxesRestoreInput(): void
    {
        $template = file_get_contents(JEM_TEST_ROOT . '/admin/views/attendee/tmpl/default.php');
        foreach (['sendemail', 'seriesbooking'] as $field) {
            preg_match('~<input type="checkbox" id="' . $field . '".*?/\s*>~s', $template, $matches);
            self::assertNotEmpty($matches);
            foreach ([[], [$field => 0], [$field => 1]] as $draft) {
                $view = new class ($draft) {
                    public function __construct(public array $formDraft) {}
                };
                $render = function ($snippet): string {
                    ob_start();
                    eval('?>' . $snippet);
                    return ob_get_clean();
                };
                $html = $render->call($view, $matches[0]);
                $checked = $draft ? (bool) $draft[$field] : $field === 'sendemail';
                self::assertSame($checked, str_contains($html, 'checked="checked"'));
            }
        }
    }
}
