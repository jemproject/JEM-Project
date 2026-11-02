<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class HousekeepingMissingFilesRegistrationsTest extends TestCase
{
    public function testDiagnosticsRunTheNewReadOnlyChecks(): void
    {
        $diagnostics = $this->read('admin/classes/housekeepingdiagnostics.class.php');

        self::assertStringContainsString("'files' => 'checkFiles',", $diagnostics);
        self::assertStringContainsString("'registrations' => 'checkRegistrations',", $diagnostics);
        self::assertStringContainsString("'registrations' => array('total' => 0, 'orphaned' => 0, 'last_id' => 0),", $diagnostics);

        foreach (array('checkFiles', 'checkRegistrations') as $method) {
            $body = $this->method($diagnostics, 'private function ' . $method . '(array &$report): void');
            foreach (array('->execute(', '->delete(', '->update(', '->insert(', 'unlink(', 'purge') as $write) {
                self::assertStringNotContainsString($write, $body, $method . ' must stay read only.');
            }
        }
    }

    public function testMissingFilesReuseTheImagePathRulesOfTheModel(): void
    {
        $diagnostics = $this->read('admin/classes/housekeepingdiagnostics.class.php');
        $model = $this->read('admin/models/housekeeping.php');

        self::assertStringContainsString('(new JemModelHousekeeping())->getMissingImageReferences()', $diagnostics);
        self::assertStringContainsString('->attachmentBase()', $diagnostics);
        self::assertStringContainsString('private const FILE_LINES_LIMIT = 50;', $diagnostics);

        $method = $this->method($model, 'public function getMissingImageReferences(): array');
        self::assertStringContainsString('$this->getImageProfileAssignments(JemHelper::config())', $method);
        self::assertStringContainsString("->from(\$this->_db->quoteName('#__jem_links'))", $method);
        self::assertStringContainsString('self::containedExistingFile(JPATH_SITE, $image)', $method);
    }

    public function testRegistrationFindingsCarryNoPersonalData(): void
    {
        $diagnostics = $this->read('admin/classes/housekeepingdiagnostics.class.php');
        $model = $this->read('admin/models/housekeeping.php');

        $check = $this->method($diagnostics, 'private function checkRegistrations(array &$report): void');
        $orphans = $this->method($model, 'public function getOrphanRegistrations(): array');
        foreach (array($check, $orphans) as $body) {
            foreach (array("'uid'", 'r.uid', 'email', 'username', '#__users') as $personal) {
                self::assertStringNotContainsString($personal, $body);
            }
        }
    }

    public function testPurgeKeepsHistoryAndDoesNotTouchEvents(): void
    {
        $model = $this->read('admin/models/housekeeping.php');
        $controller = $this->read('admin/controllers/housekeeping.php');

        $purge = $this->method($model, 'public function purgeOrphanRegistrations(array $eventIds, int $actorId): array');
        self::assertStringContainsString('$orphans = $this->getOrphanRegistrations();', $purge);
        self::assertStringContainsString("\$result['skipped']++;", $purge);
        self::assertStringContainsString('$service->purgeForEvent($eventId', $purge);
        self::assertStringContainsString("'reasonCode' => 'legacy_orphan_cleanup'", $purge);
        self::assertStringNotContainsString('#__jem_events', $purge);
        self::assertStringNotContainsString('cleanup(', $purge);

        $task = $this->method($controller, 'public function purgeOrphanRegistrations(): void');
        self::assertStringContainsString('JemHelper::requirePostToken();', $task);
        self::assertStringContainsString('$this->allowHousekeeping();', $task);
        self::assertStringContainsString("authorise('core.admin')", $task);
        self::assertStringContainsString("JemHelper::consumeActionNonce('housekeeping.purgeOrphanRegistrations'", $task);

        // The automatic cleanup keeps its own orphan purge (issue #2379: keep current functionality).
        self::assertStringContainsString("'reasonCode' => 'legacy_orphan_cleanup',", $this->read('site/helpers/helper.php'));
        self::assertStringContainsString("this.form.task.value='housekeeping.triggerarchive'", $this->read('admin/views/housekeeping/tmpl/default.php'));
    }

    public function testScreensShowTheNewCountersAndTool(): void
    {
        $diagnostics = $this->read('admin/views/housekeeping/tmpl/diagnostics.php');
        $view = $this->read('admin/views/housekeeping/view.html.php');
        $layout = $this->read('admin/views/housekeeping/tmpl/registrations.php');
        $default = $this->read('admin/views/housekeeping/tmpl/default.php');

        self::assertStringContainsString("Text::_('COM_JEM_TOOLS_MISSING_FILE')", $diagnostics);
        self::assertStringContainsString("'registrations' => 'COM_JEM_TOOLS_REGISTRATIONS',", $diagnostics);
        self::assertStringContainsString("JemHelper::issueActionNonce('housekeeping.purgeOrphanRegistrations')", $view);
        self::assertStringContainsString("\$this->setLayout('registrations');", $view);
        self::assertStringContainsString('name="registrations_nonce"', $layout);
        self::assertStringContainsString("HTMLHelper::_('form.token')", $layout);
        self::assertStringContainsString('$this->registrationPurgeAllowed', $layout);
        self::assertStringContainsString("view=housekeeping&tool=registrations'", $default);
    }

    private function method(string $source, string $signature): string
    {
        $source = str_replace("\r\n", "\n", $source);
        $start = strpos($source, $signature);
        self::assertNotFalse($start, 'Missing ' . $signature);
        $body = substr($source, (int) $start);
        $end = strpos($body, "\n    }\n");

        return $end === false ? $body : substr($body, 0, $end);
    }

    private function read(string $relativePath): string
    {
        $path = JEM_TEST_ROOT . '/' . $relativePath;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
