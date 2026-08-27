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

        $zipFiles = $this->currentPackageZipFiles();

        if ($zipFiles === array()) {
            self::markTestSkipped('No current package ZIP artifacts found. Run the build before inspecting artifact contents.');
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

    public function testExistingPackageArtifactsContainComponentSqlInstallerFiles(): void
    {
        if (!class_exists(ZipArchive::class)) {
            self::markTestSkipped('PHP zip extension is required to inspect package artifacts.');
        }

        $zipFiles = $this->currentPackageZipFiles();

        if ($zipFiles === array()) {
            self::markTestSkipped('No package ZIP artifacts found. Run the build before inspecting artifact contents.');
        }

        $missing = array();

        foreach ($zipFiles as $zipFile) {
            $zip = new ZipArchive();
            self::assertTrue($zip->open($zipFile), $this->relativePath($zipFile) . ' should be readable as a ZIP file.');

            $componentZip = $zip->getFromName('packages/com_jem.zip');
            $zip->close();

            if ($componentZip === false) {
                $missing[] = $this->relativePath($zipFile) . ':packages/com_jem.zip';
                continue;
            }

            $temporary = tempnam(sys_get_temp_dir(), 'jem_component_');
            self::assertIsString($temporary);
            file_put_contents($temporary, $componentZip);

            $component = new ZipArchive();
            self::assertTrue($component->open($temporary), $this->relativePath($zipFile) . ':packages/com_jem.zip should be readable.');

            foreach (array(
                'admin/sql/install.mysql.utf8.sql',
                'admin/sql/uninstall.mysql.utf8.sql',
                'admin/sql/updates/mysql/4.5.0.sql',
                'admin/sql/updates/mysql/5.0.0.sql',
                'admin/sql/updates/mysql/5.0.1.sql',
            ) as $entry) {
                if ($component->locateName($entry) === false) {
                    $missing[] = $this->relativePath($zipFile) . ':packages/com_jem.zip:' . $entry;
                }
            }

            $component->close();
            unlink($temporary);
        }

        self::assertSame(array(), $missing, "Package artifacts must include component SQL installer/update files:\n" . implode("\n", $missing));
    }

    public function testExistingPackageArtifactsContainRecentJoomla6Fixes(): void
    {
        if (!class_exists(ZipArchive::class)) {
            self::markTestSkipped('PHP zip extension is required to inspect package artifacts.');
        }

        $zipFiles = $this->currentPackageZipFiles();

        if ($zipFiles === array()) {
            self::markTestSkipped('No package ZIP artifacts found. Run the build before inspecting artifact contents.');
        }

        foreach ($zipFiles as $zipFile) {
            $source = $this->relativePath($zipFile) . ':packages/com_jem.zip';

            self::assertStringContainsString(
                'class="jem-import-grid"',
                $this->componentEntryContents($zipFile, 'admin/views/import/tmpl/default.php'),
                $source . ':admin/views/import/tmpl/default.php should include the responsive import grid wrapper.'
            );

            self::assertStringContainsString(
                '.jem-import-grid',
                $this->componentEntryContents($zipFile, 'media/css/backend.css'),
                $source . ':media/css/backend.css should include import grid styles.'
            );

            self::assertStringContainsString(
                'https://www.joomlaeventmanager.net/documentation/backend',
                $this->componentEntryContents($zipFile, 'admin/help/en-GB/documentation.html'),
                $source . ':admin/help/en-GB/documentation.html should include the online documentation map.'
            );

            self::assertDoesNotMatchRegularExpression(
                '/INSERT\s+INTO\s+`#__jem_categories`\s+VALUES\s*\(/i',
                $this->componentEntryContents($zipFile, 'admin/assets/sampledata.sql'),
                $source . ':admin/assets/sampledata.sql should use explicit columns for category sample data.'
            );

            $sampleDataSql = $this->componentEntryContents($zipFile, 'admin/assets/sampledata.sql');

            foreach (array('INSERT INTO `#__jem_types`', 'Museum Technology Talk at the Prado', 'INSERT INTO `#__jem_links`', 'INSERT INTO `#__jem_attachments`') as $expected) {
                self::assertStringContainsString(
                    $expected,
                    $sampleDataSql,
                    $source . ':admin/assets/sampledata.sql should contain the JEM 5 sample data from the 4.5 new-features branch.'
                );
            }

            foreach (array('event-prado-museum-technology-talk.webp', 'venue-museo-del-prado.webp', 'attachment-event1-dj-night-lineup.txt') as $entry) {
                self::assertTrue(
                    $this->componentNestedZipContains($zipFile, 'admin/assets/sampledata.zip', $entry),
                    $source . ':admin/assets/sampledata.zip should contain ' . $entry . '.'
                );
            }

            self::assertStringContainsString(
                'public $app;',
                $this->componentEntryContents($zipFile, 'admin/views/updatecheck/view.html.php'),
                $source . ':admin/views/updatecheck/view.html.php should not narrow JemAdminView::$app visibility.'
            );

            self::assertStringContainsString(
                '$selectedUpdate = $selectedUpdate ?: $highestPlatformUpdate;',
                $this->componentEntryContents($zipFile, 'admin/models/updatecheck.php'),
                $source . ':admin/models/updatecheck.php should fall back to the highest compatible platform XML update entry.'
            );

            self::assertStringContainsString(
                '$this->ensureTypeAssignmentSchema();',
                $this->componentEntryContents($zipFile, 'admin/models/sampledata.php'),
                $source . ':admin/models/sampledata.php should prepare type_id columns before loading sample data.'
            );
        }
    }

    public function testExistingPackageArtifactsContainCustomDateSeries(): void
    {
        if (!class_exists(ZipArchive::class)) {
            self::markTestSkipped('PHP zip extension is required to inspect package artifacts.');
        }

        $zipFiles = $this->currentPackageZipFiles();

        if ($zipFiles === array()) {
            self::markTestSkipped('No current package ZIP artifacts found. Run the build before inspecting Custom dates.');
        }

        foreach ($zipFiles as $zipFile) {
            $source = $this->relativePath($zipFile);
            $zip = new ZipArchive();
            self::assertTrue($zip->open($zipFile), $source . ' should be readable as a ZIP file.');
            $packageManifest = $zip->getFromName('pkg_jem.xml');
            $zip->close();

            self::assertNotFalse($packageManifest, $source . ':pkg_jem.xml should exist.');
            self::assertStringContainsString('Issue #2288', $packageManifest, $source . ' should publish the Custom dates release note.');
            self::assertStringContainsString(
                'final class JemEventSeriesSchedule',
                $this->componentEntryContents($zipFile, 'site/classes/eventseries.class.php'),
                $source . ' should include the custom-series schedule implementation.'
            );
            self::assertStringContainsString(
                'custom_schedule_editor',
                $this->componentEntryContents($zipFile, 'site/views/editevent/tmpl/edit_customschedule.php'),
                $source . ' should include the frontend Custom dates editor.'
            );
            $recurrenceJavaScript = $this->componentEntryContents($zipFile, 'media/js/recurrence.js');
            self::assertStringContainsString(
                "if (\$select_value === '7')",
                $recurrenceJavaScript,
                $source . ' should include the Custom dates JavaScript mode.'
            );
            self::assertStringNotContainsString(
                'custom_schedule_seed_row',
                $recurrenceJavaScript,
                $source . ' should keep the main event outside the additional-occurrence rows.'
            );

            $eventModel = $this->componentEntryContents($zipFile, 'admin/models/event.php');
            self::assertStringContainsString('completeCustomSeriesSchedule', $eventModel);
            self::assertStringContainsString('getCustomSeriesSchedule((int) $item->series_id, (int) $item->id)', $eventModel);
            self::assertStringContainsString("\$customSeriesScope = \$customSeriesIsRoot ? 'all' : 'occurrence';", $eventModel);
            self::assertStringContainsString("->select(\$db->quoteName('root_event_id'))", $eventModel);

            $updateSql = $this->componentEntryContents($zipFile, 'admin/sql/updates/mysql/5.0.1.sql');
            self::assertStringContainsString('ADD COLUMN `series_id`', $updateSql);
            self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `#__jem_event_series`', $updateSql);

            $installer = $this->componentEntryContents($zipFile, 'script.php');
            self::assertStringContainsString("'series_id'", $installer);
            self::assertStringContainsString("'#__jem_event_series'", $installer);
        }
    }

    public function testCurrentPackageContainsTransactionalRegistrationCapacityWriter(): void
    {
        if (!class_exists(ZipArchive::class)) {
            self::markTestSkipped('PHP zip extension is required to inspect package artifacts.');
        }

        foreach ($this->currentPackageZipFiles() as $zipFile) {
            $service = $this->componentEntryContents($zipFile, 'site/classes/registrationservice.class.php');

            self::assertStringContainsString('transactionStart()', $service);
            self::assertStringContainsString("' FOR UPDATE'", $service);
            self::assertStringContainsString('SUM(GREATEST(', $service);
            self::assertStringContainsString('saveMany(', $service);
        }
    }

    public function testCurrentPackageHashesMatchUpdateMetadata(): void
    {
        $manifest = simplexml_load_file(JEM_TEST_ROOT . '/package/pkg_jem.xml');
        $updates = simplexml_load_file(JEM_TEST_ROOT . '/update_pkg_jem.xml');
        self::assertNotFalse($manifest);
        self::assertNotFalse($updates);

        $version = (string) $manifest->version;
        $matchingUpdate = null;
        foreach ($updates->update as $update) {
            if ((string) $update->version === $version) {
                $matchingUpdate = $update;
                break;
            }
        }
        self::assertNotNull($matchingUpdate, 'The current package version requires update metadata.');

        foreach ($this->currentPackageZipFiles() as $zipFile) {
            foreach (array('sha256', 'sha384', 'sha512') as $algorithm) {
                self::assertSame(
                    strtolower(trim((string) $matchingUpdate->{$algorithm})),
                    strtolower(hash_file($algorithm, $zipFile)),
                    $this->relativePath($zipFile) . ' must match update_pkg_jem.xml ' . $algorithm . '.'
                );
            }
        }
    }

    /**
     * @return list<string>
     */
    private function currentPackageZipFiles(): array
    {
        $manifest = simplexml_load_file(JEM_TEST_ROOT . '/package/pkg_jem.xml');
        self::assertNotFalse($manifest);

        $version = (string) $manifest->version;

        return array_values(array_filter(
            $this->packageZipFiles(),
            static fn (string $path): bool => str_starts_with(basename($path), 'pkg_jem_v' . $version)
        ));
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

                    if (str_starts_with($relative, '.tmp/')) {
                        continue;
                    }

                    if (str_starts_with($relative, 'build/package-check/')) {
                        continue;
                    }

                    if (str_starts_with($relative, '_old builds/') || str_starts_with($relative, '_old packages/')) {
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

    private function componentEntryContents(string $packageZipFile, string $entryName): string
    {
        $zip = new ZipArchive();
        self::assertTrue($zip->open($packageZipFile), $this->relativePath($packageZipFile) . ' should be readable as a ZIP file.');

        $componentZip = $zip->getFromName('packages/com_jem.zip');
        $zip->close();

        self::assertNotFalse($componentZip, $this->relativePath($packageZipFile) . ':packages/com_jem.zip should exist.');

        $temporary = tempnam(sys_get_temp_dir(), 'jem_component_');
        self::assertIsString($temporary);
        file_put_contents($temporary, $componentZip);

        $component = new ZipArchive();
        self::assertTrue($component->open($temporary), $this->relativePath($packageZipFile) . ':packages/com_jem.zip should be readable.');

        $contents = $component->getFromName($entryName);

        $component->close();
        unlink($temporary);

        self::assertNotFalse($contents, $this->relativePath($packageZipFile) . ':packages/com_jem.zip:' . $entryName . ' should exist.');

        return $contents;
    }

    private function componentNestedZipContains(string $packageZipFile, string $outerEntryName, string $innerEntryName): bool
    {
        $contents = $this->componentEntryContents($packageZipFile, $outerEntryName);

        $temporary = tempnam(sys_get_temp_dir(), 'jem_nested_');
        self::assertIsString($temporary);
        file_put_contents($temporary, $contents);

        $zip = new ZipArchive();
        self::assertTrue($zip->open($temporary), $this->relativePath($packageZipFile) . ':packages/com_jem.zip:' . $outerEntryName . ' should be readable.');

        $exists = $zip->locateName($innerEntryName) !== false;

        $zip->close();
        unlink($temporary);

        return $exists;
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
            '#(^|/)(tests|vendor|\.phpunit\.cache|\.agents|\.claude|\.codex|\.cursor|\.github/copilot|_old[^/]*|old[^/]*)(/|$)|(^|/)(composer\.json|composer\.lock|phpunit\.xml|phpunit(?:\.[^.]+)?\.xml\.dist|\.env(?:\..*)?|.*\.(?:pem|key|crt|pfx|bak|orig|log|code-workspace))$#i',
            $entry
        ) === 1;
    }

    private function relativePath(string $path): string
    {
        return str_replace('\\', '/', str_starts_with($path, JEM_TEST_ROOT) ? substr($path, strlen(JEM_TEST_ROOT) + 1) : $path);
    }
}
