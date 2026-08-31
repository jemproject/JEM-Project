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
        if (str_contains(str_replace('\\', '/', $path), '/site/views/editevent/')) {
            self::assertStringContainsString("'jem-editevent-tabs'", $template);
            self::assertStringNotContainsString("'myTab'", $template);
            self::assertStringContainsString("'recall' => !empty(\$this->item->id)", $template);
        }

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
        if (str_contains(str_replace('\\', '/', $path), '/site/views/editvenue/')) {
            self::assertStringContainsString("'jem-editvenue-tabs'", $template);
            self::assertStringNotContainsString("'myTab'", $template);
        }

        foreach ($partials as $partial) {
            self::assertStringContainsString("loadTemplate('" . $partial . "')", $template);
        }

        foreach ($tabKeys as $tabKey) {
            self::assertStringContainsString($tabKey, $template);
        }
    }

    public function testVenueImageTabUsesTheStructuredFullWidthFileLayout(): void
    {
        foreach (array(
            JEM_TEST_ROOT . '/site/views/editvenue/tmpl/edit.php',
            JEM_TEST_ROOT . '/site/views/editvenue/tmpl/responsive/edit.php',
        ) as $path) {
            self::assertStringContainsString(
                "'editvenue-extendedtab', Text::_('COM_JEM_IMAGE')",
                $this->read($path)
            );
        }

        foreach (array(
            JEM_TEST_ROOT . '/site/views/editvenue/tmpl/edit_extended.php',
            JEM_TEST_ROOT . '/site/views/editvenue/tmpl/responsive/edit_extended.php',
        ) as $path) {
            $template = $this->read($path);

            self::assertStringContainsString('class="jem-image-upload-label"', $template);
            self::assertStringContainsString('class="jem-image-file-input"', $template);
            self::assertStringContainsString('jem-image-action-button', $template);
            self::assertStringNotContainsString('jem-camera-button', $template);
        }

        foreach (array('/media/css/jem.css', '/media/css/jem-responsive.css') as $path) {
            $stylesheet = $this->read(JEM_TEST_ROOT . $path);

            self::assertStringContainsString(
                'grid-template-columns: minmax(11rem, 14rem) minmax(0, 1fr);',
                $stylesheet
            );
            self::assertStringContainsString('.jem-image-file-input input[type="file"]', $stylesheet);
        }
    }

    public function testFrontendEventImageControlsUseTheirOwnTab(): void
    {
        foreach (array(
            JEM_TEST_ROOT . '/site/views/editevent/tmpl/edit.php',
            JEM_TEST_ROOT . '/site/views/editevent/tmpl/responsive/edit.php',
        ) as $path) {
            $template = $this->read($path);
            $details = strpos($template, "'editevent-infotab'");
            $description = strpos($template, "getInput('articletext')");
            $imageTab = strpos($template, "'editevent-imagetab', Text::_('COM_JEM_IMAGE')");
            $imageFields = strpos($template, 'class="jem-editevent-image-fields"');
            $extended = strpos($template, "'editevent-extendedtab'");

            self::assertNotFalse($details, $path);
            self::assertNotFalse($description, $path);
            self::assertNotFalse($imageTab, $path);
            self::assertNotFalse($imageFields, $path);
            self::assertNotFalse($extended, $path);
            self::assertTrue($details < $description && $description < $imageTab, $path);
            self::assertTrue($imageTab < $imageFields && $imageFields < $extended, $path);
            $normalisedTemplate = str_replace("\r\n", "\n", $template);
            self::assertStringNotContainsString(
                ".jem-editevent-image-layout-choice {\n        grid-column: 1 / 4;",
                $normalisedTemplate
            );
        }
    }

    public function testFrontendVenueDetailsUseCompactSemanticSections(): void
    {
        foreach (array(
            JEM_TEST_ROOT . '/site/views/editvenue/tmpl/edit.php',
            JEM_TEST_ROOT . '/site/views/editvenue/tmpl/responsive/edit.php',
        ) as $path) {
            $template = $this->read($path);

            self::assertSame(4, substr_count($template, 'class="jem-editvenue-section"'), $path);
            self::assertStringContainsString("Text::_('COM_JEM_VENUE_DETAILS_OPTIONS')", $template);
            self::assertStringContainsString("Text::_('COM_JEM_ADDRESS')", $template);
            self::assertStringContainsString("Text::_('COM_JEM_COORDINATES')", $template);
            self::assertStringContainsString("Text::_('COM_JEM_CONTACT_INFO')", $template);
        }

        foreach (array('/media/css/jem.css', '/media/css/jem-responsive.css') as $path) {
            $stylesheet = $this->read(JEM_TEST_ROOT . $path);
            self::assertStringContainsString(
                '#jem.jem_editvenue fieldset.jem-editvenue-section {',
                $stylesheet
            );
        }
    }

    public function testFrontendEventImageProfilesProvideIndependentFilePreviews(): void
    {
        foreach (array(
            JEM_TEST_ROOT . '/site/views/editevent/tmpl/edit.php',
            JEM_TEST_ROOT . '/site/views/editevent/tmpl/responsive/edit.php',
        ) as $path) {
            $template = $this->read($path);

            self::assertSame(2, substr_count($template, 'jem-editevent-image-field jem-image-upload-panel'), $path);
            self::assertSame(2, substr_count($template, 'jem-image-selected-preview'), $path);
            self::assertGreaterThanOrEqual(2, substr_count($template, 'jem-image-preview-stage'), $path);
            self::assertStringContainsString("getInput('userfile')", $template);
            self::assertStringContainsString("getInput('fulluserfile')", $template);
        }

        $previewScript = $this->read(JEM_TEST_ROOT . '/media/js/other.js');
        self::assertStringContainsString('.jem-image-upload-panel input[type="file"]', $previewScript);
        self::assertStringNotContainsString("$('#jform_userfile').on('change'", $previewScript);

        $field = $this->read(JEM_TEST_ROOT . '/site/models/fields/imageselectevent.php');
        self::assertStringContainsString('search-placeholder=', $field);
        self::assertStringContainsString('min-term-length="1"', $field);
    }

    public function testFrontendEditToolbarsKeepTenPixelSeparationFromTabs(): void
    {
        foreach (array(
            '/site/views/editevent/tmpl/edit.php' => 'jem-editevent-toolbar',
            '/site/views/editevent/tmpl/responsive/edit.php' => 'jem-editevent-toolbar',
            '/site/views/editvenue/tmpl/edit.php' => 'jem-editvenue-toolbar',
            '/site/views/editvenue/tmpl/responsive/edit.php' => 'jem-editvenue-toolbar',
        ) as $path => $class) {
            self::assertStringContainsString('class="' . $class . '"', $this->read(JEM_TEST_ROOT . $path));
        }

        foreach (array('/media/css/jem.css', '/media/css/jem-responsive.css') as $path) {
            $stylesheet = $this->read(JEM_TEST_ROOT . $path);

            self::assertStringContainsString('#jem .jem-editevent-toolbar,', $stylesheet);
            self::assertStringContainsString('#jem .jem-editvenue-toolbar {', $stylesheet);
            self::assertStringContainsString('margin: 0 0 0.625rem;', $stylesheet);
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

    public function testEveryJoomlaUiTabSetIsClosedAndUsesItsDeclaredId(): void
    {
        $filesChecked = 0;

        foreach (array('/admin/views', '/site/views') as $relativeRoot) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(JEM_TEST_ROOT . $relativeRoot, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
                    continue;
                }

                $source = (string) file_get_contents($file->getPathname());
                if (!str_contains($source, "HTMLHelper::_('uitab.startTabSet'")) {
                    continue;
                }

                $filesChecked++;
                preg_match_all("/HTMLHelper::_\\('uitab\\.startTabSet',\\s*'([^']+)'/", $source, $starts);
                preg_match_all("/HTMLHelper::_\\('uitab\\.addTab',\\s*'([^']+)'/", $source, $tabs);

                self::assertCount(
                    count($starts[1]),
                    array_fill(0, substr_count($source, "HTMLHelper::_('uitab.endTabSet'"), true),
                    $file->getPathname() . ' must close every Joomla UI tab set.'
                );

                foreach ($tabs[1] as $tabSetId) {
                    self::assertContains(
                        $tabSetId,
                        $starts[1],
                        $file->getPathname() . ' must add tabs to a tab set declared in the same template.'
                    );
                }
            }
        }

        self::assertGreaterThan(0, $filesChecked);
    }

    private function read(string $path): string
    {
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
