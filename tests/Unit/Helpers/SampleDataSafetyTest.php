<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/admin/classes/sampledataimport.php';
require_once JEM_TEST_ROOT . '/admin/classes/sampledatafiles.php';

final class SampleDataSafetyTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = str_replace('\\', '/', sys_get_temp_dir()) . '/jem-sample-unit-' . bin2hex(random_bytes(8));
        mkdir($this->directory);
        file_put_contents($this->directory . '/source.webp', 'fixture');
    }

    protected function tearDown(): void
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $entry) {
            $entry->isDir() && !$entry->isLink() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($this->directory);
    }

    public function testConfigurationAndSqlPolicies(): void
    {
        foreach ([null, '', '  ', '{}', '[]'] as $empty) {
            self::assertFalse(JemSampleDataImport::hasFieldConfiguration($empty));
        }
        self::assertTrue(JemSampleDataImport::hasFieldConfiguration('{"event":{"custom1":{"enabled":0}}}'));
        self::assertTrue(JemSampleDataImport::hasFieldConfiguration('invalid but existing'));
        $config = "-- Demo config\nINSERT INTO `#__jem_config` (`keyname`, `value`) VALUES ('custom_fields_config', '{}')";
        self::assertSame([], JemSampleDataImport::queries($config, true));
        self::assertSame([$config], JemSampleDataImport::queries($config, false));
        $query = "-- Types\nINSERT INTO `#__jem_types` (`name`, `alias`, `description`, `entity`, `created`) VALUES\n('Concert', 'concert', 'A, B (live)', 1, NOW()),\n('Talk', 'talk', 'It''s a talk', 3, NOW())";
        $queries = JemSampleDataImport::queries($query, false);
        self::assertCount(2, $queries);
        self::assertStringContainsString("`alias` = 'concert' AND `entity` = 1", $queries[0]);
        self::assertStringContainsString("`alias` = 'talk' AND `entity` = 3", $queries[1]);
        self::assertStringContainsString("'A, B (live)'", $queries[0]);
        self::assertStringContainsString("'It''s a talk'", $queries[1]);
        self::assertStringContainsString('WHERE NOT EXISTS', $queries[0]);
    }

    public function testCopiesAndGeneratedThumbnailsAreRemovedButExistingFilesSurvive(): void
    {
        $journal = new JemSampleDataFiles([$this->directory]);
        $journal->copy($this->directory . '/source.webp', $this->directory . '/source.webp');
        $journal->copy($this->directory . '/source.webp', $this->directory . '/new/original.webp');
        $journal->generate($this->directory . '/new/small/image.webp', static function ($temporary) {
            self::assertSame('webp', pathinfo($temporary, PATHINFO_EXTENSION));
            return file_put_contents($temporary, 'generated') !== false;
        });
        self::assertFileExists($this->directory . '/new/small/image.webp');
        self::assertTrue($journal->rollback());
        self::assertDirectoryDoesNotExist($this->directory . '/new');
        self::assertSame('fixture', file_get_contents($this->directory . '/source.webp'));
    }

    public function testConflictingExistingFileIsNeverOverwritten(): void
    {
        file_put_contents($this->directory . '/existing.webp', 'original');
        $journal = new JemSampleDataFiles([$this->directory]);
        try {
            $journal->copy($this->directory . '/source.webp', $this->directory . '/existing.webp');
            self::fail('A conflicting file must fail the import.');
        } catch (RuntimeException $error) {
            self::assertStringContainsString('conflicts', $error->getMessage());
        }
        self::assertTrue($journal->rollback());
        self::assertSame('original', file_get_contents($this->directory . '/existing.webp'));
    }

    public function testFailedThumbnailGenerationIsRecoverable(): void
    {
        $journal = new JemSampleDataFiles([$this->directory]);
        try {
            $journal->generate($this->directory . '/new/thumb.webp', static function ($temporary) {
                file_put_contents($temporary, 'partial');
                throw new RuntimeException('forced failure');
            });
            self::fail('Generation should fail.');
        } catch (RuntimeException $error) {
            self::assertSame('forced failure', $error->getMessage());
        }
        self::assertTrue($journal->rollback());
        self::assertDirectoryDoesNotExist($this->directory . '/new');
    }

    public function testIncompleteRecoveryDoesNotDeleteUnrelatedFiles(): void
    {
        $journal = new JemSampleDataFiles([$this->directory]);
        $journal->copy($this->directory . '/source.webp', $this->directory . '/new/image.webp');
        file_put_contents($this->directory . '/new/unrelated.txt', 'keep');
        self::assertFalse($journal->rollback());
        self::assertFileDoesNotExist($this->directory . '/new/image.webp');
        self::assertSame('keep', file_get_contents($this->directory . '/new/unrelated.txt'));
    }

    public function testOutOfRootDestinationIsRejected(): void
    {
        $journal = new JemSampleDataFiles([$this->directory . '/media']);
        $this->expectException(RuntimeException::class);
        $journal->copy($this->directory . '/source.webp', $this->directory . '/outside.webp');
    }
}
