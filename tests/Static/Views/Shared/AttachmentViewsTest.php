<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AttachmentViewsTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function editAttachmentTemplateProvider(): iterable
    {
        yield 'admin event attachments' => array(JEM_TEST_ROOT . '/admin/views/event/tmpl/edit_attachments.php');
        yield 'admin venue attachments' => array(JEM_TEST_ROOT . '/admin/views/venue/tmpl/edit_attachments.php');
        yield 'site attachments edit' => array(JEM_TEST_ROOT . '/site/common/views/tmpl/default_attachments_edit.php');
    }

    #[DataProvider('editAttachmentTemplateProvider')]
    public function testEditAttachmentTemplatesRenderGlobalOptions(string $path): void
    {
        $template = $this->read($path);

        self::assertStringContainsString('jem-attachments-global-options', $template);
        self::assertStringContainsString("renderField('attachments_layout', 'attribs')", $template);
        self::assertStringContainsString("renderField('attachments_icon_size', 'attribs')", $template);
        self::assertStringContainsString("renderField('attachments_frame', 'attribs')", $template);
    }

    #[DataProvider('editAttachmentTemplateProvider')]
    public function testEditAttachmentTemplatesKeepRowClassesForStyling(string $path): void
    {
        $template = $this->read($path);

        self::assertStringContainsString('jem-attachment-row', $template);
        self::assertStringContainsString('jem-attachment-upload-row', $template);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function publicAttachmentTemplateProvider(): iterable
    {
        yield 'legacy public attachments' => array(JEM_TEST_ROOT . '/site/common/views/tmpl/default_attachments.php');
        yield 'responsive public attachments' => array(JEM_TEST_ROOT . '/site/common/views/tmpl/responsive/default_attachments.php');
    }

    #[DataProvider('publicAttachmentTemplateProvider')]
    public function testPublicAttachmentTemplatesUseDisplayHelper(string $path): void
    {
        $template = $this->read($path);

        self::assertStringContainsString('/helpers/attachmentdisplay.php', $template);
        self::assertStringContainsString('JemAttachmentDisplayHelper::resolveLayout', $template);
        self::assertStringContainsString('JemAttachmentDisplayHelper::resolveIconSize', $template);
        self::assertStringContainsString('JemAttachmentDisplayHelper::frameClass', $template);
    }

    #[DataProvider('publicAttachmentTemplateProvider')]
    public function testPublicAttachmentTemplatesExposeLayoutIconAndFrameClasses(string $path): void
    {
        $template = $this->read($path);

        self::assertStringContainsString('jem-attachments-list', $template);
        self::assertStringContainsString('jem-attachments-layout-', $template);
        self::assertStringContainsString('jem-attachments-icons-', $template);
        self::assertStringContainsString('$attachmentsFrameClass', $template);
    }

    public function testBackendAttachmentListShowsDownloadStatistics(): void
    {
        $template = $this->read(JEM_TEST_ROOT . '/admin/views/attachments/tmpl/default.php');
        $model = $this->read(JEM_TEST_ROOT . '/admin/models/attachments.php');

        self::assertStringContainsString("'COM_JEM_ATTACHMENT_DOWNLOADS', 'a.downloads'", $template);
        self::assertStringContainsString("'COM_JEM_ATTACHMENT_LAST_DOWNLOAD', 'a.last_download'", $template);
        self::assertStringContainsString('(int) $item->downloads', $template);
        self::assertStringContainsString("'downloads', 'a.downloads'", $model);
        self::assertStringContainsString("'last_download', 'a.last_download'", $model);
    }

    public function testSuccessfulFrontendAndBackendDeliveriesAreRecorded(): void
    {
        $frontendController = $this->read(JEM_TEST_ROOT . '/site/controller.php');
        $backendController = $this->read(JEM_TEST_ROOT . '/admin/controllers/attachments.php');
        $attachmentClass = $this->read(JEM_TEST_ROOT . '/site/classes/attachment.class.php');

        foreach (array($frontendController, $backendController) as $controller) {
            self::assertStringContainsString('$delivered = readfile($path);', $controller);
            self::assertStringContainsString('if ($delivered !== false)', $controller);
            self::assertStringContainsString('JemAttachment::recordDownload($id);', $controller);
        }

        self::assertStringContainsString('static public function recordDownload($id)', $attachmentClass);
        self::assertStringContainsString("quoteName('downloads') . ' = ' . \$db->quoteName('downloads') . ' + 1'", $attachmentClass);
        self::assertStringContainsString("quoteName('last_download')", $attachmentClass);
        self::assertStringContainsString('static public function logDownloadError', $attachmentClass);
    }

    private function read(string $path): string
    {
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
