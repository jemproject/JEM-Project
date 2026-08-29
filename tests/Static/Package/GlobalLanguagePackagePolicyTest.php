<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class GlobalLanguagePackagePolicyTest extends TestCase
{
    public function testCurrentPackageAndEveryNestedExtensionUseGlobalLanguageManifests(): void
    {
        if (!class_exists(ZipArchive::class)) {
            self::markTestSkipped('PHP zip extension is required to inspect package artifacts.');
        }

        $packageManifest = simplexml_load_file(JEM_TEST_ROOT . '/package/pkg_jem.xml');
        self::assertNotFalse($packageManifest);

        $packagePath = JEM_TEST_ROOT . '/pkg_jem_v' . (string) $packageManifest->version . '.zip';
        self::assertFileExists($packagePath, 'Build the current package before checking its language policy.');

        $package = new ZipArchive();
        self::assertTrue($package->open($packagePath));

        $manifest = $package->getFromName('pkg_jem.xml');
        self::assertNotFalse($manifest);
        $this->assertGlobalLanguageManifest(
            'pkg_jem.xml',
            $manifest,
            static fn (string $entry): bool => $package->locateName($entry) !== false
        );

        foreach ($this->nestedManifests() as $archiveEntry => $manifestEntry) {
            $archiveContents = $package->getFromName($archiveEntry);
            self::assertNotFalse($archiveContents, $archiveEntry . ' must exist.');

            $temporary = tempnam(sys_get_temp_dir(), 'jem_language_');
            self::assertIsString($temporary);
            file_put_contents($temporary, $archiveContents);

            $archive = new ZipArchive();
            self::assertTrue($archive->open($temporary), $archiveEntry . ' must be a readable ZIP.');

            $nestedManifest = $archive->getFromName($manifestEntry);
            self::assertNotFalse($nestedManifest, $archiveEntry . ':' . $manifestEntry . ' must exist.');
            $this->assertGlobalLanguageManifest(
                $archiveEntry . ':' . $manifestEntry,
                $nestedManifest,
                static fn (string $entry): bool => $archive->locateName($entry) !== false
            );

            $archive->close();
            unlink($temporary);
        }

        $package->close();
    }

    private function assertGlobalLanguageManifest(
        string $label,
        string $manifest,
        callable $entryExists
    ): void
    {
        $xml = new DOMDocument();
        self::assertTrue($xml->loadXML($manifest), $label . ' must be valid XML.');

        $xpath = new DOMXPath($xml);
        self::assertSame(
            0,
            $xpath->query('//files/folder[normalize-space(.) = "language"]')->length,
            $label . ' must not install a local language folder.'
        );

        $languageGroups = $xpath->query('//languages');
        self::assertGreaterThan(0, $languageGroups->length, $label . ' must declare global language files.');

        foreach ($languageGroups as $languageGroup) {
            self::assertInstanceOf(DOMElement::class, $languageGroup);
            $sourceFolder = trim($languageGroup->getAttribute('folder'), '/');

            foreach ($xpath->query('./language', $languageGroup) as $languageFile) {
                $entry = trim($languageFile->textContent, '/');
                if ($sourceFolder !== '') {
                    $entry = $sourceFolder . '/' . $entry;
                }

                self::assertTrue($entryExists($entry), $label . ' is missing declared language source ' . $entry . '.');
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function nestedManifests(): array
    {
        return array(
            'packages/com_jem.zip' => 'jem.xml',
            'packages/mod_jem.zip' => 'mod_jem.xml',
            'packages/mod_jem_banner.zip' => 'mod_jem_banner.xml',
            'packages/mod_jem_cal.zip' => 'mod_jem_cal.xml',
            'packages/mod_jem_jubilee.zip' => 'mod_jem_jubilee.xml',
            'packages/mod_jem_map.zip' => 'mod_jem_map.xml',
            'packages/mod_jem_teaser.zip' => 'mod_jem_teaser.xml',
            'packages/mod_jem_types.zip' => 'mod_jem_types.xml',
            'packages/mod_jem_wide.zip' => 'mod_jem_wide.xml',
            'packages/plg_actionlog_jem.zip' => 'jem.xml',
            'packages/plg_content_jemembed.zip' => 'jemembed.xml',
            'packages/plg_content_jemlistevents.zip' => 'jemlistevents.xml',
            'packages/plg_finder_jem.zip' => 'jem.xml',
            'packages/plg_jem_comments.zip' => 'comments.xml',
            'packages/plg_jem_mailer.zip' => 'mailer.xml',
            'packages/plg_quickicon_jem.zip' => 'jem.xml',
            'packages/plg_task_jem.zip' => 'jem.xml',
            'packages/plg_user_jem.zip' => 'jem.xml',
        );
    }
}
