<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FrontendFormModeTest extends TestCase
{
    #[DataProvider('editLayouts')]
    public function testEditLayoutsProvideAnAccessibleAdvancedMode(string $relativePath): void
    {
        $source = $this->read($relativePath);

        self::assertStringContainsString("JemHelper::loadCss('frontend-form-mode')", $source);
        self::assertStringContainsString("'media/com_jem/js/frontend-form-mode.js'", $source);
        self::assertStringContainsString('data-jem-form-mode', $source);
        self::assertStringContainsString('data-jem-form-mode-toggle', $source);
        self::assertStringContainsString('aria-pressed="false"', $source);
        self::assertStringContainsString('jem-form-mode-state-indicator', $source);
        self::assertStringContainsString('data-jem-advanced-field', $source);
        self::assertStringContainsString("getInput('frontend_form_mode', 'attribs')", $source);

        $toggle = strpos($source, 'data-jem-form-mode-toggle');
        $save = strpos($source, "Joomla.submitbutton('");
        self::assertNotFalse($toggle);
        self::assertNotFalse($save);
        self::assertGreaterThan($save, $toggle);
        self::assertMatchesRegularExpression(
            '/data-jem-form-mode-toggle[^>]*>.*?COM_JEM_ADVANCED/s',
            $source
        );
    }

    #[DataProvider('eventLayouts')]
    public function testEventAdvancedFieldsAreGrouped(string $relativePath): void
    {
        $source = $this->read($relativePath);

        self::assertMatchesRegularExpression('/data-jem-advanced-field[^>]*>[^<]*<\?php echo \$this->form->getLabel\(\'timezone_mode\'\)/', $source);
        self::assertStringContainsString(
            'data-jem-advanced-field<?php echo $showWhenCustomTimezoneAttribute; ?>',
            $source
        );
        self::assertMatchesRegularExpression('/data-jem-advanced-field[^>]*>[^<]*<\?php echo \$this->form->getLabel\(\'type_id\'\)/', $source);
        self::assertMatchesRegularExpression(
            '/<fieldset\s+class="adminform jem-associated-article-options".*?data-jem-advanced-field>/s',
            $source
        );
    }

    #[DataProvider('eventLayouts')]
    public function testEventSelectorsUseTheAvailableContentWidth(string $relativePath): void
    {
        $source = $this->read($relativePath);

        self::assertStringContainsString(
            'grid-template-columns: minmax(160px, 276px) minmax(14rem, 1fr);',
            $source
        );
        self::assertMatchesRegularExpression(
            '/\.jem-associated-article-picker\s*\{.*?width:\s*100%;/s',
            $source
        );
        self::assertMatchesRegularExpression(
            '/\.jem-editevent-field-cats select\s*\{.*?width:\s*100%\s*!important;.*?max-width:\s*100%\s*!important;/s',
            $source
        );
    }

    #[DataProvider('eventLayouts')]
    public function testEventEasyPublishingFieldsAndAdvancedTabsAreOrdered(string $relativePath): void
    {
        $source = $this->read($relativePath);
        $publish = $this->read(str_replace('edit.php', 'edit_publish.php', $relativePath));

        self::assertSame(1, substr_count($source, "getLabel('access')"));
        self::assertSame(1, substr_count($source, "getLabel('published')"));
        self::assertStringNotContainsString("getLabel('access')", $publish);
        self::assertStringNotContainsString("getLabel('published')", $publish);
        self::assertStringContainsString('jem-editevent-field-featured', $source);
        self::assertStringContainsString("'<joomla-tab-element data-jem-advanced-field '", $source);

        $description = strpos($source, '<!-- EVENTDESCRIPTION -->');
        $access = strpos($source, "getLabel('access')");
        $otherTab = strpos($source, "'event-other'");
        $advancedTab = strpos($source, "'editevent-advancedtab'");
        $linksTab = strpos($source, "'event-links'");
        self::assertNotFalse($description);
        self::assertNotFalse($access);
        self::assertNotFalse($otherTab);
        self::assertNotFalse($advancedTab);
        self::assertNotFalse($linksTab);
        self::assertLessThan($description, $access);
        self::assertGreaterThan($otherTab, $advancedTab);
        self::assertGreaterThan($advancedTab, $linksTab);
    }

    #[DataProvider('venueLayouts')]
    public function testVenueAdvancedFieldsAreGrouped(string $relativePath): void
    {
        $source = $this->read($relativePath);

        self::assertMatchesRegularExpression('/data-jem-advanced-field[^>]*>[^<]*<\?php echo \$this->form->getLabel\(\'level\'\)/', $source);
        self::assertMatchesRegularExpression('/data-jem-advanced-field[^>]*>[^<]*<\?php echo \$this->form->getLabel\(\'type_id\'\)/', $source);

        $publish = $this->read(str_replace('edit.php', 'edit_publish.php', $relativePath));
        self::assertSame(1, substr_count($source, "getLabel('access')"));
        self::assertSame(1, substr_count($source, "getLabel('published')"));
        self::assertStringNotContainsString("getLabel('access')", $publish);
        self::assertStringNotContainsString("getLabel('published')", $publish);

        $otherTab = strpos($source, "'venue-other'");
        $advancedTab = strpos($source, "'venue-publishtab'");
        self::assertNotFalse($otherTab);
        self::assertNotFalse($advancedTab);
        self::assertGreaterThan($otherTab, $advancedTab);
    }

    #[DataProvider('venueImageLayouts')]
    public function testVenueImageResolutionIsAdvancedWhenAvailable(string $relativePath): void
    {
        $source = $this->read($relativePath);

        if (!str_contains($source, 'JemImageCamera::resolutionControl(')) {
            self::markTestSkipped('This maintained version does not provide per-upload resolution controls.');
        }

        self::assertMatchesRegularExpression(
            '/<div class="jem-image-resolution-slot" data-jem-advanced-field>\s*<\?php echo JemImageCamera::resolutionControl\(/',
            $source
        );
    }

    #[DataProvider('eventLayouts')]
    public function testEventImageResolutionsAreAdvancedWhenAvailable(string $relativePath): void
    {
        $source = $this->read($relativePath);

        if (!str_contains($source, 'JemImageCamera::resolutionControl(')) {
            self::markTestSkipped('This maintained version does not provide per-upload resolution controls.');
        }

        self::assertSame(
            2,
            preg_match_all(
                '/<div class="jem-image-resolution-slot" data-jem-advanced-field>\s*<\?php echo JemImageCamera::resolutionControl\(/',
                $source
            )
        );
    }

    #[DataProvider('eventLayouts')]
    public function testEventImageResolutionSlotUsesTheFullGridWidth(string $relativePath): void
    {
        $source = $this->read($relativePath);

        if (!str_contains($source, 'JemImageCamera::resolutionControl(')) {
            self::markTestSkipped('This maintained version does not provide per-upload resolution controls.');
        }

        self::assertStringContainsString(
            '.jem-editevent-image-field > .jem-image-resolution-slot',
            $source
        );
        self::assertMatchesRegularExpression(
            '/\.jem-editevent-image-field > \.jem-image-resolution-slot\s*\{[^}]*grid-area:\s*resolution;[^}]*width:\s*100%;[^}]*min-width:\s*0;/s',
            $source
        );
        self::assertMatchesRegularExpression(
            '/\.jem-image-resolution-slot\s*\{[^}]*width:\s*100%;[^}]*max-width:\s*100%;[^}]*min-width:\s*0;/s',
            $this->read('/media/css/image-camera.css')
        );
    }

    public function testSharedAssetsUseProgressiveEnhancementAndRevealInvalidFields(): void
    {
        $script = $this->read('/media/js/frontend-form-mode.js');
        $style = $this->read('/media/css/frontend-form-mode.css');

        self::assertStringContainsString('jem-form-mode--ready', $script);
        self::assertStringContainsString("modeInput.value = enabled ? 'advanced' : 'easy'", $script);
        self::assertStringContainsString('markAdvancedTabControls(form)', $script);
        self::assertStringContainsString('leaveHiddenAdvancedTab(form)', $script);
        self::assertStringContainsString("form.addEventListener('invalid'", $script);
        self::assertStringContainsString('aria-invalid="true"', $script);
        self::assertStringContainsString(
            '.jem-form-mode--ready:not(.jem-form-mode--advanced) [data-jem-advanced-field]',
            $style
        );
        self::assertStringContainsString('[data-jem-form-mode] [data-showon].hidden', $style);
        self::assertStringContainsString('--bs-btn-color: var(--body-color, #212529)', $style);
        self::assertStringContainsString('--bs-btn-hover-color: var(--btn-hover-color, #fff)', $style);
        self::assertStringContainsString('margin-inline-start: auto', $style);
        self::assertStringContainsString(
            '.jem-form-mode-toggle[aria-pressed="false"]:focus:not(:hover):not(:focus-visible)',
            $style
        );
        self::assertStringContainsString(
            '.jem-form-mode-toggle[aria-pressed="true"] .jem-form-mode-state-indicator',
            $style
        );
        self::assertMatchesRegularExpression(
            '/\.jem-form-mode-state-indicator\s*\{.*?background:\s*transparent;/s',
            $style
        );
        self::assertMatchesRegularExpression(
            '/\.jem-editevent-field-featured select,.*?min-width:\s*6\.5rem;.*?padding-inline-end:\s*2\.75rem;/s',
            $style
        );
    }

    #[DataProvider('modeFormDefinitions')]
    public function testFrontendModeIsStoredAndPreserved(string $formPath, string $adminTemplatePath): void
    {
        $form = $this->read($formPath);
        $adminTemplate = $this->read($adminTemplatePath);

        self::assertMatchesRegularExpression(
            '/<field name="frontend_form_mode" type="hidden"\s+default="easy"\s+filter="cmd"/s',
            $form
        );
        self::assertStringContainsString("getInput('frontend_form_mode', 'attribs')", $adminTemplate);
    }

    public function testAdvancedLabelIsInstalledAsAFrontendComponentLanguage(): void
    {
        $manifest = $this->read('/jem.xml');
        $language = $this->read('/site/language/en-GB/com_jem.ini');

        self::assertMatchesRegularExpression(
            '#<languages folder="site/language">\s*<language tag="en-GB">en-GB/com_jem\.ini</language>\s*</languages>#',
            $manifest
        );
        self::assertStringNotContainsString('<folder>language</folder>', $manifest);
        self::assertStringContainsString('COM_JEM_ADVANCED="Advanced"', $language);
    }

    public static function editLayouts(): array
    {
        return array(
            'event legacy' => array('/site/views/editevent/tmpl/edit.php'),
            'event responsive' => array('/site/views/editevent/tmpl/responsive/edit.php'),
            'venue legacy' => array('/site/views/editvenue/tmpl/edit.php'),
            'venue responsive' => array('/site/views/editvenue/tmpl/responsive/edit.php'),
        );
    }

    public static function eventLayouts(): array
    {
        return array(
            'legacy' => array('/site/views/editevent/tmpl/edit.php'),
            'responsive' => array('/site/views/editevent/tmpl/responsive/edit.php'),
        );
    }

    public static function venueLayouts(): array
    {
        return array(
            'legacy' => array('/site/views/editvenue/tmpl/edit.php'),
            'responsive' => array('/site/views/editvenue/tmpl/responsive/edit.php'),
        );
    }

    public static function venueImageLayouts(): array
    {
        return array(
            'legacy' => array('/site/views/editvenue/tmpl/edit_extended.php'),
            'responsive' => array('/site/views/editvenue/tmpl/responsive/edit_extended.php'),
        );
    }

    public static function modeFormDefinitions(): array
    {
        return array(
            'site event' => array('/site/models/forms/event.xml', '/admin/views/event/tmpl/edit.php'),
            'site venue' => array('/site/models/forms/venue.xml', '/admin/views/venue/tmpl/edit.php'),
            'admin event' => array('/admin/models/forms/event.xml', '/admin/views/event/tmpl/edit.php'),
            'admin venue' => array('/admin/models/forms/venue.xml', '/admin/views/venue/tmpl/edit.php'),
        );
    }

    private function read(string $relativePath): string
    {
        $path = JEM_TEST_ROOT . $relativePath;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
