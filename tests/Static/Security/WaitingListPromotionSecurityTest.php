<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class WaitingListPromotionSecurityTest extends TestCase
{
    public function testAutomaticPromotionSettingIsInstalledAndEnabledByDefault(): void
    {
        $form = file_get_contents(JEM_TEST_ROOT . '/admin/models/forms/settings.xml');
        $install = file_get_contents(JEM_TEST_ROOT . '/admin/sql/install.mysql.utf8.sql');
        $update = file_get_contents(JEM_TEST_ROOT . '/admin/sql/updates/mysql/5.0.1.sql');

        self::assertMatchesRegularExpression('/name="waitinglist_automatic"[\s\S]*?default="1"/', $form);
        self::assertMatchesRegularExpression('/name="waitinglist_strategy"[\s\S]*?default="strict"/', $form);
        self::assertStringContainsString("('waitinglist_automatic', '1')", $install);
        self::assertStringContainsString("('waitinglist_strategy', 'strict')", $install);
        self::assertStringContainsString("VALUES ('waitinglist_automatic', '1')", $update);
        self::assertStringContainsString("VALUES ('waitinglist_strategy', 'strict')", $update);
    }

    public function testPromotionLocksCapacityAndCommitsBeforeNotifications(): void
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/site/classes/waitinglistpromotion.class.php');
        $lock = strpos($source, 'FOR UPDATE');
        $commit = strpos($source, '$db->transactionCommit();', $lock);
        $mail = strpos($source, 'dispatchStatusMail', $commit);

        self::assertNotFalse($lock);
        self::assertNotFalse($commit);
        self::assertNotFalse($mail);
        self::assertLessThan($commit, $lock);
        self::assertLessThan($mail, $commit);
    }

    public function testForcedPromotionRequiresCoreAdminInFrontendAndBackend(): void
    {
        $backend = file_get_contents(JEM_TEST_ROOT . '/admin/controllers/attendees.php');
        $frontend = file_get_contents(JEM_TEST_ROOT . '/site/controllers/attendeeregistrations.php');

        foreach (array($backend, $frontend) as $source) {
            self::assertStringContainsString("authorise('core.admin', 'com_jem')", $source);
            self::assertStringContainsString("getBool('waitinglist_force', false)", $source);
        }
    }

    public function testCapacityReleaseFlowsUseCentralReconciliation(): void
    {
        $files = array(
            '/admin/controllers/attendee.php',
            '/admin/controllers/attendees.php',
            '/admin/controllers/event.php',
            '/site/controllers/attendeeregistrations.php',
            '/site/controllers/attendees.php',
            '/site/controllers/event.php',
        );

        foreach ($files as $file) {
            $source = file_get_contents(JEM_TEST_ROOT . $file);
            self::assertMatchesRegularExpression('/(?:reconcile|update)WaitingList\s*\(/', $source, $file);
        }
    }

    public function testEventSaveReconciliationUsesThePersistedPostSaveModel(): void
    {
        foreach (array(
            '/admin/controllers/event.php',
            '/site/controllers/event.php',
        ) as $file) {
            $source = (string) file_get_contents(JEM_TEST_ROOT . $file);
            $save = $this->method($source, 'save');
            $postSaveHook = $this->method($source, '_postSaveHook');

            self::assertStringNotContainsString('reconcileWaitingList(', $save, $file);
            self::assertStringContainsString("getState('event.id'", $postSaveHook, $file);
            self::assertStringContainsString('reconcileWaitingList(', $postSaveHook, $file);
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
}
