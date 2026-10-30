<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AttendeeOutputPaginationTest extends TestCase
{
    public function testCsvAndPrintUseFullReadersWithoutChangingNormalLists(): void
    {
        $adminModel = $this->method('admin/models/attendees.php', 'getCsv');
        $siteExport = $this->method('site/controllers/attendees.php', 'export');
        self::assertStringContainsString('$items = $this->getAllItems();', $adminModel);
        self::assertStringContainsString('$datas = $model->getAllItems();', $siteExport);
        foreach (['admin', 'site'] as $client) {
            $print = $this->method($client . '/views/attendees/view.html.php', '_displayprint');
            self::assertStringContainsString("\$this->get('AllItems')", $print);
            self::assertStringNotContainsString("\$this->get('Items')", $print);
            self::assertStringNotContainsString("\$this->get('Data')", $print);
            $list = $this->method($client . '/views/attendees/view.html.php', 'display');
            self::assertStringContainsString(
                $client === 'admin' ? "\$this->get('Items')" : "\$this->get('Data')",
                $list
            );
            $reader = $this->method($client . '/models/attendees.php', 'getAllItems');
            self::assertStringContainsString(', 0, 0)', $reader);
            self::assertStringNotContainsString('setState(', $reader);
            self::assertStringNotContainsString('setUserState(', $reader);
            self::assertStringNotContainsString('getPagination(', $reader);
        }
    }

    public function testExportAndPrintKeepTheirExistingAccessGuards(): void
    {
        $admin = $this->method('admin/controllers/attendees.php', 'export');
        self::assertStringContainsString('Session::checkToken()', $admin);
        $this->assertBefore('$this->assertCanManageAttendees()', '$model->getCsv()', $admin);
        $site = $this->method('site/controllers/attendees.php', 'export');
        $this->assertBefore('$this->assertCanManageAttendees($eventid)', '$model->getAllItems()', $site);
        $view = $this->method('admin/views/attendees/view.html.php', 'display');
        $this->assertBefore("JemHelperBackend::canManage('jem.attendees.manage')", '$this->_displayprint(', $view);
        $view = $this->method('site/views/attendees/view.html.php', 'display');
        $this->assertBefore('$model->canManageAttendees($user)', '$this->_displayprint(', $view);
    }

    /**
     * Extract a production method for call-site and security-boundary contracts.
     *
     * @param string $path Repository-relative source path.
     * @param string $name Method name.
     *
     * @return string Method source until the following method documentation.
     */
    private function method(string $path, string $name): string
    {
        $source = file_get_contents(JEM_TEST_ROOT . '/' . $path);
        $start = strpos($source, 'function ' . $name . '(');
        self::assertNotFalse($start, $path . '::' . $name);
        $end = strpos($source, "\n    /**", $start);
        return substr($source, $start, $end === false ? null : $end - $start);
    }

    /**
     * Verify the access guard precedes the protected operation.
     *
     * @param string $guard Guard source fragment.
     * @param string $operation Protected operation source fragment.
     * @param string $source Method source.
     *
     * @return void
     */
    private function assertBefore(string $guard, string $operation, string $source): void
    {
        $guardPosition = strpos($source, $guard);
        $operationPosition = strpos($source, $operation);
        self::assertNotFalse($guardPosition);
        self::assertNotFalse($operationPosition);
        self::assertLessThan($operationPosition, $guardPosition);
    }
}
