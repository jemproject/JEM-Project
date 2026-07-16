<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AcyMailingJemAddonTest extends TestCase
{
    private string $addon;

    protected function setUp(): void
    {
        $this->addon = (string) file_get_contents(JEM_TEST_ROOT . '/3rd/acymailing_jem/jem/plugin.php');
    }

    public function testAddonUsesAcyMailing10IntegrationApi(): void
    {
        self::assertStringContainsString('use AcyMailing\\Core\\AcymPlugin;', $this->addon);
        self::assertStringContainsString('class plgAcymJem extends AcymPlugin', $this->addon);
        self::assertStringContainsString("\$this->cms = 'Joomla';", $this->addon);
        self::assertStringContainsString('function getPossibleIntegrations(): ?object', $this->addon);
        self::assertStringContainsString('function insertionOptions(', $this->addon);
        self::assertStringContainsString('function replaceContent(', $this->addon);
        self::assertStringContainsString('function generateByCategory(', $this->addon);
    }

    public function testAddonDoesNotUseAcyMailing5Api(): void
    {
        self::assertStringNotContainsString('acymailing_config(', $this->addon);
        self::assertStringNotContainsString('acymailing_get(', $this->addon);
        self::assertStringNotContainsString('onAcymailing_getPluginType', $this->addon);
        self::assertStringNotContainsString('onAcymailing_replacetags', $this->addon);
        self::assertStringNotContainsString('ACYMAILING_COMPONENT', $this->addon);
    }

    public function testAddonSupportsIndividualAndAutomaticEventInsertion(): void
    {
        self::assertStringContainsString('$this->replaceMultiple($email);', $this->addon);
        self::assertStringContainsString('$this->replaceOne($email);', $this->addon);
        self::assertStringContainsString("extractTags(\$email, 'auto'.\$this->name)", $this->addon);
        self::assertStringContainsString('#__jem_cats_event_relations', $this->addon);
        self::assertStringContainsString('$this->rootCategoryId = 1;', $this->addon);
        self::assertStringContainsString('event.featured = 1', $this->addon);
        self::assertStringContainsString('COALESCE(event.enddates, event.dates)', $this->addon);
        self::assertStringContainsString("in_array('image', \$display, true)", $this->addon);
    }

    public function testAddonProvidesAnExplicitDynamicNextEventPreset(): void
    {
        self::assertStringContainsString("'next'.\$this->name", $this->addon);
        self::assertStringContainsString("\$parameter->jem_next_event = true", $this->addon);
        self::assertStringContainsString("\$parameter->max = 1", $this->addon);
        self::assertStringContainsString('ORDER BY event.dates ASC', $this->addon);
        self::assertStringContainsString('event.times ASC, event.id ASC', $this->addon);
        self::assertStringContainsString("COALESCE(event.endtimes, event.times)", $this->addon);
        self::assertStringContainsString("= '00:00:00'", $this->addon);
    }

    public function testAddonUsesValidatedJemMenuSelectorsAndShowsLinkPreview(): void
    {
        self::assertStringContainsString('private function loadJemMenuItems()', $this->addon);
        self::assertStringContainsString("published = 1 AND type = '.acym_escapeDB('component')", $this->addon);
        self::assertStringContainsString("'%option=com_jem%'", $this->addon);
        self::assertStringContainsString('private function validateJemMenuItemId(', $this->addon);
        self::assertStringContainsString("'label' => 'JEM menu item'", $this->addon);
        self::assertStringContainsString("'type' => 'select'", $this->addon);
        self::assertStringContainsString('does not render a JEM module', $this->addon);
        self::assertStringContainsString("'title' => 'Generated event link'", $this->addon);
        self::assertStringContainsString('jem-event-link-preview-', $this->addon);
        self::assertStringContainsString('before Joomla applies SEF routing', $this->addon);
        self::assertStringNotContainsString("'text' => 'Itemid'", $this->addon);
    }

    public function testAddonFiltersEventsAndCategoriesForEmailAudienceAndLanguage(): void
    {
        self::assertStringContainsString('loadUserById(0)', $this->addon);
        self::assertStringContainsString("event.access IN (", $this->addon);
        self::assertStringContainsString('audience_category.access IN (', $this->addon);
        self::assertStringContainsString('audience_category.published = 1', $this->addon);
        self::assertStringContainsString('event.language IN (', $this->addon);
        self::assertStringContainsString('audience_category.language IN (', $this->addon);
        self::assertStringContainsString("'type' => 'language'", $this->addon);
    }

    public function testAddonUsesNativeOptionsForFrontendAccessAndReadMore(): void
    {
        self::assertStringContainsString("'label' => 'ACYM_FRONT_ACCESS'", $this->addon);
        self::assertStringContainsString("\$this->getParam('front', 'all') === 'hide'", $this->addon);
        self::assertStringContainsString("'name' => 'readmore'", $this->addon);
        self::assertStringContainsString('if (!empty($tag->readmore))', $this->addon);
        self::assertStringNotContainsString("'readmore' => ['ACYM_READ_MORE', true]", $this->addon);
    }

    public function testAddonDocumentsItsSupportedInstallationTarget(): void
    {
        $readme = (string) file_get_contents(JEM_TEST_ROOT . '/3rd/acymailing_jem/README.md');

        self::assertStringContainsString('Joomla 5.4 or Joomla 6', $readme);
        self::assertStringContainsString('JEM 5.0.1', $readme);
        self::assertStringNotContainsString('Beta 1', $readme);
        self::assertStringContainsString('AcyMailing 10 (verified against 10.11.1)', $readme);
        self::assertStringContainsString('administrator/components/com_acym/dynamics/jem/plugin.php', $readme);
    }

    public function testJoomlaInstallerTargetsOnlyTheAcyMailingAddonDirectory(): void
    {
        $manifest = simplexml_load_file(JEM_TEST_ROOT . '/3rd/acymailing_jem/files_acym_jem.xml');

        self::assertNotFalse($manifest);
        self::assertSame('file', (string) $manifest['type']);
        self::assertSame('upgrade', (string) $manifest['method']);
        self::assertSame('JEM - Events for AcyMailing', (string) $manifest->name);
        self::assertSame('files_acym_jem', (string) $manifest->element);
        self::assertSame('5.0.1', (string) $manifest->version);
        self::assertSame('script.php', (string) $manifest->scriptfile);
        self::assertSame('jem', (string) $manifest->fileset->files['folder']);
        self::assertSame(
            'administrator/components/com_acym/dynamics/jem',
            (string) $manifest->fileset->files['target']
        );
        $installedFiles = array_map(
            static fn (SimpleXMLElement $file): string => (string) $file,
            iterator_to_array($manifest->fileset->files->filename, false)
        );

        self::assertSame(['plugin.php', 'icon.png', 'banner.png'], $installedFiles);

        foreach ($installedFiles as $installedFile) {
            self::assertFileExists(JEM_TEST_ROOT . '/3rd/acymailing_jem/jem/' . $installedFile);
        }

        self::assertSame([600, 348], array_slice(
            getimagesize(JEM_TEST_ROOT . '/3rd/acymailing_jem/jem/banner.png'),
            0,
            2
        ));
    }

    public function testConfigInfoDetectsAddonByStableExtensionElement(): void
    {
        $model = (string) file_get_contents(JEM_TEST_ROOT . '/admin/models/settings.php');
        $view = (string) file_get_contents(JEM_TEST_ROOT . '/admin/views/settings/tmpl/default_configinfo.php');
        $language = (string) file_get_contents(JEM_TEST_ROOT . '/admin/language/en-GB/com_jem.ini');

        self::assertStringContainsString("element = ' . \$db->quote('files_acym_jem')", $model);
        self::assertStringContainsString("\$extension->element === 'files_acym_jem'", $model);
        self::assertStringContainsString("'files_acym_jem'     => 'COM_JEM_MAIN_CONFIG_VS_ACYMAILING_JEM'", $view);
        self::assertStringContainsString(
            'COM_JEM_MAIN_CONFIG_VS_ACYMAILING_JEM="JEM - Events for AcyMailing"',
            $language
        );
    }


    public function testInstallerRegistersAddonWithoutOverwritingUserStateOrSettings(): void
    {
        $script = (string) file_get_contents(JEM_TEST_ROOT . '/3rd/acymailing_jem/script.php');

        self::assertStringContainsString("private const FOLDER_NAME = 'jem';", $script);
        self::assertStringContainsString("private const ADDON_VERSION = '5.0.1';", $script);
        self::assertStringContainsString("'title' => 'JEM - Events for AcyMailing'", $script);
        self::assertStringContainsString("'type' => 'ADDON'", $script);
        self::assertStringContainsString("'active'", $script);
        self::assertStringContainsString("if (\$existingId > 0)", $script);
        self::assertStringNotContainsString("'settings' =>", $script);
        self::assertStringContainsString("updateObject('#__acym_plugin'", $script);
        self::assertStringContainsString("insertObject('#__acym_plugin'", $script);
        self::assertStringContainsString('$addon = (object) $metadata;', $script);
        self::assertStringNotContainsString("updateObject('#__acym_plugin', (object)", $script);
        self::assertStringNotContainsString("insertObject('#__acym_plugin', (object)", $script);
        self::assertStringContainsString('CMSApplicationInterface $app', $script);
        self::assertStringNotContainsString('AdministratorApplication $app', $script);
    }

    public function testLegacyAcyMailing5PluginWasRemoved(): void
    {
        self::assertFileDoesNotExist(JEM_TEST_ROOT . '/3rd/plg_tagjem/tagjem.php');
        self::assertFileDoesNotExist(JEM_TEST_ROOT . '/3rd/plg_tagjem/tagjem.xml');
        self::assertStringNotContainsString('plg_tagjem', (string) file_get_contents(JEM_TEST_ROOT . '/build.xml'));
    }
}
