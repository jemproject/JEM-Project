<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PasswordFieldPrivacyTest extends TestCase
{
    public function testPasswordInputsDoNotRenderStoredValues(): void
    {
        $findings = array();

        foreach ($this->phpAndXmlFiles() as $path) {
            $relative = $this->relativePath($path);
            $contents = (string) file_get_contents($path);

            if (!preg_match_all('/<input\b[^>]*type\s*=\s*[\'"]password[\'"][^>]*>/i', $contents, $matches)) {
                continue;
            }

            foreach ($matches[0] as $input) {
                if (preg_match('/\bvalue\s*=\s*[\'"](?![\'"])/i', $input) === 1) {
                    $findings[] = $relative;
                    break;
                }
            }
        }

        sort($findings);

        self::assertSame(
            array(),
            $findings,
            "Password inputs must not render pre-filled values:\n" . implode("\n", $findings)
        );
    }

    /**
     * @return iterable<string>
     */
    private function phpAndXmlFiles(): iterable
    {
        foreach (array('admin', 'site', 'modules', 'plugins') as $root) {
            $directory = JEM_TEST_ROOT . '/' . $root;

            if (!is_dir($directory)) {
                continue;
            }

            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($files as $file) {
                if ($file->isFile() && in_array(strtolower($file->getExtension()), array('php', 'xml'), true)) {
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
