<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ModuleCssOverrideMigrationTest extends TestCase
{
    public function testCssManagerExposesAnExplicitProtectedMigrationAction(): void
    {
        $controller = $this->read('admin/controllers/cssmanager.php');
        $method = $this->method($controller, 'migratelegacyoverrides');

        self::assertStringContainsString('JemHelper::requirePostToken();', $method);
        self::assertStringContainsString("JemHelperBackend::canManage('jem.tools.manage')", $method);
        self::assertStringContainsString('migrateLegacyModuleCssOverrides()', $method);
        self::assertStringContainsString("view=cssmanager", $method);
    }

    public function testMigrationModelUsesFixedMappingsAndInstalledSiteTemplates(): void
    {
        $model = $this->read('admin/models/cssmanager.php');

        self::assertStringContainsString('/classes/modulecssoverride.class.php', $model);
        self::assertStringContainsString('JemModuleCssOverride::discover(JPATH_SITE', $model);
        self::assertStringContainsString('JemModuleCssOverride::migrate(JPATH_SITE', $model);
        self::assertStringContainsString("#__template_styles", $model);
        self::assertStringContainsString("client_id') . ' = 0", $model);
    }

    public function testMigrationPolicyNeverOverwritesAndRejectsUnsafePaths(): void
    {
        $policy = $this->read('site/classes/modulecssoverride.class.php');

        self::assertStringContainsString("preg_match('/^[A-Za-z0-9_-]+$/', \$template)", $policy);
        self::assertStringContainsString('self::isWithinRoot($directoryReal, $root)', $policy);
        self::assertStringContainsString('is_link($source)', $policy);
        self::assertStringContainsString('file_exists($target) || is_link($target)', $policy);
        self::assertStringContainsString("\$item['status'] !== 'pending'", $policy);
        self::assertStringContainsString("fopen(\$target, 'x+b')", $policy);
        self::assertStringContainsString("hash_equals(\$sourceHash, \$targetHash)", $policy);
        self::assertStringNotContainsString('rename($item', $policy);
    }

    public function testViewShowsEveryPathAndRequiresConfirmation(): void
    {
        $view = $this->read('admin/views/cssmanager/view.html.php');
        $template = $this->read('admin/views/cssmanager/tmpl/default.php');

        self::assertStringContainsString("\$this->get('LegacyModuleCssOverrides')", $view);
        self::assertStringContainsString("\$legacyOverride['sourceRelative']", $template);
        self::assertStringContainsString("\$legacyOverride['targetRelative']", $template);
        self::assertStringContainsString('cssmanager.migratelegacyoverrides', $template);
        self::assertStringContainsString('COM_JEM_CSSMANAGER_LEGACY_CONFIRM', $template);
        self::assertStringContainsString("HTMLHelper::_('form.token')", $template);
    }

    public function testControlPanelWarnsAdministratorsBeforeTheyOpenCssManager(): void
    {
        $view = $this->read('admin/views/main/view.html.php');
        $template = $this->read('admin/views/main/tmpl/default.php');

        self::assertStringContainsString("JemHelperBackend::canManage('jem.tools.manage')", $view);
        self::assertStringContainsString('getLegacyModuleCssOverrides()', $view);
        self::assertStringContainsString('COM_JEM_MAIN_LEGACY_MODULE_CSS_TITLE', $template);
        self::assertStringContainsString('view=cssmanager', $template);
    }

    private function method(string $contents, string $name): string
    {
        $start = strpos($contents, 'function ' . $name . '(');

        self::assertIsInt($start, $name . '() was not found.');

        $next = strpos($contents, "\n    public function ", $start + 1);

        return substr($contents, $start, $next === false ? null : $next - $start);
    }

    private function read(string $relativePath): string
    {
        $path = JEM_TEST_ROOT . '/' . $relativePath;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
