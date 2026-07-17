<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AttachmentRemovalSecurityTest extends TestCase
{
    public function testAjaxRemovalRequiresCsrfValidationInBothApplications(): void
    {
        foreach (array('admin/controller.php', 'site/controller.php') as $relativePath) {
            $controller = $this->read($relativePath);

            self::assertStringContainsString("Session::checkToken('request')", $controller, $relativePath);
            self::assertStringContainsString('JemAttachment::remove($id)', $controller, $relativePath);
        }
    }

    public function testAttachmentRemovalRequiresAnOwnerOrParentEditPermission(): void
    {
        $attachment = $this->read('site/classes/attachment.class.php');

        self::assertStringContainsString('empty($userid) || ($userid != $res->created_by)', $attachment);
        self::assertStringContainsString('$user->can(\'edit\', $type, $itemid, $created_by)', $attachment);
        self::assertStringContainsString('getAuthorisedViewLevels()', $attachment);
    }

    private function read(string $relativePath): string
    {
        $path = JEM_TEST_ROOT . '/' . $relativePath;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
