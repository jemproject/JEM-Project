<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SecretLeakageTest extends TestCase
{
    public function testNoPrivateKeyBlocksAreCommitted(): void
    {
        $findings = array();

        foreach ($this->textFiles() as $path) {
            $relative = $this->relativePath($path);
            $contents = (string) file_get_contents($path);

            if (preg_match('/-----BEGIN (RSA |DSA |EC |OPENSSH |)PRIVATE KEY-----/', $contents) === 1) {
                $findings[] = $relative;
            }
        }

        sort($findings);

        self::assertSame(
            array(),
            $findings,
            "Private key material must not be committed:\n" . implode("\n", $findings)
        );
    }

    public function testNoHardcodedBearerOrApiSecretsAreCommitted(): void
    {
        $findings = array();
        $patterns = array(
            'bearer token' => '/\bBearer\s+[A-Za-z0-9._~+\/=-]{20,}/',
            'generic api secret assignment' => '/\b(api[_-]?key|api[_-]?secret|client[_-]?secret|access[_-]?token)\b\s*[=:]\s*[\'"][^\'"]{16,}[\'"]/i',
            'aws access key' => '/\bAKIA[0-9A-Z]{16}\b/',
            'slack token' => '/\bxox[baprs]-[A-Za-z0-9-]{20,}\b/',
        );

        foreach ($this->textFiles() as $path) {
            $relative = $this->relativePath($path);
            $contents = (string) file_get_contents($path);

            foreach ($patterns as $label => $pattern) {
                if (preg_match($pattern, $contents) === 1) {
                    $findings[] = $relative . ':' . $label;
                }
            }
        }

        sort($findings);

        self::assertSame(
            array(),
            $findings,
            "Possible hardcoded secrets need review:\n" . implode("\n", $findings)
        );
    }

    /**
     * @return iterable<string>
     */
    private function textFiles(): iterable
    {
        foreach (array('admin', 'site', 'modules', 'plugins', 'media') as $root) {
            $directory = JEM_TEST_ROOT . '/' . $root;

            if (!is_dir($directory)) {
                continue;
            }

            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($files as $file) {
                if ($file->isFile() && in_array(strtolower($file->getExtension()), array('php', 'js', 'xml', 'ini'), true)) {
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
