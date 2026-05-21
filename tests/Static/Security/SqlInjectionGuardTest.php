<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SqlInjectionGuardTest extends TestCase
{
    public function testVenueDeleteCastsPrimaryKeysBeforeBuildingSql(): void
    {
        $contents = (string) file_get_contents(JEM_TEST_ROOT . '/admin/models/venue.php');
        $method = $this->extractMethod($contents, 'delete');

        self::assertStringContainsString('$pk = (int) $pk;', $method);
        self::assertStringContainsString('\'v.id = \' . $pk', $method);

        foreach ($this->sqlInjectionPayloads() as $payload) {
            $safeId = (int) $payload;
            $queryFragment = 'v.id = ' . $safeId;

            self::assertStringNotContainsString($payload, $queryFragment);
            self::assertMatchesRegularExpression('/^v\.id = -?\d+$/', $queryFragment);
        }
    }

    public function testRawRequestValuesAreNotConcatenatedIntoSql(): void
    {
        $findings = array();
        $sources = array(
            '\$_GET',
            '\$_POST',
            '\$_REQUEST',
            '\$_COOKIE',
            'input->get(',
            'input->post->get(',
        );

        foreach ($this->phpFiles() as $path) {
            $relative = $this->relativePath($path);
            $contents = $this->stripPhpComments((string) file_get_contents($path));

            foreach ($sources as $source) {
                $pattern = '/(?:setQuery|where|join|having)\s*\([^;]*\.\s*' . preg_quote($source, '/') . '/i';

                if (preg_match($pattern, $contents) === 1) {
                    $findings[] = $relative . ':' . stripslashes($source);
                }
            }
        }

        sort($findings);

        self::assertSame(
            array(),
            $findings,
            "Raw request values must not be concatenated into SQL builders:\n" . implode("\n", $findings)
        );
    }

    public function testSqlBoundaryIdsAreNormalisedToIntegers(): void
    {
        $cases = array(
            "' OR '1'='1" => 0,
            '1; DROP TABLE users; --' => 1,
            '-42' => -42,
            '0' => 0,
            '' => 0,
            '999999999999' => 999999999999,
        );

        foreach ($cases as $input => $expected) {
            self::assertSame($expected, (int) $input);
        }
    }

    /**
     * @return list<string>
     */
    private function sqlInjectionPayloads(): array
    {
        return array(
            "' OR '1'='1",
            '1; DROP TABLE users; --',
        );
    }

    /**
     * @return iterable<string>
     */
    private function phpFiles(): iterable
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
                if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
                    yield $file->getPathname();
                }
            }
        }
    }

    private function extractMethod(string $contents, string $method): string
    {
        $pattern = '/public\s+function\s+' . preg_quote($method, '/') . '\s*\(.*?\)\s*\{/s';

        if (preg_match($pattern, $contents, $match, PREG_OFFSET_CAPTURE) !== 1) {
            self::fail('Method not found: ' . $method);
        }

        $openBrace = strpos($contents, '{', $match[0][1]);
        self::assertNotFalse($openBrace);

        $depth = 0;
        $length = strlen($contents);

        for ($i = $openBrace; $i < $length; $i++) {
            if ($contents[$i] === '{') {
                $depth++;
            } elseif ($contents[$i] === '}') {
                $depth--;

                if ($depth === 0) {
                    return substr($contents, $openBrace, $i - $openBrace + 1);
                }
            }
        }

        return substr($contents, $openBrace);
    }

    private function stripPhpComments(string $contents): string
    {
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

    private function relativePath(string $path): string
    {
        return str_replace('\\', '/', substr($path, strlen(JEM_TEST_ROOT) + 1));
    }
}
