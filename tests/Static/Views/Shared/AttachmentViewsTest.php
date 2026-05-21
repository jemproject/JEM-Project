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

    private function read(string $path): string
    {
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
