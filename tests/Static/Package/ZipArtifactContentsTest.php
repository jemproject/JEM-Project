<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ZipArtifactContentsTest extends TestCase
{
    public function testExistingPackageArtifactsDoNotContainDevelopmentFiles(): void
    {
        if (!class_exists(ZipArchive::class)) {
            self::markTestSkipped('PHP zip extension is required to inspect package artifacts.');
        }

        $zipFiles = $this->packageZipFiles();

        if ($zipFiles === array()) {
            self::markTestSkipped('No package ZIP artifacts found. Run the Ant build before inspecting artifact contents.');
        }

        $findings = array();

        foreach ($zipFiles as $zipFile) {
            $findings = array_merge($findings, $this->developmentEntriesInZip($zipFile));
        }

        sort($findings);

        self::assertSame(
            array(),
            $findings,
            "Package artifacts must not include development files:\n" . implode("\n", $findings)
        );
    }

    public function testBuildConfigurationTemplateDocumentsRequiredValues(): void
    {
        $template = JEM_TEST_ROOT . '/build.config.example';

        self::assertFileExists($template);

        $contents = (string) file_get_contents($template);

        foreach (array('cfg.name=jem', 'cfg.buildDir=build', 'cfg.localhostRoot=') as $expected) {
            self::assertStringContainsString($expected, $contents);
        }
    }

    public function testComponentPackageContainsOnlySupportedSqlUpdates(): void
    {
        if (!class_exists(ZipArchive::class)) {
            self::markTestSkipped('PHP zip extension is required to inspect package artifacts.');
        }

        $zipFiles = $this->packageZipFiles();

        if ($zipFiles === array()) {
            self::markTestSkipped('No package ZIP artifacts found. Run the Ant build before inspecting artifact contents.');
        }

        foreach ($zipFiles as $zipFile) {
            if (!$this->isCurrentPackageArtifact($zipFile)) {
                continue;
            }

            $this->assertSqlPackageContents($zipFile);
        }
    }

    /**
     * @return list<string>
     */
    private function packageZipFiles(): array
    {
        $files = array();

        foreach (array(JEM_TEST_ROOT, JEM_TEST_ROOT . '/build') as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && preg_match('/(?:pkg_jem|com_jem).*\.zip$/i', $file->getFilename()) === 1) {
                    $relative = $this->relativePath($file->getPathname());

                    if (str_starts_with($relative, 'build/package-check/')) {
                        continue;
                    }

                    if (str_starts_with($relative, '_old packages/')) {
                        continue;
                    }

                    if ($file->getFilename() === 'com_jem_check.zip') {
                        continue;
                    }

                    $files[] = $file->getPathname();
                }
            }
        }

        sort($files);

        return $files;
    }

    /**
     * @return list<string>
     */
    private function developmentEntriesInZip(string $zipFile): array
    {
        $zip = new ZipArchive();
        self::assertTrue($zip->open($zipFile), $this->relativePath($zipFile) . ' should be readable as a ZIP file.');

        $findings = array();

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = (string) $zip->getNameIndex($i);

            if ($this->isDevelopmentEntry($entry)) {
                $findings[] = $this->relativePath($zipFile) . ':' . $entry;
            }

            if (preg_match('/\.zip$/i', $entry) === 1) {
                $nestedFindings = $this->developmentEntriesInNestedZip($zip, $zipFile, $entry);
                $findings = array_merge($findings, $nestedFindings);
            }
        }

        $zip->close();

        return $findings;
    }

    /**
     * @return list<string>
     */
    private function developmentEntriesInNestedZip(ZipArchive $outerZip, string $outerZipFile, string $entry): array
    {
        $contents = $outerZip->getFromName($entry);

        if ($contents === false) {
            return array($this->relativePath($outerZipFile) . ':' . $entry . ' could not be read.');
        }

        $temporary = tempnam(sys_get_temp_dir(), 'jem_zip_');
        self::assertIsString($temporary);
        file_put_contents($temporary, $contents);

        $zip = new ZipArchive();
        self::assertTrue($zip->open($temporary), $this->relativePath($outerZipFile) . ':' . $entry . ' should be a readable nested ZIP file.');

        $findings = array();

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nestedEntry = (string) $zip->getNameIndex($i);

            if ($this->isDevelopmentEntry($nestedEntry)) {
                $findings[] = $this->relativePath($outerZipFile) . ':' . $entry . ':' . $nestedEntry;
            }
        }

        $zip->close();
        unlink($temporary);

        return $findings;
    }

    private function assertSqlPackageContents(string $zipFile): void
    {
        $zip = new ZipArchive();
        self::assertTrue($zip->open($zipFile), $this->relativePath($zipFile) . ' should be readable as a ZIP file.');

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = (string) $zip->getNameIndex($i);

            if (preg_match('#(^|/)com_jem\.zip$#i', $entry) !== 1) {
                continue;
            }

            $contents = $zip->getFromName($entry);
            self::assertNotFalse($contents, $this->relativePath($zipFile) . ':' . $entry . ' should be readable.');
            $zip->close();
            $this->assertComponentSqlPackageContents((string) $contents, $this->relativePath($zipFile) . ':' . $entry);
            return;
        }

        $zip->close();

        if (preg_match('/com_jem.*\.zip$/i', basename($zipFile)) === 1) {
            $this->assertComponentSqlPackageContents((string) file_get_contents($zipFile), $this->relativePath($zipFile));
        }
    }

    private function assertComponentSqlPackageContents(string $contents, string $label): void
    {
        $temporary = tempnam(sys_get_temp_dir(), 'jem_sql_zip_');
        self::assertIsString($temporary);
        file_put_contents($temporary, $contents);

        $zip = new ZipArchive();
        self::assertTrue($zip->open($temporary), $label . ' should be a readable component ZIP file.');

        $entries = array();
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entries[] = trim(str_replace('\\', '/', (string) $zip->getNameIndex($i)), '/');
        }

        $zip->close();
        unlink($temporary);

        $entries = array_map(array($this, 'componentRelativeEntry'), $entries);

        self::assertContains('admin/sql/install.mysql.utf8.sql', $entries, $label . ' must include the install SQL schema.');

        $updates = array_values(array_filter($entries, static function (string $entry): bool {
            return preg_match('#^admin/sql/updates/mysql/.+\.sql$#', $entry) === 1;
        }));
        sort($updates);

        self::assertSame(
            array(
                'admin/sql/updates/mysql/4.1.0.sql',
                'admin/sql/updates/mysql/4.2.0.sql',
                'admin/sql/updates/mysql/4.2.1.sql',
                'admin/sql/updates/mysql/4.2.2.sql',
                'admin/sql/updates/mysql/4.3.0.sql',
                'admin/sql/updates/mysql/4.3.1.sql',
                'admin/sql/updates/mysql/4.3.2.sql',
                'admin/sql/updates/mysql/4.3.3.sql',
                'admin/sql/updates/mysql/4.3.4.sql',
                'admin/sql/updates/mysql/4.4.0.sql',
                'admin/sql/updates/mysql/4.4.1.sql',
                'admin/sql/updates/mysql/4.4.2.sql',
                'admin/sql/updates/mysql/4.5.0.sql',
            ),
            $updates,
            $label . ' must include SQL updates from 4.1.0 onward only.'
        );
    }

    private function isDevelopmentEntry(string $entry): bool
    {
        $entry = trim(str_replace('\\', '/', $entry), '/');

        if ($entry === '') {
            return false;
        }

        if (preg_match('#(^|/)media/vendor(/|$)#i', $entry) === 1) {
            return false;
        }

        return preg_match(
            '#(^|/)(tests|vendor|\.phpunit\.cache|\.agents|\.claude|\.codex|\.cursor|\.github/copilot)(/|$)|(^|/)(composer\.json|composer\.lock|phpunit\.xml|phpunit\.xml\.dist|\.env(?:\..*)?|.*\.(?:pem|key|crt|pfx|bak|orig|log))$#i',
            $entry
        ) === 1;
    }

    private function componentRelativeEntry(string $entry): string
    {
        return preg_replace('#^com_jem/#', '', $entry) ?? $entry;
    }

    private function isCurrentPackageArtifact(string $zipFile): bool
    {
        $relative = $this->relativePath($zipFile);

        return str_contains(basename($zipFile), '4.5.0beta5')
            || str_starts_with($relative, 'build/pkg_stage/');
    }

    private function relativePath(string $path): string
    {
        return str_replace('\\', '/', str_starts_with($path, JEM_TEST_ROOT) ? substr($path, strlen(JEM_TEST_ROOT) + 1) : $path);
    }
}
