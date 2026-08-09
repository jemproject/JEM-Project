<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Joomla6CompatibilityTest extends TestCase
{
    public function testPackageManifestTargetsJoomla5InstallerSchema(): void
    {
        $manifest = simplexml_load_file(JEM_TEST_ROOT . '/package/pkg_jem.xml');

        self::assertNotFalse($manifest);
        self::assertSame('5.0', (string) $manifest['version']);
    }

    public function testUpdateFeedTargetsJoomla54AndJoomla6WithPhp83(): void
    {
        $updates = simplexml_load_file(JEM_TEST_ROOT . '/update_pkg_jem.xml');

        self::assertNotFalse($updates);

        // The update feed describes the latest published package and may
        // intentionally lag behind a development branch manifest.
        $current = $updates->update[0] ?? null;
        self::assertNotNull($current, 'The JEM update feed must contain a published package.');
        self::assertSame('^(5\.[4-9].*|6\..*)$', (string) $current->targetplatform['version']);
        self::assertSame('8.3', (string) $current->php_minimum);
    }

    public function testBetaReleaseNotesAreStoredInTheComponentAndPackageManifests(): void
    {
        foreach (array('/jem.xml', '/package/pkg_jem.xml') as $relativePath) {
            $manifest = simplexml_load_file(JEM_TEST_ROOT . $relativePath);

            self::assertNotFalse($manifest);
            self::assertSame('5.1.0beta1', (string) $manifest->version);
            self::assertCount(34, explode(';', (string) $manifest->notes));
            self::assertStringContainsString('parent event programmes and nested venue hierarchies', (string) $manifest->notes);
            self::assertStringContainsString('Issue #2242', (string) $manifest->notes);
            self::assertStringContainsString('Issue #2257', (string) $manifest->notes);
            self::assertStringContainsString('Issue #2263', (string) $manifest->notes);
            self::assertStringContainsString('Issue #2288', (string) $manifest->notes);
            self::assertStringContainsString('Issue #2280', (string) $manifest->notes);
            self::assertStringContainsString('Issue #2287', (string) $manifest->notes);
            self::assertStringContainsString('Commit 1e82bf8', (string) $manifest->notes);
            self::assertStringContainsString('Commit f199043', (string) $manifest->notes);
        }
    }

    public function testExtensionManifestsUseJem510Beta1Version(): void
    {
        $manifestPaths = array_merge(
            array(JEM_TEST_ROOT . '/jem.xml', JEM_TEST_ROOT . '/package/pkg_jem.xml'),
            glob(JEM_TEST_ROOT . '/modules/*/*.xml') ?: array(),
            glob(JEM_TEST_ROOT . '/plugins/*/*.xml') ?: array()
        );

        $wrong = array();

        foreach ($manifestPaths as $path) {
            $manifest = simplexml_load_file($path);

            if ($manifest === false || (string) $manifest->version === '') {
                continue;
            }

            if ((string) $manifest->version !== '5.1.0beta1') {
                $wrong[] = $this->relativePath($path) . ':' . (string) $manifest->version;
            }
        }

        self::assertSame(array(), $wrong, "Joomla 5.4/6 package manifests should use JEM 5.x versions:\n" . implode("\n", $wrong));
    }

    public function testReleaseManifestsUseCurrentJemVersion(): void
    {
        $rootManifest = simplexml_load_file(JEM_TEST_ROOT . '/jem.xml');
        self::assertNotFalse($rootManifest);

        $expectedVersion = (string) $rootManifest->version;
        $manifestPaths = array_merge(
            array(JEM_TEST_ROOT . '/jem.xml', JEM_TEST_ROOT . '/package/pkg_jem.xml'),
            glob(JEM_TEST_ROOT . '/modules/*/*.xml') ?: array(),
            glob(JEM_TEST_ROOT . '/plugins/*/*.xml') ?: array()
        );

        $wrong = array();

        foreach ($manifestPaths as $path) {
            $manifest = simplexml_load_file($path);

            if ($manifest === false || (string) $manifest->version === '') {
                continue;
            }

            if ((string) $manifest->version !== $expectedVersion) {
                $wrong[] = $this->relativePath($path) . ':' . (string) $manifest->version;
            }
        }

        self::assertSame(array(), $wrong, "JEM release manifests should use $expectedVersion:\n" . implode("\n", $wrong));
    }

    public function testInstallerScriptsGuardJoomla54AndJoomla6Runtime(): void
    {
        $scriptPaths = array_merge(
            array(
                JEM_TEST_ROOT . '/script.php',
                JEM_TEST_ROOT . '/package/pkg_install.php',
            ),
            glob(JEM_TEST_ROOT . '/modules/*/script.php') ?: array(),
            glob(JEM_TEST_ROOT . '/plugins/*/script.php') ?: array()
        );

        $missingMinimum = array();
        $missingMaximum = array();

        foreach ($scriptPaths as $path) {
            $code = file_get_contents($path);
            self::assertIsString($code);

            if (strpos($code, "version_compare(JVERSION, '5.4.0'") === false) {
                $missingMinimum[] = $this->relativePath($path);
            }

            if (strpos($code, "version_compare(JVERSION, '7.0.0'") === false && strpos($code, 'Version::MAJOR_VERSION > 6') === false) {
                $missingMaximum[] = $this->relativePath($path);
            }
        }

        self::assertSame(array(), $missingMinimum, "Installer scripts must require Joomla 5.4.0 or newer:\n" . implode("\n", $missingMinimum));
        self::assertSame(array(), $missingMaximum, "Installer scripts must reject Joomla 7 or newer until tested:\n" . implode("\n", $missingMaximum));
    }

    public function testComponentInstallerAllowsFreshInstallWhenUpdatePreflightHasNoInstalledVersion(): void
    {
        $code = (string) file_get_contents(JEM_TEST_ROOT . '/script.php');

        self::assertStringContainsString('$this->oldRelease = $this->getParam(\'version\');', $code);
        self::assertStringContainsString('$this->oldRelease !== \'\' && version_compare($this->oldRelease, $minUpgradeVersion, \'lt\')', $code);
        self::assertStringContainsString('$this->oldRelease !== \'\' && version_compare($this->newRelease, $this->oldRelease, \'lt\')', $code);
        self::assertMatchesRegularExpression(
            '/if\s*\(\$this->oldRelease\s*!==\s*\'\'\)\s*\{[^}]*\$this->deleteObsoleteFiles\(\);[^}]*\$this->checkColumnsIntoDatabase\(\);[^}]*\$this->makeFilesWritable\(\);[^}]*\$this->initializeSchema\(\$this->oldRelease\);/s',
            $code,
            'Update-only cleanup and schema checks must not run when Joomla calls update preflight for a fresh package install with no installed JEM version.'
        );
    }

    public function testJoomlaIntegrationTestsExpectJoomla5Or6(): void
    {
        $testPaths = array();
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(JEM_TEST_ROOT . '/tests/Joomla'));

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $testPaths[] = $file->getPathname();
            }
        }

        $offenders = array();

        foreach ($testPaths as $path) {
            $code = file_get_contents($path);
            self::assertIsString($code);

            if (preg_match('/assertSame\(\s*4\s*,\s*Version::MAJOR_VERSION|\/\^4\\\\\.\//', $code) === 1) {
                $offenders[] = $this->relativePath($path);
            }
        }

        self::assertSame(array(), $offenders, "Joomla integration tests must target Joomla 5.4 or Joomla 6:\n" . implode("\n", $offenders));
    }

    private function relativePath(string $path): string
    {
        return str_replace('\\', '/', substr($path, strlen(JEM_TEST_ROOT) + 1));
    }
}


