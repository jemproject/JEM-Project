<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class FilePermissionGuardTest extends TestCase
{
    #[DataProvider('sqlFilesWithAttachmentDefaults')]
    public function testDefaultAttachmentTypesDoNotPermitExecutableOrBrowserActiveFiles(string $relativePath): void
    {
        $contents = (string) file_get_contents(JEM_TEST_ROOT . '/' . $relativePath);
        preg_match_all("/attachments_types'\\s*,\\s*'([^']+)'|SET\\s+`value`\\s*=\\s*'([^']+)'\\s+WHERE\\s+`keyname`\\s*=\\s*'attachments_types'/i", $contents, $matches, PREG_SET_ORDER);

        self::assertNotEmpty($matches, $relativePath . ' should define or update attachment file types.');

        $unsafe = array(
            'php', 'php3', 'php4', 'php5', 'phtml', 'phar',
            'cgi', 'pl', 'py', 'rb', 'asp', 'aspx', 'jsp',
            'sh', 'bash', 'cmd', 'bat', 'exe', 'dll', 'so',
            'js', 'mjs', 'html', 'htm', 'xhtml', 'svg',
        );

        foreach ($matches as $match) {
            $configuredTypes = $match[1] !== '' ? $match[1] : $match[2];
            $extensions = array_filter(array_map('trim', explode(',', strtolower($configuredTypes))));
            $dangerous = array_values(array_intersect($extensions, $unsafe));

            self::assertSame(
                array(),
                $dangerous,
                $relativePath . ' must not allow executable or browser-active attachment extensions by default.'
            );
        }
    }

    public function testAttachmentUploadKeepsPathAndFilePermissionSafeguards(): void
    {
        $contents = $this->phpWithoutComments('site/classes/attachment.class.php');

        self::assertStringContainsString('static protected function getSafeAttachmentPath', $contents);
        self::assertMatchesRegularExpression('/preg_match\\s*\\(\\s*[\'"]\\/\\^\\[a-z\\]\\+\\[0-9\\]\\+\\$\\/i[\'"]\\s*,\\s*\\$object\\s*\\)/', $contents);
        self::assertStringContainsString('Path::clean(JPATH_SITE', $contents);
        self::assertStringContainsString('strpos(strtolower($path), $baseCheck) !== 0', $contents);
        self::assertStringContainsString('is_uploaded_file($rec[\'tmp_name\'])', $contents);
        self::assertStringContainsString('self::hasUnsafeExtension($file)', $contents);
        self::assertStringContainsString('self::hasAllowedMime($rec[\'tmp_name\'], $fileext)', $contents);
        self::assertStringContainsString('JemImage::sanitize($path, $file)', $contents);
        self::assertStringContainsString('File::upload($rec[\'tmp_name\'], $filepath, false, false', $contents);
        self::assertStringContainsString('forbidden_ext_in_content', $contents);
    }

    public function testImageUploadAndDeleteStayInsideKnownJemImageFolders(): void
    {
        $contents = $this->phpWithoutComments('admin/controllers/imagehandler.php');

        foreach (array('events', 'venues', 'categories') as $folder) {
            self::assertStringContainsString('/images/jem/' . $folder . '/', $contents);
        }

        self::assertStringContainsString('Session::checkToken()', $contents);
        self::assertStringContainsString('$app->getIdentity()->authorise(\'core.manage\', \'com_jem\')', $contents);
        self::assertStringContainsString('$allowedFolders = array(\'events\', \'venues\', \'categories\')', $contents);
        self::assertStringContainsString('Path::clean($directories[$task])', $contents);
        self::assertStringContainsString('JemImage::sanitize($base_Dir, $file[\'name\'])', $contents);
        self::assertStringContainsString('strpos(strtolower($filepath), $baseCheck) !== 0', $contents);
        self::assertStringContainsString('InputFilter::getInstance()->clean($image, \'path\')', $contents);
        self::assertStringContainsString('strpos(strtolower($fullPath), $baseCheck) !== 0', $contents);
    }

    public function testSampleDataAttachmentsAreCopiedOnlyIntoAttachmentObjectFolders(): void
    {
        $contents = $this->phpWithoutComments('admin/models/sampledata.php');

        self::assertStringContainsString('Path::clean(JPATH_ROOT . \'/\' . $jemsettings->attachments_path)', $contents);
        self::assertMatchesRegularExpression('/preg_match\\s*\\(\\s*[\'"]\\/\\^attachment-\\(\\(\\?:event\\|venue\\)\\\\d\\+\\)-\\(\\.\\+\\)\\$\\/[\'"]/', $contents);
        self::assertStringContainsString('$filename = File::makeSafe($matches[2])', $contents);
        self::assertStringContainsString('$destination = Path::clean($attachmentBase . \'/\' . $object)', $contents);
        self::assertStringContainsString('Folder::create($destination)', $contents);
    }

    public function testRepositoryFilesAreNotWorldWritableOnPosixFileSystems(): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            self::assertTrue(true);
            return;
        }

        $findings = array();

        foreach ($this->repositoryFiles() as $path) {
            $mode = fileperms($path);

            if ($mode !== false && ($mode & 0002) !== 0) {
                $findings[] = $this->relativePath($path);
            }
        }

        sort($findings);

        self::assertSame(
            array(),
            $findings,
            "Repository files must not be world-writable:\n" . implode("\n", $findings)
        );
    }

    /**
     * @return iterable<array{string}>
     */
    public static function sqlFilesWithAttachmentDefaults(): iterable
    {
        yield 'install SQL' => array('admin/sql/install.mysql.utf8.sql');
        yield '4.5.0 migration SQL' => array('admin/sql/updates/mysql/4.5.0.sql');
    }

    private function phpWithoutComments(string $relativePath): string
    {
        $contents = (string) file_get_contents(JEM_TEST_ROOT . '/' . $relativePath);
        $tokens = token_get_all($contents);
        $clean = '';

        foreach ($tokens as $token) {
            if (is_array($token) && in_array($token[0], array(T_COMMENT, T_DOC_COMMENT), true)) {
                continue;
            }

            $clean .= is_array($token) ? $token[1] : $token;
        }

        return $clean;
    }

    /**
     * @return iterable<string>
     */
    private function repositoryFiles(): iterable
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(JEM_TEST_ROOT, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $relative = $this->relativePath($file->getPathname());

            if (preg_match('#^(\\.git|vendor|\\.phpunit\\.cache|build)/#', $relative) === 1) {
                continue;
            }

            yield $file->getPathname();
        }
    }

    private function relativePath(string $path): string
    {
        return str_replace('\\', '/', substr($path, strlen(JEM_TEST_ROOT) + 1));
    }
}
