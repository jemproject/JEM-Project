<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/classes/modulecssoverride.class.php';

final class ModuleCssOverrideTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/jem-module-css-override-' . bin2hex(random_bytes(6));
        self::assertTrue(mkdir($this->root, 0777, true));
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->root)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $entry) {
            if ($entry->isDir()) {
                rmdir($entry->getPathname());
            } else {
                unlink($entry->getPathname());
            }
        }

        rmdir($this->root);
    }

    public function testMapsEveryRenamedModuleStylesheetToItsCurrentName(): void
    {
        $mappings = JemModuleCssOverride::getMappings();

        self::assertCount(6, $mappings);
        self::assertSame(26, array_sum(array_map('count', $mappings)));
        self::assertSame('default.css', $mappings['mod_jem_teaser']['mod_jem_teaser.css']);
        self::assertSame('responsive.css', $mappings['mod_jem_wide']['mod_jem_wide_responsive.css']);
        self::assertSame('grid.css', $mappings['mod_jem_cal']['mod_jem_cal_grid.css']);
        self::assertSame('mod_jem_teaser.css', JemModuleCssOverride::getLegacyFileName('mod_jem_teaser', 'default'));
        self::assertSame('mod_jem_wide_iconfont.css', JemModuleCssOverride::getLegacyFileName('mod_jem_wide', 'iconfont.css'));
        self::assertSame('', JemModuleCssOverride::getLegacyFileName('mod_jem_types', 'default'));
    }

    public function testDiscoversPendingOverridesAndConflictsOnlyInsideApprovedLocations(): void
    {
        $pending = $this->write(
            'templates/cassiopeia/css/mod_jem_teaser/mod_jem_teaser.css',
            '.legacy-teaser {}'
        );
        $conflict = $this->write(
            'templates/cassiopeia/html/mod_jem_wide/mod_jem_wide_default.css',
            '.legacy-wide {}'
        );
        $this->write('templates/cassiopeia/html/mod_jem_wide/default.css', '.current-wide {}');
        $media = $this->write('media/mod_jem_cal/css/mod_jem_cal_grid.css', '.legacy-calendar {}');
        $this->write('templates/escape/css/mod_jem_teaser/mod_jem_teaser.css', '.not-selected {}');

        $items = JemModuleCssOverride::discover($this->root, array('cassiopeia', '../escape'));

        self::assertCount(3, $items);
        self::assertSame(
            array('pending', 'pending', 'conflict'),
            array_column($items, 'status')
        );
        self::assertSame(
            array($media, $pending, $conflict),
            array_column($items, 'source')
        );
        self::assertSame(
            array(
                '/media/mod_jem_cal/css/grid.css',
                '/templates/cassiopeia/css/mod_jem_teaser/default.css',
                '/templates/cassiopeia/html/mod_jem_wide/default.css',
            ),
            array_column($items, 'targetRelative')
        );
    }

    public function testMigrationRenamesSafeFilesAndNeverOverwritesAConflict(): void
    {
        $teaserSource = $this->write(
            'templates/cassiopeia/css/mod_jem_teaser/mod_jem_teaser.css',
            '.legacy-teaser {}'
        );
        $wideSource = $this->write(
            'templates/cassiopeia/html/mod_jem_wide/mod_jem_wide_default.css',
            '.legacy-wide {}'
        );
        $wideTarget = $this->write(
            'templates/cassiopeia/html/mod_jem_wide/default.css',
            '.current-wide {}'
        );

        $result = JemModuleCssOverride::migrate($this->root, array('cassiopeia'));
        $teaserTarget = $this->root . '/templates/cassiopeia/css/mod_jem_teaser/default.css';

        self::assertCount(1, $result['migrated']);
        self::assertCount(0, $result['failed']);
        self::assertCount(1, $result['skipped']);
        self::assertFileDoesNotExist($teaserSource);
        self::assertFileExists($teaserTarget);
        self::assertSame('.legacy-teaser {}', file_get_contents($teaserTarget));
        self::assertFileExists($wideSource);
        self::assertSame('.current-wide {}', file_get_contents($wideTarget));
    }

    public function testExclusiveMoveRejectsATargetCreatedBeforeTheWrite(): void
    {
        $source = $this->write('media/mod_jem_teaser/css/mod_jem_teaser.css', '.legacy {}');
        $target = $this->write('media/mod_jem_teaser/css/default.css', '.current {}');
        $method = new ReflectionMethod(JemModuleCssOverride::class, 'moveWithoutOverwrite');
        $method->setAccessible(true);

        self::assertFalse($method->invoke(null, $source, $target));
        self::assertFileExists($source);
        self::assertSame('.legacy {}', file_get_contents($source));
        self::assertSame('.current {}', file_get_contents($target));
    }

    private function write(string $relativePath, string $contents): string
    {
        $path = $this->root . '/' . $relativePath;
        $directory = dirname($path);

        if (!is_dir($directory)) {
            self::assertTrue(mkdir($directory, 0777, true));
        }

        self::assertNotFalse(file_put_contents($path, $contents));

        return str_replace('/', DIRECTORY_SEPARATOR, $path);
    }
}
