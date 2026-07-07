<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EditViewContractsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>, list<string>}>
     */
    public static function eventEditTemplateProvider(): iterable
    {
        yield 'admin event edit' => array(
            JEM_TEST_ROOT . '/admin/views/event/tmpl/edit.php',
            array('attachments', 'links', 'settings'),
            array('COM_JEM_EVENT_ATTACHMENTS_TAB', 'COM_JEM_EVENT_LINKS_TAB', 'COM_JEM_EVENT_SETTINGS_TAB'),
        );
        yield 'site event edit' => array(
            JEM_TEST_ROOT . '/site/views/editevent/tmpl/edit.php',
            array('extended', 'publish', 'attachments', 'links', 'other'),
            array('COM_JEM_EVENT_ATTACHMENTS_TAB', 'COM_JEM_EVENT_LINKS_TAB', 'COM_JEM_EVENT_OTHER_TAB'),
        );
        yield 'site responsive event edit' => array(
            JEM_TEST_ROOT . '/site/views/editevent/tmpl/responsive/edit.php',
            array('extended', 'publish', 'attachments', 'links', 'other'),
            array('COM_JEM_EVENT_ATTACHMENTS_TAB', 'COM_JEM_EVENT_LINKS_TAB', 'COM_JEM_EVENT_OTHER_TAB'),
        );
    }

    /**
     * @param list<string> $partials
     * @param list<string> $tabKeys
     */
    #[DataProvider('eventEditTemplateProvider')]
    public function testEventEditTemplatesExposeExpectedTabs(string $path, array $partials, array $tabKeys): void
    {
        $template = $this->read($path);

        self::assertStringContainsString("HTMLHelper::_('uitab.startTabSet'", $template);

        foreach ($partials as $partial) {
            self::assertStringContainsString("loadTemplate('" . $partial . "')", $template);
        }

        foreach ($tabKeys as $tabKey) {
            self::assertStringContainsString($tabKey, $template);
        }
    }

    /**
     * @return iterable<string, array{string, list<string>, list<string>}>
     */
    public static function venueEditTemplateProvider(): iterable
    {
        yield 'admin venue edit' => array(
            JEM_TEST_ROOT . '/admin/views/venue/tmpl/edit.php',
            array('attachments'),
            array('COM_JEM_EVENT_ATTACHMENTS_TAB'),
        );
        yield 'site venue edit' => array(
            JEM_TEST_ROOT . '/site/views/editvenue/tmpl/edit.php',
            array('extended', 'publish', 'attachments', 'other'),
            array('COM_JEM_EDITVENUE_ATTACHMENTS_TAB', 'COM_JEM_EDITVENUE_OTHER_TAB'),
        );
        yield 'site responsive venue edit' => array(
            JEM_TEST_ROOT . '/site/views/editvenue/tmpl/responsive/edit.php',
            array('extended', 'publish', 'attachments', 'other'),
            array('COM_JEM_EDITVENUE_ATTACHMENTS_TAB', 'COM_JEM_EDITVENUE_OTHER_TAB'),
        );
    }

    /**
     * @param list<string> $partials
     * @param list<string> $tabKeys
     */
    #[DataProvider('venueEditTemplateProvider')]
    public function testVenueEditTemplatesExposeExpectedTabs(string $path, array $partials, array $tabKeys): void
    {
        $template = $this->read($path);

        self::assertStringContainsString("HTMLHelper::_('uitab.startTabSet'", $template);

        foreach ($partials as $partial) {
            self::assertStringContainsString("loadTemplate('" . $partial . "')", $template);
        }

        foreach ($tabKeys as $tabKey) {
            self::assertStringContainsString($tabKey, $template);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function siteAttachmentStubProvider(): iterable
    {
        yield 'site event attachments' => array(JEM_TEST_ROOT . '/site/views/editevent/tmpl/edit_attachments.php');
        yield 'site responsive event attachments' => array(JEM_TEST_ROOT . '/site/views/editevent/tmpl/responsive/edit_attachments.php');
        yield 'site venue attachments' => array(JEM_TEST_ROOT . '/site/views/editvenue/tmpl/edit_attachments.php');
        yield 'site responsive venue attachments' => array(JEM_TEST_ROOT . '/site/views/editvenue/tmpl/responsive/edit_attachments.php');
    }

    #[DataProvider('siteAttachmentStubProvider')]
    public function testSiteAttachmentTemplatesReuseCommonEditPartial(string $path): void
    {
        $template = $this->read($path);

        self::assertStringContainsString('/components/com_jem/common/views/tmpl/default_attachments_edit.php', $template);
    }

    private function read(string $path): string
    {
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
