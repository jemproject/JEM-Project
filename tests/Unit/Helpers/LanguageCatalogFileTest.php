<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/admin/helpers/languagecatalog.php';

final class LanguageCatalogFileTest extends TestCase
{
    private const CATALOG_PATH = '/updatecheck/language_catalog_jem.xml';

    public function testRepositoryCatalogHasValidInstallMetadata(): void
    {
        $catalog = simplexml_load_file(JEM_TEST_ROOT . self::CATALOG_PATH);

        self::assertNotFalse($catalog);
        self::assertSame('jem-language-catalog', $catalog->getName());
        self::assertSame('1.0', (string) $catalog['version']);
        self::assertSame('major', (string) $catalog['compatibility']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', (string) $catalog['published']);

        $languageTags = array();
        $packageIds = array();
        $packageCount = 0;

        foreach ($catalog->language as $language) {
            $tag = (string) $language['tag'];

            self::assertMatchesRegularExpression('/^[a-z]{2,3}-[A-Z]{2}$/', $tag);
            self::assertNotSame('', trim((string) $language['name']));
            self::assertArrayNotHasKey($tag, $languageTags, 'Duplicate language tag: ' . $tag);
            $languageTags[$tag] = true;
            self::assertGreaterThanOrEqual(1, count($language->package));

            foreach ($language->package as $package) {
                $packageCount++;
                $id = (string) $package['id'];
                $version = (string) $package['version'];
                $jemBranch = (string) $package['jem'];
                $downloadUrl = (string) $package->download['url'];
                $urlParts = parse_url($downloadUrl);

                self::assertArrayNotHasKey($id, $packageIds, 'Duplicate package id: ' . $id);
                $packageIds[$id] = true;
                self::assertSame('com_jem_' . $tag, (string) $package['element']);
                self::assertSame('file', (string) $package['type']);
                self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+(?:\.\d+)*$/', $version);
                self::assertMatchesRegularExpression('/^\d+\.\d+$/', $jemBranch);
                self::assertStringStartsWith($jemBranch . '.', $version);
                self::assertContains((string) $package['language_layout'], array('extension-local', 'global'));
                self::assertMatchesRegularExpression('/^\d+$/', (string) $package['bytes']);
                self::assertGreaterThan(0, (int) $package['bytes']);
                self::assertIsArray($urlParts);
                self::assertSame('https', $urlParts['scheme'] ?? '');
                self::assertSame('www.joomlaeventmanager.net', $urlParts['host'] ?? '');
                self::assertSame('/download', $urlParts['path'] ?? '');
                self::assertMatchesRegularExpression('/^download=\d+%3A[a-z0-9-]+$/', $urlParts['query'] ?? '');
                self::assertSame('sha256', (string) $package->checksum['algorithm']);
                self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', trim((string) $package->checksum));
            }
        }

        self::assertCount(31, $languageTags);
        self::assertSame(31, $packageCount);
    }

    public function testCurrentPublishedInventoryTargetsJemFiveZero(): void
    {
        $catalog = simplexml_load_file(JEM_TEST_ROOT . self::CATALOG_PATH);

        self::assertNotFalse($catalog);

        foreach ($catalog->language as $language) {
            $packages = $language->xpath('./package[@jem="5.0"][@version="5.0.0"]');

            self::assertIsArray($packages);
            self::assertCount(1, $packages, 'Missing JEM 5.0 package for ' . (string) $language['tag']);
            self::assertSame('extension-local', (string) $packages[0]['language_layout']);
        }
    }

    public function testRepositoryCatalogPassesRuntimeValidation(): void
    {
        $xml = (string) file_get_contents(JEM_TEST_ROOT . self::CATALOG_PATH);
        $error = '';

        self::assertTrue(JemLanguageCatalogHelper::validateCatalogXml($xml, $error), $error);
        self::assertSame('', $error);
    }

    public function testRuntimeValidationRejectsExternalEntities(): void
    {
        $xml = '<?xml version="1.0"?><!DOCTYPE catalog [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><jem-language-catalog version="1.0" published="2026-08-29" compatibility="major" />';
        $error = '';

        self::assertFalse(JemLanguageCatalogHelper::validateCatalogXml($xml, $error));
        self::assertSame('external_entities', $error);
    }

    public function testRuntimeValidationRejectsAnUntrustedDownloadHost(): void
    {
        $xml = (string) file_get_contents(JEM_TEST_ROOT . self::CATALOG_PATH);
        $xml = preg_replace(
            '#https://www\.joomlaeventmanager\.net/download#',
            'https://example.org/download',
            $xml,
            1
        );
        $error = '';

        self::assertFalse(JemLanguageCatalogHelper::validateCatalogXml($xml, $error));
        self::assertSame('invalid_package', $error);
    }

    public function testRuntimeValidationRejectsDuplicatePackageIdentifiers(): void
    {
        $xml = (string) file_get_contents(JEM_TEST_ROOT . self::CATALOG_PATH);
        $xml = preg_replace('/id="ca-ES-5\.0\.0"/', 'id="bg-BG-5.0.0"', $xml, 1);
        $error = '';

        self::assertFalse(JemLanguageCatalogHelper::validateCatalogXml($xml, $error));
        self::assertSame('duplicate_package', $error);
    }

    public function testCompatibilityUsesTheInstalledJemMajorRelease(): void
    {
        $packages = array(
            array('jem' => '5.0', 'version' => '5.0.4'),
            array('jem' => '5.1', 'version' => '5.1.0'),
            array('jem' => '5.1', 'version' => '5.1.1'),
            array('jem' => '6.0', 'version' => '6.0.0'),
        );

        self::assertSame('5.1', JemLanguageCatalogHelper::getVersionBranch('5.1.0beta2'));
        self::assertSame('5', JemLanguageCatalogHelper::getVersionMajor('5.1.0beta2'));
        self::assertSame(
            '5.1.1',
            JemLanguageCatalogHelper::selectCompatiblePackage($packages, '5.1.0beta2')['version']
        );
        self::assertSame(
            '5.0.4',
            JemLanguageCatalogHelper::selectCompatiblePackage(array($packages[0]), '5.1.0beta2')['version']
        );
        self::assertSame(
            '6.0.0',
            JemLanguageCatalogHelper::selectCompatiblePackage($packages, '6.1.0')['version']
        );
        self::assertNull(JemLanguageCatalogHelper::selectCompatiblePackage($packages, '7.0.0'));
    }

    /**
     * @dataProvider packageStateProvider
     */
    public function testPackageActionPolicy(
        string $tag,
        bool $joomlaInstalled,
        string $installedVersion,
        ?array $package,
        string $expected
    ): void {
        self::assertSame(
            $expected,
            JemLanguageCatalogHelper::getPackageState($tag, $joomlaInstalled, $installedVersion, $package)
        );
    }

    public static function packageStateProvider(): iterable
    {
        $package = array('version' => '5.1.0');

        yield 'English is bundled with JEM' => array('en-GB', true, '', null, 'built_in');
        yield 'catalog branch does not match' => array('es-ES', true, '', null, 'incompatible');
        yield 'Joomla language is required' => array('es-ES', false, '', $package, 'joomla_required');
        yield 'package can be installed' => array('es-ES', true, '', $package, 'available');
        yield 'newer package can update' => array('es-ES', true, '5.1.0', array('version' => '5.1.1'), 'update');
        yield 'same package is installed' => array('es-ES', true, '5.1.0', $package, 'installed');
        yield 'newer local package remains installed' => array('es-ES', true, '5.1.2', $package, 'installed');
    }

    public function testLanguagesAreOrderedByDefaultThenJoomlaInstallationThenName(): void
    {
        $items = array(
            array('tag' => 'fr-FR', 'name' => 'French', 'joomla_installed' => false),
            array('tag' => 'de-DE', 'name' => 'German', 'joomla_installed' => true),
            array('tag' => 'bg-BG', 'name' => 'Bulgarian', 'joomla_installed' => false),
            array('tag' => 'es-ES', 'name' => 'Spanish', 'joomla_installed' => true),
            array('tag' => 'en-GB', 'name' => 'English', 'joomla_installed' => true),
        );

        $sorted = JemLanguageCatalogHelper::sortLanguageItems($items, 'es-ES');

        self::assertSame(
            array('es-ES', 'en-GB', 'de-DE', 'bg-BG', 'fr-FR'),
            array_column($sorted, 'tag')
        );
    }

    public function testDownloadedPackageManifestMustMatchCatalogMetadata(): void
    {
        $package = array(
            'element' => 'com_jem_es-ES',
            'tag' => 'es-ES',
            'version' => '5.1.0',
        );
        $manifest = '<extension type="file" version="5.0"><name>com_jem_es-ES</name><tag>es-ES</tag><version>5.1.0</version></extension>';

        self::assertTrue(JemLanguageCatalogHelper::validatePackageManifestXml($manifest, $package));
        self::assertFalse(JemLanguageCatalogHelper::validatePackageManifestXml(
            str_replace('<tag>es-ES</tag>', '<tag>fr-FR</tag>', $manifest),
            $package
        ));
        self::assertFalse(JemLanguageCatalogHelper::validatePackageManifestXml(
            str_replace('<version>5.1.0</version>', '<version>5.0.0</version>', $manifest),
            $package
        ));
    }

    public function testDownloadedPackageManifestRejectsExternalEntities(): void
    {
        $package = array(
            'element' => 'com_jem_es-ES',
            'tag' => 'es-ES',
            'version' => '5.1.0',
        );
        $manifest = '<!DOCTYPE extension [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><extension type="file"><name>com_jem_es-ES</name><tag>es-ES</tag><version>5.1.0</version></extension>';

        self::assertFalse(JemLanguageCatalogHelper::validatePackageManifestXml($manifest, $package));
    }
}
