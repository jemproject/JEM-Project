<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AttachmentRemovalSecurityTest extends TestCase
{
    public function testAjaxRemovalRequiresCsrfValidationInBothApplications(): void
    {
        $admin = $this->read('admin/controller.php');
        $site = $this->read('site/controller.php');

        self::assertStringContainsString('JemHelper::requirePostToken()', $admin);
        self::assertStringContainsString("JemHelperBackend::canAccessAttachment(\$attachment->object, 'edit')", $admin);
        self::assertStringContainsString('JemHelper::requirePostToken()', $site);
        self::assertStringContainsString("->post->getInt('id', 0)", $admin);
        self::assertStringContainsString("->post->getInt('id', 0)", $site);
        self::assertStringContainsString('JemAttachment::remove($id)', $admin);
        self::assertStringContainsString('JemAttachment::remove($id)', $site);

        $javascript = $this->read('media/js/attachments.js');
        self::assertStringContainsString("method: 'POST'", $javascript);
        self::assertStringContainsString("'X-CSRF-Token'", $javascript);
        self::assertStringContainsString('new URLSearchParams({id})', $javascript);
        self::assertStringNotContainsString('tokenQuery', $javascript);
        self::assertStringNotContainsString('&id=${encodeURIComponent(id)}', $javascript);
    }

    public function testEventAndVenueAttachmentRemovalAlwaysRequiresCurrentParentPermission(): void
    {
        $attachment = $this->read('site/classes/attachment.class.php');

        self::assertStringContainsString("array('created_by', 'access')", $attachment);
        self::assertStringContainsString("\$user->can('edit', \$type, \$itemid, (int) \$item->created_by)", $attachment);
        self::assertStringContainsString('in_array((int) $item->access', $attachment);
        self::assertStringContainsString('getAuthorisedViewLevels()', $attachment);
    }

    public function testFrontendDownloadsRespectEventAndVenueVisibility(): void
    {
        $attachment = $this->read('site/classes/attachment.class.php');

        self::assertStringContainsString("preg_match('/^event(\\d+)$/i'", $attachment);
        self::assertStringContainsString("preg_match('/^venue(\\d+)$/i'", $attachment);
        self::assertStringContainsString("array('id', 'created_by', 'access', 'published'", $attachment);
        self::assertStringContainsString('in_array((int) $item->access', $attachment);
        self::assertStringContainsString("\$user->can('publish', \$type", $attachment);
    }

    private function read(string $relativePath): string
    {
        $path = JEM_TEST_ROOT . '/' . $relativePath;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
