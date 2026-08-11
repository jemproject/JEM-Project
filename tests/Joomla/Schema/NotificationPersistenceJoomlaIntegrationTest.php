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

        $repair = new ReflectionMethod(com_jemInstallerScript::class, 'repair510NotificationSchema');
        $repair->invoke(new com_jemInstallerScript());
    }

    public function testSnapshotIdempotencyAttemptsAndSelfServiceLimits(): void
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $columns = array_change_key_case($db->getTableColumns($db->replacePrefix('#__jem_notifications'), false), CASE_LOWER);
        self::assertArrayHasKey('registration_revision', $columns);
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
            self::assertSame(JemNotificationService::STATE_FAILED, $service->getById($row->id)->state);
            $second = $service->beginAttempt($row->id, 'integration_retry', 0);
            self::assertNotFalse($second);
            self::assertTrue($service->finishAttempt($row->id, $second->id, true));
            self::assertSame(JemNotificationService::STATE_SENT, $service->getById($row->id)->state);
            $history = $service->getRegistrationNotifications(0, 100);
            $historyById = array_column($history, null, 'id');
            self::assertArrayHasKey((string) $row->id, $historyById);
            self::assertSame(2, (int) $historyById[(string) $row->id]->attempts_total);

            require_once JPATH_ADMINISTRATOR . '/components/com_jem/models/notificationhistory.php';
            $historyModel = new JemModelNotificationhistory(array('ignore_request' => true));
            $historyModel->setState('filter_search', 'id:' . (int) $row->id);
            $adminRows = $historyModel->getItems();
            self::assertCount(1, $adminRows);
            self::assertSame((int) $row->id, (int) $adminRows[0]->id);

            $settingsXml = simplexml_load_file(JPATH_ADMINISTRATOR . '/components/com_jem/models/forms/settings.xml');
            self::assertInstanceOf(SimpleXMLElement::class, $settingsXml);
            self::assertCount(1, $settingsXml->xpath("//field[@name='notification_retention_years']"));

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
                $query = $db->getQuery(true)->delete($db->quoteName('#__jem_notification_attempts'))->where('notification_id IN (' . $idList . ')');
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
            ->from($db->quoteName('#__jem_notification_attempts'))
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
}
