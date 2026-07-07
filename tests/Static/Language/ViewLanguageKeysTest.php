<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ViewLanguageKeysTest extends TestCase
{
    /**
     * These keys were already missing when view language coverage was introduced.
     * Keep the list explicit so new missing keys still fail the build.
     */
    private const KNOWN_MISSING = array(
        'admin' => array(
            'COM_JEM_HOUSEKEEPING_CATSEVENT_RELS',
            'COM_JEM_REMOVE',
            'COM_JEM_SELECTATTENDEE',
            'COM_JEM_SELECTCONTACT',
        ),
        'site' => array(
            'COM_JEM_MY_ATTENDANCES',
            'COM_JEM_NO_CATEGORY',
            'COM_JEM_SETTINGS_TITLE',
            'COM_JEM_VENUESLIST_PAGETITLE',
        ),
    );

    public function testAdminViewLanguageKeysExistOrAreInBaseline(): void
    {
        $this->assertViewLanguageKeysAreCovered(
            'admin',
            array(JEM_TEST_ROOT . '/admin/views'),
            JEM_TEST_ROOT . '/admin/language/en-GB/com_jem.ini'
        );
    }

    public function testSiteViewLanguageKeysExistOrAreInBaseline(): void
    {
        $this->assertViewLanguageKeysAreCovered(
            'site',
            array(
                JEM_TEST_ROOT . '/site/views',
                JEM_TEST_ROOT . '/site/common/views',
            ),
            JEM_TEST_ROOT . '/site/language/en-GB/com_jem.ini'
        );
    }

    /**
     * @param list<string> $viewPaths
     */
    private function assertViewLanguageKeysAreCovered(string $scope, array $viewPaths, string $languageFile): void
    {
        $defined = $this->readLanguageKeys($languageFile);
        $used = $this->readViewLanguageKeys($viewPaths);
        $knownMissing = array_flip(self::KNOWN_MISSING[$scope]);

        $missing = array_values(array_filter(
            $used,
            static fn (string $key): bool => !isset($defined[$key]) && !isset($knownMissing[$key])
        ));

        sort($missing);

        self::assertSame(
            array(),
            $missing,
            'New missing ' . $scope . " view language keys:\n" . implode("\n", $missing)
        );
    }

    /**
     * @return array<string, true>
     */
    private function readLanguageKeys(string $path): array
    {
        self::assertFileExists($path);

        preg_match_all('/^([A-Z0-9_]+)=/m', (string) file_get_contents($path), $matches);

        return array_fill_keys($matches[1], true);
    }

    /**
     * @param list<string> $viewPaths
     *
     * @return list<string>
     */
    private function readViewLanguageKeys(array $viewPaths): array
    {
        $keys = array();

        foreach ($viewPaths as $viewPath) {
            if (!is_dir($viewPath)) {
                continue;
            }

            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($viewPath, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($files as $file) {
                if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
                    continue;
                }

                $contents = $this->stripComments((string) file_get_contents($file->getPathname()));

                preg_match_all(
                    '/Text::(?:_|sprintf|plural)\(\s*[\'"](COM_JEM_[A-Z0-9_]+)[\'"]\s*[,)]/',
                    $contents,
                    $matches
                );

                foreach ($matches[1] as $key) {
                    $keys[$key] = true;
                }
            }
        }

        $keys = array_keys($keys);
        sort($keys);

        return $keys;
    }

    private function stripComments(string $contents): string
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
}
