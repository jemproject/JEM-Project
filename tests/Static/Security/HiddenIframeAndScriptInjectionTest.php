<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class HiddenIframeAndScriptInjectionTest extends TestCase
{
    public function testNoHiddenIframesAreIntroduced(): void
    {
        $findings = array();

        foreach ($this->sourceFiles() as $path) {
            $relative = $this->relativePath($path);
            $contents = (string) file_get_contents($path);

            if (preg_match('/<iframe\b[^>]*(display\s*:\s*none|visibility\s*:\s*hidden|opacity\s*:\s*0|\s(?:width|height)\s*=\s*[\'"]?0(?:[\'"\s>]))/i', $contents) === 1) {
                $findings[] = $relative;
            }
        }

        sort($findings);

        self::assertSame(
            array(),
            $findings,
            "Hidden iframes are not allowed:\n" . implode("\n", $findings)
        );
    }

    public function testNoDynamicExternalScriptInjectionIsIntroduced(): void
    {
        $findings = array();

        foreach ($this->sourceFiles() as $path) {
            $relative = $this->relativePath($path);
            $contents = (string) file_get_contents($path);

            if (preg_match('/document\.createElement\s*\(\s*[\'"]script[\'"]\s*\)/i', $contents) === 1) {
                $findings[] = $relative . ':createElement(script)';
            }

            if (preg_match('/appendChild\s*\(\s*script\s*\)/i', $contents) === 1) {
                $findings[] = $relative . ':appendChild(script)';
            }
        }

        sort($findings);

        self::assertSame(
            array(),
            $findings,
            "Dynamic script injection needs security review:\n" . implode("\n", $findings)
        );
    }

    /**
     * @return iterable<string>
     */
    private function sourceFiles(): iterable
    {
        foreach (array('admin', 'site', 'modules', 'plugins', 'media/js') as $root) {
            $directory = JEM_TEST_ROOT . '/' . $root;

            if (!is_dir($directory)) {
                continue;
            }

            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($files as $file) {
                if ($file->isFile() && in_array(strtolower($file->getExtension()), array('php', 'js', 'xml'), true)) {
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
