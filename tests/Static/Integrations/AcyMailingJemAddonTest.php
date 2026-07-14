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
        self::assertStringContainsString('JEM 5.0.1 Beta 1', $readme);
        self::assertStringContainsString('AcyMailing 10 (verified against 10.11.1)', $readme);
        self::assertStringContainsString('administrator/components/com_acym/dynamics/jem/plugin.php', $readme);
    }

    public function testJoomlaInstallerTargetsOnlyTheAcyMailingAddonDirectory(): void
    {
        $manifest = simplexml_load_file(JEM_TEST_ROOT . '/3rd/acymailing_jem/files_acym_jem.xml');

        self::assertNotFalse($manifest);
        self::assertSame('file', (string) $manifest['type']);
        self::assertSame('upgrade', (string) $manifest['method']);
        self::assertSame('files_acym_jem', (string) $manifest->element);
        self::assertSame('5.0.1', (string) $manifest->version);
        self::assertSame('script.php', (string) $manifest->scriptfile);
        self::assertSame('jem', (string) $manifest->fileset->files['folder']);
        self::assertSame(
            'administrator/components/com_acym/dynamics/jem',
            (string) $manifest->fileset->files['target']
        );
        self::assertSame(['plugin.php', 'icon.svg'], array_map(
            static fn (SimpleXMLElement $file): string => (string) $file,
            iterator_to_array($manifest->fileset->files->filename, false)
        ));
    }


    public function testInstallerRegistersAddonWithoutOverwritingUserStateOrSettings(): void
    {
        $script = (string) file_get_contents(JEM_TEST_ROOT . '/3rd/acymailing_jem/script.php');

        self::assertStringContainsString("private const FOLDER_NAME = 'jem';", $script);
        self::assertStringContainsString("private const ADDON_VERSION = '5.0.1';", $script);
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
