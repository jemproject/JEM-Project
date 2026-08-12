<?php

declare(strict_types=1);

use Joomla\CMS\Factory;

require_once __DIR__ . '/../JoomlaTestCase.php';

final class NotificationPersistenceJoomlaIntegrationTest extends JoomlaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('JEM_TEST_WRITABLE') !== '1') {
            self::markTestSkipped('Set JEM_TEST_WRITABLE=1 to run the transactional notification test.');
        }
        self::bootJoomlaSite();
        require_once JPATH_SITE . '/components/com_jem/helpers/helper.php';
        require_once JPATH_SITE . '/components/com_jem/factory.php';
        require_once JPATH_ADMINISTRATOR . '/components/com_jem/script.php';

        $installer = new com_jemInstallerScript();
        $registrationRepair = new ReflectionMethod(com_jemInstallerScript::class, 'repair510RegistrationSchema');
        $registrationRepair->invoke($installer);
        $notificationRepair = new ReflectionMethod(com_jemInstallerScript::class, 'repair510NotificationSchema');
        $notificationRepair->invoke($installer);
    }

    public function testSnapshotIdempotencyAttemptsAndSelfServiceLimits(): void
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $columns = array_change_key_case($db->getTableColumns($db->replacePrefix('#__jem_notifications'), false), CASE_LOWER);
        self::assertArrayHasKey('registration_revision', $columns);
        self::assertArrayHasKey('reminder_definition_id', $columns);
        self::assertContains($db->replacePrefix('#__jem_notifications_attempts'), $db->getTableList());
        self::assertNotContains($db->replacePrefix('#__jem_notification_attempts'), $db->getTableList());
        self::assertContains($db->replacePrefix('#__jem_register_history'), $db->getTableList());
        self::assertNotContains($db->replacePrefix('#__jem_registration_history'), $db->getTableList());
        self::assertContains($db->replacePrefix('#__jem_reminders'), $db->getTableList());
        $reminderColumns = array_change_key_case(
            $db->getTableColumns($db->replacePrefix('#__jem_reminders'), false),
            CASE_LOWER
        );
        foreach (array('event_id', 'source_id', 'minutes', 'created_by') as $column) {
            self::assertArrayHasKey($column, $reminderColumns);
        }
        foreach (array('amount', 'unit') as $legacyColumn) {
            self::assertArrayNotHasKey($legacyColumn, $reminderColumns);
        }
        $service = new JemNotificationService($db);
        $ids = array();

        try {
            $base = array(
                'notification_type' => 'integration_test',
                'recipient_type' => 'user',
                'recipient_email' => 'JEM-Point2B-PHPUnit@example.invalid',
                'revision' => 3,
                'resolved_language' => 'en-GB',
                'template_id' => 'plg_jem_mailer.integration_test',
                'subject' => 'JEM Point 2B integration test',
                'body' => "Line one\nLine two",
                'source' => 'integration_test',
            );
            $key = hash('sha256', 'JEM_POINT2B_PHPUNIT|' . microtime(true));
            $row = $service->create($base + array('idempotency_key' => $key));
            $ids[] = (int) $row->id;
            self::assertSame('JEM-Point2B-PHPUnit@example.invalid', $row->recipient_email);
            self::assertSame(3, (int) $row->registration_revision);
            self::assertSame(
                hash('sha256', implode("\x1f", array($base['subject'], $base['body'], '', '[]', '[]'))),
                $row->content_hash
            );
            $duplicate = $service->create($base + array('idempotency_key' => $key));
            self::assertSame((int) $row->id, (int) $duplicate->id);
            self::assertFalse((bool) $duplicate->_created);

            $first = $service->beginAttempt($row->id, 'integration_test', 0);
            self::assertNotFalse($first);
            self::assertFalse($service->finishAttempt($row->id, $first->id, false, 'expected test failure'));
            $failed = $service->getById($row->id);
            self::assertSame(JemNotificationService::STATE_FAILED, $failed->state);
            self::assertEqualsWithDelta(600, Factory::getDate($failed->next_attempt_at)->toUnix() - time(), 10);
            $second = $service->beginAttempt($row->id, 'integration_retry', 0);
            self::assertNotFalse($second);
            self::assertTrue($service->finishAttempt($row->id, $second->id, true));
            self::assertSame(JemNotificationService::STATE_SENT, $service->getById($row->id)->state);
            $history = $service->getRegistrationNotifications(0, 100);
            $historyById = array_column($history, null, 'id');
            self::assertArrayHasKey((string) $row->id, $historyById);
            self::assertSame(2, (int) $historyById[(string) $row->id]->attempts_total);

            $retrySequence = $service->create($base + array(
                'idempotency_key' => hash('sha256', $key . '|retry-sequence'),
                'subject' => 'JEM retry sequence integration test',
            ));
            $ids[] = (int) $retrySequence->id;
            foreach (array(600, 1800, 7200) as $expectedDelay) {
                $attempt = $service->beginAttempt($retrySequence->id, 'integration_retry', 0);
                self::assertNotFalse($attempt);
                self::assertFalse($service->finishAttempt($retrySequence->id, $attempt->id, false, 'expected retry sequence failure'));
                $failedAttempt = $service->getById($retrySequence->id);
                self::assertEqualsWithDelta(
                    $expectedDelay,
                    Factory::getDate($failedAttempt->next_attempt_at)->toUnix() - time(),
                    10
                );
            }
            $lastAttempt = $service->beginAttempt($retrySequence->id, 'integration_retry', 0);
            self::assertNotFalse($lastAttempt);
            self::assertFalse($service->finishAttempt($retrySequence->id, $lastAttempt->id, false, 'expected final failure'));
            $retryExhausted = $service->getById($retrySequence->id);
            self::assertSame(4, (int) $retryExhausted->attempt_count);
            self::assertNull($retryExhausted->next_attempt_at);
            self::assertFalse($service->beginAttempt($retrySequence->id, 'integration_retry', 0));

            $scheduled = $service->create(array_replace($base, array(
                'idempotency_key' => hash('sha256', $key . '|scheduled'),
                'source' => 'integration_test',
                'notification_type' => 'reminder',
                'scheduled_at' => Factory::getDate(time() + 3600)->toSql(),
            )));
            $ids[] = (int) $scheduled->id;
            self::assertSame(JemNotificationService::STATE_SCHEDULED, $scheduled->state);
            $query = $db->getQuery(true)->update($db->quoteName('#__jem_notifications'))
                ->set($db->quoteName('scheduled_at') . ' = ' . $db->quote(Factory::getDate(time() - 60)->toSql()))
                ->where($db->quoteName('id') . ' = ' . (int) $scheduled->id);
            $db->setQuery($query)->execute();
            self::assertContains((int) $scheduled->id, $service->getDueNotificationIds(100));
            self::assertSame(JemNotificationService::STATE_QUEUED, $service->getById($scheduled->id)->state);

            require_once JPATH_ADMINISTRATOR . '/components/com_jem/models/notificationhistory.php';
            $historyModel = new JemModelNotificationhistory(array('ignore_request' => true));
            $historyModel->setState('filter_search', 'id:' . (int) $row->id);
            $adminRows = $historyModel->getItems();
            self::assertCount(1, $adminRows);
            self::assertSame((int) $row->id, (int) $adminRows[0]->id);

            $settingsXml = simplexml_load_file(JPATH_ADMINISTRATOR . '/components/com_jem/models/forms/settings.xml');
            self::assertInstanceOf(SimpleXMLElement::class, $settingsXml);
            $retentionFields = $settingsXml->xpath("//field[@name='notification_retention_days']");
            self::assertCount(1, $retentionFields);
            self::assertSame('0', (string) $retentionFields[0]['default']);
            self::assertSame('0', (string) $retentionFields[0]['min']);

            foreach (array(1, 2) as $index) {
                $resend = $service->create(array_replace($base, array(
                    'idempotency_key' => hash('sha256', $key . '|resend|' . $index),
                    'source' => 'user_resend',
                )));
                $ids[] = (int) $resend->id;
                if ($index === 1) {
                    $cooldownPolicy = $service->canUserResend(0, 0);
                    self::assertFalse($cooldownPolicy->allowed);
                    self::assertSame('cooldown', $cooldownPolicy->reason);
                }
            }
            $policy = $service->canUserResend(0, 0);
            self::assertFalse($policy->allowed);
            self::assertSame('limit', $policy->reason);
        } finally {
            if ($ids) {
                $idList = implode(',', array_map('intval', $ids));
                $query = $db->getQuery(true)->delete($db->quoteName('#__jem_notifications_attempts'))->where('notification_id IN (' . $idList . ')');
                $db->setQuery($query)->execute();
                $query = $db->getQuery(true)->delete($db->quoteName('#__jem_notifications'))->where('id IN (' . $idList . ')')->where('source IN (' . $db->quote('integration_test') . ',' . $db->quote('user_resend') . ')');
                $db->setQuery($query)->execute();
            }
        }

        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__jem_notifications'))
            ->where($db->quoteName('recipient_email') . ' = ' . $db->quote('JEM-Point2B-PHPUnit@example.invalid'));
        self::assertSame(0, (int) $db->setQuery($query)->loadResult());
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__jem_notifications_attempts'))
            ->where('notification_id IN (' . implode(',', array_map('intval', $ids)) . ')');
        self::assertSame(0, (int) $db->setQuery($query)->loadResult());
    }

    public function testLegacyZeroActivationAccountIsAcceptedWhenPresent(): void
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__users'))
            ->where($db->quoteName('block') . ' = 0')
            ->where($db->quoteName('activation') . ' = ' . $db->quote('0'));
        $db->setQuery($query, 0, 1);
        $userId = (int) $db->loadResult();
        if ($userId < 1) {
            self::markTestSkipped('No active legacy account with activation="0" is available.');
        }

        $service = new JemRegistrationService($db);
        $method = new ReflectionMethod(JemRegistrationService::class, 'assertActiveUser');
        $method->invoke($service, $userId);
        self::addToAssertionCount(1);
    }

    public function testZeroDayRetentionIsUnlimitedAndPositiveDaysPurgeOnlyTerminalHistory(): void
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $service = new JemNotificationService($db);
        $ids = array();

        try {
            $base = array(
                'notification_type' => 'integration_retention',
                'recipient_type' => 'user',
                'recipient_email' => 'JEM-Retention-PHPUnit@example.invalid',
                'resolved_language' => 'en-GB',
                'template_id' => 'plg_jem_mailer.integration_retention',
                'subject' => 'JEM retention integration test',
                'body' => 'Retention test body',
                'source' => 'integration_retention',
            );
            $key = hash('sha256', 'JEM_RETENTION_PHPUNIT|' . microtime(true));
            $sent = $service->create($base + array('idempotency_key' => hash('sha256', $key . '|sent')));
            $ids[] = (int) $sent->id;
            $attempt = $service->beginAttempt($sent->id, 'integration_retention', 0);
            self::assertNotFalse($attempt);
            self::assertTrue($service->finishAttempt($sent->id, $attempt->id, true));

            $pending = $service->create($base + array('idempotency_key' => hash('sha256', $key . '|pending')));
            $ids[] = (int) $pending->id;
            $old = Factory::getDate(time() - (10 * 86400))->toSql();
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__jem_notifications'))
                ->set($db->quoteName('created') . ' = ' . $db->quote($old))
                ->where($db->quoteName('id') . ' IN (' . implode(',', $ids) . ')');
            $db->setQuery($query)->execute();

            self::assertSame(0, $service->purgeExpired(0));
            self::assertNotNull($service->getById($sent->id));
            self::assertNotNull($service->getById($pending->id));

            self::assertSame(1, $service->purgeExpired(1));
            self::assertNull($service->getById($sent->id));
            self::assertNotNull($service->getById($pending->id));
        } finally {
            if ($ids) {
                $idList = implode(',', array_map('intval', $ids));
                $query = $db->getQuery(true)->delete($db->quoteName('#__jem_notifications_attempts'))
                    ->where('notification_id IN (' . $idList . ')');
                $db->setQuery($query)->execute();
                $query = $db->getQuery(true)->delete($db->quoteName('#__jem_notifications'))
                    ->where('id IN (' . $idList . ')')
                    ->where($db->quoteName('source') . ' = ' . $db->quote('integration_retention'));
                $db->setQuery($query)->execute();
            }
        }
    }

    public function testReminderDefaultsNativeTaskAndUtcCalculation(): void
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select(array('code', 'minutes', 'published', 'default_new_event'))
            ->from($db->quoteName('#__jem_reminders'))
            ->where($db->quoteName('event_id') . ' = 0')
            ->where($db->quoteName('code') . ' IN (' . implode(',', array_map(array($db, 'quote'), array(
                'default_7_days', 'default_24_hours', 'default_2_hours',
            ))) . ')');
        $db->setQuery($query);
        $definitions = $db->loadObjectList('code');
        self::assertSame(10080, (int) $definitions['default_7_days']->minutes);
        self::assertSame(1440, (int) $definitions['default_24_hours']->minutes);
        self::assertSame(120, (int) $definitions['default_2_hours']->minutes);
        foreach ($definitions as $definition) {
            self::assertSame(1, (int) $definition->published);
            self::assertSame(1, (int) $definition->default_new_event);
        }

        $query = $db->getQuery(true)
            ->select(array('enabled'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
            ->where($db->quoteName('folder') . ' = ' . $db->quote('task'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('jem'));
        $db->setQuery($query);
        self::assertSame(1, (int) $db->loadResult());

        $query = $db->getQuery(true)
            ->select(array('execution_rules'))
            ->from($db->quoteName('#__scheduler_tasks'))
            ->where($db->quoteName('type') . ' = ' . $db->quote(JemReminderSchedulerService::TASK_TYPE))
            ->where($db->quoteName('state') . ' <> -2');
        $db->setQuery($query, 0, 1);
        $rules = json_decode((string) $db->loadResult(), true);
        self::assertIsArray($rules);
        self::assertSame('interval-minutes', $rules['rule-type'] ?? null);
        self::assertSame(10, (int) ($rules['interval-minutes'] ?? 0));

        $service = new JemReminderService($db);
        $definition = (object) array('minutes' => 120);
        $timed = (object) array(
            'dates' => '2030-06-15',
            'times' => '18:00:00',
            'start_utc' => '2030-06-15 16:00:00',
        );
        self::assertSame('2030-06-15 14:00:00', $service->calculateDueUtc($timed, $definition));
        self::assertNull($service->calculateDueUtc((object) array('dates' => null), $definition));

        $query = $db->getQuery(true)
            ->select($db->quoteName('value'))
            ->from($db->quoteName('#__jem_config'))
            ->where($db->quoteName('keyname') . ' = ' . $db->quote('reminder_all_day_time'));
        $db->setQuery($query);
        $allDayTime = (string) ($db->loadResult() ?: '09:00');
        $allDay = (object) array(
            'dates' => '2030-06-15',
            'times' => null,
            'start_utc' => null,
            'timezone_mode' => 'custom',
            'timezone' => 'Europe/Madrid',
            'locid' => 0,
        );
        $expectedAllDay = (new DateTimeImmutable('2030-06-15 ' . $allDayTime, new DateTimeZone('Europe/Madrid')))
            ->setTimezone(new DateTimeZone('UTC'))
            ->sub(new DateInterval('PT120M'))
            ->format('Y-m-d H:i:s');
        self::assertSame($expectedAllDay, $service->calculateDueUtc($allDay, $definition));
    }

    public function testReminderEditorConvertsDisplayUnitsToCanonicalMinutes(): void
    {
        require_once JPATH_ADMINISTRATOR . '/components/com_jem/models/reminder.php';

        self::assertSame(20160, JemModelReminder::intervalToMinutes(2, 'week'));
        self::assertSame(array('amount' => 2, 'unit' => 'week'), JemModelReminder::minutesToInterval(20160));
        self::assertSame(array('amount' => 24, 'unit' => 'hour'), JemModelReminder::minutesToInterval(1440, 'default_24_hours'));
        $this->expectException(InvalidArgumentException::class);
        JemModelReminder::intervalToMinutes(0, 'day');
    }

    public function testReminderLifecycleSchedulesAndProcessesWithoutSendingRealMail(): void
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $eventId = 0;
        $registrationId = 0;
        $notificationId = 0;
        $query = $db->getQuery(true)
            ->select(array('id', 'name', 'email'))
            ->from($db->quoteName('#__users'))
            ->where($db->quoteName('block') . ' = 0')
            ->where('(' . $db->quoteName('activation') . ' = ' . $db->quote('')
                . ' OR ' . $db->quoteName('activation') . ' = ' . $db->quote('0') . ')')
            ->where($db->quoteName('email') . ' <> ' . $db->quote(''))
            ->order($db->quoteName('id') . ' ASC');
        $db->setQuery($query, 0, 1);
        $user = $db->loadObject();
        if (!$user || !filter_var((string) $user->email, FILTER_VALIDATE_EMAIL)) {
            self::markTestSkipped('No active verified Joomla user is available for the reminder lifecycle test.');
        }

        $query = $db->getQuery(true)
            ->select($db->quoteName('value'))
            ->from($db->quoteName('#__jem_config'))
            ->where($db->quoteName('keyname') . ' = ' . $db->quote('reminders_enabled'));
        $db->setQuery($query);
        $originalEnabled = (string) ($db->loadResult() ?? '0');
        $future = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('+8 days')->setTime(12, 0);
        $now = Factory::getDate()->toSql();

        try {
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__jem_config'))
                ->set($db->quoteName('value') . ' = ' . $db->quote('1'))
                ->where($db->quoteName('keyname') . ' = ' . $db->quote('reminders_enabled'));
            $db->setQuery($query)->execute();

            $event = (object) array(
                'title' => 'JEM reminder integration test',
                'alias' => 'jem-reminder-integration-' . bin2hex(random_bytes(4)),
                'dates' => $future->format('Y-m-d'),
                'times' => $future->format('H:i:s'),
                'timezone_mode' => 'custom',
                'timezone' => 'UTC',
                'start_utc' => $future->format('Y-m-d H:i:s'),
                'created_by' => (int) $user->id,
                'created_by_alias' => '',
                'created' => $now,
                'introtext' => 'Integration test event.',
                'fulltext' => '',
                'metadata' => '',
                'published' => 1,
                'event_status' => 'scheduled',
                'registra' => 1,
                'access' => 1,
            );
            $db->insertObject('#__jem_events', $event, 'id');
            $eventId = (int) $event->id;

            $registration = (object) array(
                'event' => $eventId,
                'uid' => (int) $user->id,
                'places' => 1,
                'uregdate' => $now,
                'waiting' => 0,
                'status' => 1,
                'comment' => '',
                'reference' => substr('R-TEST-' . strtoupper(bin2hex(random_bytes(12))), 0, 28),
                'created' => $now,
                'modified' => $now,
                'revision' => 1,
            );
            $db->insertObject('#__jem_register', $registration, 'id');
            $registrationId = (int) $registration->id;

            $query = $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__jem_reminders'))
                ->where($db->quoteName('event_id') . ' = 0')
                ->where($db->quoteName('code') . ' = ' . $db->quote('default_7_days'));
            $db->setQuery($query);
            $definitionId = (int) $db->loadResult();
            $reminders = new JemReminderService($db);
            self::assertTrue($reminders->storeEventDefinitions($eventId, array($definitionId), 0, false));
            $eventReminderIds = $reminders->getEventDefinitionIds($eventId);
            self::assertCount(1, $eventReminderIds);
            self::assertNotSame($definitionId, $eventReminderIds[0]);
            $eventReminderId = $eventReminderIds[0];
            $query = $db->getQuery(true)
                ->select(array('event_id', 'source_id', 'minutes'))
                ->from($db->quoteName('#__jem_reminders'))
                ->where($db->quoteName('id') . ' = ' . $eventReminderId);
            $db->setQuery($query);
            $eventReminder = $db->loadObject();
            self::assertSame($eventId, (int) $eventReminder->event_id);
            self::assertSame($definitionId, (int) $eventReminder->source_id);
            self::assertSame(10080, (int) $eventReminder->minutes);
            $query = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__jem_notifications'))
                ->where($db->quoteName('registration_id') . ' = ' . $registrationId)
                ->where($db->quoteName('reminder_definition_id') . ' = ' . $eventReminderId);
            $db->setQuery($query);
            $notification = $db->loadObject();
            self::assertNotNull($notification);
            $notificationId = (int) $notification->id;
            self::assertSame(JemNotificationService::STATE_SCHEDULED, $notification->state);
            self::assertSame($future->modify('-7 days')->format('Y-m-d H:i:s'), $notification->scheduled_at);

            $query = $db->getQuery(true)
                ->update($db->quoteName('#__jem_notifications'))
                ->set($db->quoteName('scheduled_at') . ' = ' . $db->quote(Factory::getDate(time() - 60)->toSql()))
                ->set($db->quoteName('state') . ' = ' . $db->quote(JemNotificationService::STATE_QUEUED))
                ->set($db->quoteName('queued_at') . ' = ' . $db->quote(Factory::getDate()->toSql()))
                ->where($db->quoteName('id') . ' = ' . $notificationId);
            $db->setQuery($query)->execute();
            $notifications = new JemNotificationService($db);
            self::assertTrue($notifications->retryStored($notificationId, static function ($snapshot, &$error) {
                $error = '';

                return true;
            }, 0, 'scheduler_retry'));
            self::assertSame(JemNotificationService::STATE_SENT, $notifications->getById($notificationId)->state);
            $query = $db->getQuery(true)
                ->select($db->quoteName('source'))
                ->from($db->quoteName('#__jem_notifications_attempts'))
                ->where($db->quoteName('notification_id') . ' = ' . $notificationId);
            $db->setQuery($query);
            self::assertSame('scheduler_retry', (string) $db->loadResult());
        } finally {
            if ($notificationId > 0) {
                $query = $db->getQuery(true)->delete($db->quoteName('#__jem_notifications_attempts'))
                    ->where($db->quoteName('notification_id') . ' = ' . $notificationId);
                $db->setQuery($query)->execute();
            }
            if ($registrationId > 0) {
                $query = $db->getQuery(true)->delete($db->quoteName('#__jem_notifications'))
                    ->where($db->quoteName('registration_id') . ' = ' . $registrationId);
                $db->setQuery($query)->execute();
                $query = $db->getQuery(true)->delete($db->quoteName('#__jem_register'))
                    ->where($db->quoteName('id') . ' = ' . $registrationId);
                $db->setQuery($query)->execute();
            }
            if ($eventId > 0) {
                $query = $db->getQuery(true)->delete($db->quoteName('#__jem_reminders'))
                    ->where($db->quoteName('event_id') . ' = ' . $eventId);
                $db->setQuery($query)->execute();
                $query = $db->getQuery(true)->delete($db->quoteName('#__jem_events'))
                    ->where($db->quoteName('id') . ' = ' . $eventId);
                $db->setQuery($query)->execute();
            }
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__jem_config'))
                ->set($db->quoteName('value') . ' = ' . $db->quote($originalEnabled))
                ->where($db->quoteName('keyname') . ' = ' . $db->quote('reminders_enabled'));
            $db->setQuery($query)->execute();
        }
    }
}
