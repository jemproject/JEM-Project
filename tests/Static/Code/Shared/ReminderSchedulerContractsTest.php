<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ReminderSchedulerContractsTest extends TestCase
{
    public function testNativeTaskPluginIsPackagedAndRunsTheReminderService(): void
    {
        $manifest = $this->read('/package/pkg_jem.xml');
        $plugin = $this->read('/plugins/plg_task_jem/src/Extension/Jem.php');
        $scheduler = $this->read('/site/classes/reminderschedulerservice.class.php');
        $packageInstaller = $this->read('/package/pkg_install.php');

        self::assertStringContainsString('plg_task_jem.zip', $manifest);
        self::assertStringContainsString("'jem.notifications'", $plugin);
        self::assertStringContainsString('TaskPluginTrait', $plugin);
        self::assertStringContainsString('$application->loadIdentity();', $plugin);
        self::assertStringContainsString('processDue', $plugin);
        self::assertStringContainsString("'scheduler'", $plugin);
        self::assertStringContainsString('DEFAULT_INTERVAL_MINUTES = 10', $scheduler);
        self::assertStringContainsString("'interval-minutes' => self::DEFAULT_INTERVAL_MINUTES", $scheduler);
        self::assertStringContainsString('ensureJemNotificationTask', $packageInstaller);
    }

    public function testEventHasAnExplicitReminderSwitchAndSeriesScope(): void
    {
        foreach (array('/admin/models/forms/event.xml', '/site/models/forms/event.xml') as $path) {
            $form = $this->read($path);
            self::assertStringContainsString('<fieldset name="notifications"', $form);
            self::assertStringContainsString('name="event_reminders_enabled"', $form);
            self::assertStringContainsString('name="reminder_ids"', $form);
            self::assertStringContainsString('name="apply_reminders_to_series"', $form);
        }

        $model = $this->read('/admin/models/event.php');
        self::assertStringContainsString('storeEventDefinitions', $model);
        self::assertStringContainsString('$new || $applyRemindersToSeries', $model);
        self::assertStringContainsString('COM_JEM_EVENT_REMINDERS_REQUIRE_INTERVAL', $model);
    }

    public function testDueProcessingUsesUtcAndTheAgreedRetrySequence(): void
    {
        $reminders = $this->read('/site/classes/reminderservice.class.php');
        $notifications = $this->read('/site/classes/notificationservice.class.php');

        self::assertStringContainsString("new DateTimeZone('UTC')", $reminders);
        self::assertStringContainsString('$definition->minutes', $reminders);
        self::assertStringContainsString('getEventTimeZoneName', $reminders);
        self::assertStringContainsString("'09:00'", $reminders);
        self::assertStringContainsString('if (empty($event->dates))', $reminders);
        self::assertStringContainsString("array(10, 30, 120)", $notifications);
        self::assertStringContainsString('$completedAt = Factory::getDate(time())', $notifications);
        self::assertStringContainsString('$completedAt->toUnix() + ($delayMinutes * 60)', $notifications);
        self::assertStringContainsString('recoverStaleProcessing', $notifications);
        self::assertStringContainsString('purgeExpired', $notifications);
        self::assertStringContainsString('if ($days <= 0)', $notifications);
        self::assertStringContainsString("quoteName('attempt_count') . ' >= ' . \$this->db->quoteName('max_attempts')", $notifications);
    }

    public function testReminderDefinitionsHaveAnAdminTabAndCrud(): void
    {
        self::assertFileExists(JEM_TEST_ROOT . '/admin/models/reminders.php');
        self::assertFileExists(JEM_TEST_ROOT . '/admin/models/reminder.php');
        self::assertFileExists(JEM_TEST_ROOT . '/admin/views/reminders/tmpl/default.php');
        self::assertStringContainsString("unset(\$data['amount'], \$data['unit'])", $this->read('/admin/models/reminder.php'));
        self::assertStringContainsString("'minutes' => max(1, (int) \$definition->minutes)", $this->read('/site/classes/reminderservice.class.php'));
        self::assertStringContainsString('COM_JEM_NOTIFICATION_TAB_REMINDERS', $this->read('/admin/views/notificationtemplates/tmpl/default.php'));
        self::assertStringContainsString('view=reminders', $this->read('/admin/views/notificationhistory/tmpl/default.php'));
    }

    public function testPhysicalEventCleanupCancelsAndRemovesPendingReminderWork(): void
    {
        $eventTable = $this->read('/admin/tables/event.php');
        $housekeeping = $this->read('/site/helpers/helper.php');

        self::assertStringContainsString('cancelPendingReminders(0, $id)', $eventTable);
        self::assertStringContainsString("#__jem_reminders", $eventTable);
        self::assertStringContainsString("quoteName('event_id')", $eventTable);
        self::assertStringContainsString('cancelPendingReminders(0, $outdatedEventId)', $housekeeping);
        self::assertStringContainsString('DELETE FROM #__jem_reminders WHERE event_id IN', $housekeeping);
    }

    private function read(string $path): string
    {
        $contents = file_get_contents(JEM_TEST_ROOT . $path);
        self::assertNotFalse($contents, $path . ' must be readable.');

        return $contents;
    }
}
