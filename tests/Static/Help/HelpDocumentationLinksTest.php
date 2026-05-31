<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class HelpDocumentationLinksTest extends TestCase
{
    public function testHelpPagesExposeOnlineManualSections(): void
    {
        $missing = array();
        $requiredLinks = array(
            'https://www.joomlaeventmanager.net/documentation/manual/backend',
            'https://www.joomlaeventmanager.net/documentation/manual/frontend',
            'https://www.joomlaeventmanager.net/documentation/manual/modules',
            'https://www.joomlaeventmanager.net/documentation/manual/plugins',
        );

        foreach ($this->helpHtmlFiles() as $path) {
            $html = (string) file_get_contents($path);

            foreach ($requiredLinks as $requiredLink) {
                if (strpos($html, $requiredLink) === false) {
                    $missing[] = $this->relativePath($path) . ' missing ' . $requiredLink;
                }
            }
        }

        self::assertSame(array(), $missing, "Help pages must link to the online manual sections:\n" . implode("\n", $missing));
    }

    public function testToolbarHelpButtonsUseOnlineManualUrls(): void
    {
        $missing = array();

        foreach ($this->viewPhpFiles() as $path) {
            $code = (string) file_get_contents($path);

            if (preg_match_all('/(?:ToolBarHelper|ToolbarHelper)::help\s*\(([^;]+)\);/', $code, $matches) === 0) {
                continue;
            }

            foreach ($matches[0] as $call) {
                if (
                    strpos($call, 'https://www.joomlaeventmanager.net/documentation/manual/') === false
                    && strpos($call, 'https://www.joomlaeventmanager.net/documentation/backend/') === false
                ) {
                    $missing[] = $this->relativePath($path) . ' uses ' . trim($call);
                }
            }
        }

        self::assertSame(array(), $missing, "Toolbar Help buttons should point to online JEM documentation:\n" . implode("\n", $missing));
    }

    /**
     * @return iterable<string>
     */
    private function helpHtmlFiles(): iterable
    {
        $root = JEM_TEST_ROOT . '/admin/help/en-GB';
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'html' && strtolower($file->getFilename()) !== 'index.html') {
                yield $file->getPathname();
            }
        }
    }

    /**
     * @return iterable<string>
     */
    private function viewPhpFiles(): iterable
    {
        foreach (array('admin/views', 'site/views') as $directory) {
            $root = JEM_TEST_ROOT . '/' . $directory;

            if (!is_dir($root)) {
                continue;
            }

            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($files as $file) {
                if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
                    yield $file->getPathname();
                }
            }
        }
    }

    private function relativePath(string $path): string
    {
        return str_replace('\\', '/', substr($path, strlen(JEM_TEST_ROOT) + 1));
    }
}
