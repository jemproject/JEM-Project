<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class BuildPackageExcludesTest extends TestCase
{
    public function testComponentBuildExcludesDevelopmentFiles(): void
    {
        $buildFile = JEM_TEST_ROOT . '/build.xml';
        self::assertFileExists($buildFile);

        $xml = new DOMDocument();
        $xml->load($buildFile);
        $xpath = new DOMXPath($xml);

        $excluded = array();
        foreach ($xpath->query('//target[@name="build_component"]//exclude') ?: array() as $exclude) {
            if ($exclude instanceof DOMElement) {
                $excluded[] = $exclude->getAttribute('name');
            }
        }

        foreach (array(
            'tests/**',
            'vendor/**',
            '.phpunit.cache/**',
            '.agents/**',
            '.claude/**',
            '.codex/**',
            '.cursor/**',
            '.github/copilot/**',
            '.env',
            '.env.*',
            '*.pem',
            '*.key',
            '*.crt',
            '*.pfx',
            '*.bak',
            '*.orig',
            '*.log',
            '*.code-workspace',
            'pkg_*.zip',
            '_old*/**',
            'old*/**',
            'composer.json',
            'composer.lock',
            'phpunit.xml',
            'phpunit.xml.dist',
        ) as $pattern) {
            self::assertContains($pattern, $excluded, $pattern . ' must not be packaged in com_jem.zip.');
        }
    }

    public function testBuildConfigIsLocalOnlyAndNotCommitted(): void
    {
        self::assertFileDoesNotExist(JEM_TEST_ROOT . '/build.config', 'build.config should remain local and ignored.');
        self::assertFileExists(JEM_TEST_ROOT . '/build.config.example', 'build.config.example documents local build values.');
    }

    public function testRootWorkspaceFilesAreLocalOnlyAndExportIgnored(): void
    {
        $gitignore = (string) file_get_contents(JEM_TEST_ROOT . '/.gitignore');
        $gitattributes = (string) file_get_contents(JEM_TEST_ROOT . '/.gitattributes');

        self::assertStringContainsString('/*.code-workspace', $gitignore, 'Root VS Code workspace files should remain local and untracked.');
        self::assertStringContainsString('/*.code-workspace export-ignore', $gitattributes, 'Root VS Code workspace files should be excluded from Git archive/GitHub source archives.');
    }

    public function testPackageManifestFilesAreProducedByBuild(): void
    {
        $manifest = simplexml_load_file(JEM_TEST_ROOT . '/package/pkg_jem.xml');
        self::assertNotFalse($manifest);

        $required = array();
        foreach ($manifest->files->file as $file) {
            $required[] = (string) $file;
        }

        $buildFile = JEM_TEST_ROOT . '/build.xml';
        self::assertFileExists($buildFile);

        $xml = new DOMDocument();
        $xml->load($buildFile);
        $xpath = new DOMXPath($xml);

        $produced = array();
        foreach ($xpath->query('//target[@name="build_plugins" or @name="build_modules" or @name="build_component"]//zip') ?: array() as $zip) {
            if ($zip instanceof DOMElement) {
                $produced[] = basename($this->resolveBuildName($zip->getAttribute('destfile')));
            }
        }

        foreach ($required as $zipName) {
            self::assertContains($zipName, $produced, $zipName . ' is required by package/pkg_jem.xml and must be produced by build.xml.');
        }

        $excluded = array();
        foreach ($xpath->query('//target[@name="build_package"]//exclude') ?: array() as $exclude) {
            if ($exclude instanceof DOMElement) {
                $excluded[] = $exclude->getAttribute('name');
            }
        }

        foreach ($required as $zipName) {
            self::assertNotContains($zipName, $excluded, $zipName . ' is required by package/pkg_jem.xml and must not be excluded from the package ZIP.');
        }
    }

    public function testPackagedModulesDeclareExistingInstallScripts(): void
    {
        $manifest = simplexml_load_file(JEM_TEST_ROOT . '/package/pkg_jem.xml');
        self::assertNotFalse($manifest);

        $missing = array();

        foreach ($manifest->files->file as $file) {
            if ((string) $file['type'] !== 'module') {
                continue;
            }

            $module = (string) $file['id'];
            $moduleManifestPath = JEM_TEST_ROOT . '/modules/' . $module . '/' . $module . '.xml';
            self::assertFileExists($moduleManifestPath);

            $moduleManifest = simplexml_load_file($moduleManifestPath);
            self::assertNotFalse($moduleManifest);

            if ((string) $moduleManifest->scriptfile !== 'script.php') {
                $missing[] = $module . ': missing <scriptfile>script.php</scriptfile>';
                continue;
            }

            if (!is_file(JEM_TEST_ROOT . '/modules/' . $module . '/script.php')) {
                $missing[] = $module . ': script.php file is missing';
            }

            $listedFiles = array();

            foreach ($moduleManifest->files->filename as $filename) {
                $listedFiles[] = (string) $filename;
            }

            if (!in_array('script.php', $listedFiles, true)) {
                $missing[] = $module . ': script.php is not listed in <files>';
            }
        }

        self::assertSame(array(), $missing, implode("\n", $missing));
    }

    public function testAllJemModulesDeclareValidInstallScripts(): void
    {
        $moduleDirs = glob(JEM_TEST_ROOT . '/modules/mod_jem*', GLOB_ONLYDIR) ?: array();
        self::assertNotSame(array(), $moduleDirs, 'At least one JEM module directory should exist.');

        $missing = array();

        foreach ($moduleDirs as $moduleDir) {
            $module = basename($moduleDir);
            $moduleManifestPath = $moduleDir . '/' . $module . '.xml';
            $scriptPath = $moduleDir . '/script.php';

            if (!is_file($moduleManifestPath)) {
                $missing[] = $module . ': module manifest is missing';
                continue;
            }

            $moduleManifest = simplexml_load_file($moduleManifestPath);
            self::assertNotFalse($moduleManifest);

            if ((string) $moduleManifest->scriptfile !== 'script.php') {
                $missing[] = $module . ': missing <scriptfile>script.php</scriptfile>';
            }

            if (!is_file($scriptPath)) {
                $missing[] = $module . ': script.php file is missing';
                continue;
            }

            $listedFiles = array();

            foreach ($moduleManifest->files->filename as $filename) {
                $listedFiles[] = (string) $filename;
            }

            if (!in_array('script.php', $listedFiles, true)) {
                $missing[] = $module . ': script.php is not listed in <files>';
            }

            $script = (string) file_get_contents($scriptPath);
            $expectedClass = $module . 'InstallerScript';

            if (preg_match('/\bclass\s+' . preg_quote($expectedClass, '/') . '\b/', $script) !== 1) {
                $missing[] = $module . ': installer class ' . $expectedClass . ' is missing';
            }

            if (preg_match('/Version::MAJOR_VERSION\s*(?:===|!==)\s*6/', $script) !== 1) {
                $missing[] = $module . ': installer script must guard Joomla 6 runtime';
            }
        }

        self::assertSame(array(), $missing, implode("\n", $missing));
    }

    public function testTypesModuleIsPackagedAndHasInstallerScript(): void
    {
        $packageManifest = simplexml_load_file(JEM_TEST_ROOT . '/package/pkg_jem.xml');
        self::assertNotFalse($packageManifest);

        $packageEntry = null;

        foreach ($packageManifest->files->file as $file) {
            if ((string) $file['type'] === 'module' && (string) $file['id'] === 'mod_jem_types') {
                $packageEntry = $file;
                break;
            }
        }

        self::assertNotNull($packageEntry, 'The JEM Types module must be installed by the package manifest.');
        self::assertSame('site', (string) $packageEntry['client']);
        self::assertSame('mod_jem_types.zip', (string) $packageEntry);

        $buildFile = JEM_TEST_ROOT . '/build.xml';
        self::assertFileExists($buildFile);

        $buildXml = (string) file_get_contents($buildFile);
        self::assertStringContainsString('mod_${cfg.name}_types.zip', $buildXml);
        self::assertStringContainsString('modules/mod_${cfg.name}_types', $buildXml);

        $moduleManifest = simplexml_load_file(JEM_TEST_ROOT . '/modules/mod_jem_types/mod_jem_types.xml');
        self::assertNotFalse($moduleManifest);
        self::assertSame('script.php', (string) $moduleManifest->scriptfile);
        self::assertFileExists(JEM_TEST_ROOT . '/modules/mod_jem_types/script.php');
    }

    public function testPackageInstallerNormalisesJemModuleInstanceParams(): void
    {
        $script = (string) file_get_contents(JEM_TEST_ROOT . '/package/pkg_install.php');

        self::assertStringContainsString('$this->normaliseJemModuleParams();', $script);
        self::assertStringContainsString('function normaliseJemModuleParams()', $script);
        self::assertStringContainsString("->where(\$db->quoteName('module') . ' LIKE ' . \$db->quote('mod_jem%'))", $script);
        self::assertStringContainsString("->set(\$db->quoteName('params') . ' = ' . \$db->quote('{}'))", $script);
    }

    private function resolveBuildName(string $value): string
    {
        return strtr($value, array(
            '${cfg.name}' => 'jem',
            '${cfg.comName}' => 'com_jem',
        ));
    }
}
