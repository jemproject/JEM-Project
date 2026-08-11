<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class NotificationHistoryContractsTest extends TestCase
{
    public function testPersistentServiceSeparatesSnapshotsAttemptsRetryAndResend(): void
    {
        $service = $this->read('/site/classes/notificationservice.class.php');
        $mailer = $this->read('/plugins/plg_jem_mailer/mailer.php');

        self::assertStringContainsString("insertObject('#__jem_notifications'", $service);
        self::assertStringContainsString("insertObject('#__jem_notification_attempts'", $service);
        self::assertStringContainsString('idempotency_key', $service);
        self::assertStringContainsString('retryStored', $service);
        self::assertStringContainsString('canUserResend', $service);
        self::assertStringContainsString('resolveRecipientLanguage', $service);
        self::assertStringContainsString('$attachmentsJson', $service);
        self::assertStringContainsString('\'recipient_email\' => $email', $service);
        self::assertStringContainsString('onJemNotificationAction', $mailer);
        self::assertStringContainsString("'resend_of'", $mailer);
        self::assertStringContainsString("'force_unique' => true", $mailer);
    }

    public function testAdminHistoryAclAndReservationAuditBoxExist(): void
    {
        $access = $this->read('/admin/access.xml');
        $history = $this->read('/admin/views/notificationhistory/tmpl/default.php');
        $attendee = $this->read('/admin/views/attendee/tmpl/default.php');
        $sidebar = $this->read('/admin/helpers/helper.php');
        $dashboard = $this->read('/admin/views/main/tmpl/default.php');

        self::assertStringContainsString('jem.notifications.history', $access);
        self::assertStringContainsString('jem.notifications.resend', $access);
        self::assertStringContainsString("'jem.notifications.history'", $sidebar);
        self::assertStringContainsString("'jem.notifications.resend'", $sidebar);
        self::assertStringContainsString('COM_JEM_NOTIFICATION_TAB_HISTORY', $history);
        self::assertStringContainsString('notification.retry', $history);
        self::assertStringContainsString('notification.resend', $history);
        self::assertStringContainsString('COM_JEM_REGISTRATION_CHANGES', $attendee);
        self::assertStringContainsString('COM_JEM_NOTIFICATION_HISTORY', $attendee);
        self::assertStringContainsString('$canManageNotificationTemplates ? \'notifications\' : \'notificationhistory\'', $sidebar);
        self::assertStringContainsString('view=notifications', $dashboard);
        self::assertStringContainsString('view=notificationhistory', $dashboard);
    }

    public function testFrontendResendRequiresOwnershipAndRateLimit(): void
    {
        $controller = $this->read('/site/controllers/notification.php');
        $view = $this->read('/site/views/registration/view.html.php');
        $model = $this->read('/site/models/myattendances.php');

        self::assertStringContainsString('getOwnedRegistration', $controller);
        self::assertStringContainsString('canUserResend', $controller);
        self::assertStringContainsString("recipient_type !== 'user'", $controller);
        self::assertStringContainsString('recipient_user_id', $controller);
        self::assertStringContainsString('getRegistrationNotifications', $view);
        self::assertStringContainsString('r.id AS registration_id', $model);
    }

    public function testSettingsExposeFourYearRetentionAndResendLimits(): void
    {
        $form = $this->read('/admin/models/forms/settings.xml');
        $layout = $this->read('/admin/views/settings/tmpl/default.php');

        self::assertStringContainsString('name="notification_retention_years"', $form);
        self::assertStringContainsString('default="4"', $form);
        self::assertStringContainsString('name="notification_user_resend_limit"', $form);
        self::assertStringContainsString('default="2"', $form);
        self::assertStringContainsString('name="notification_user_resend_cooldown_minutes"', $form);
        self::assertStringContainsString("loadTemplate('notifications')", $layout);
    }

    private function read(string $path): string
    {
        $contents = file_get_contents(JEM_TEST_ROOT . $path);
        self::assertNotFalse($contents, $path . ' must be readable.');
        return $contents;
    }
}
