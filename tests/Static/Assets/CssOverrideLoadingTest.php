<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CssOverrideLoadingTest extends TestCase
{
    private string $helper;

    protected function setUp(): void
    {
        $this->helper = $this->read('site/helpers/helper.php');
    }

    public function testComponentCssUsesUnifiedTemplateAwareWebAssetLoading(): void
    {
        $method = $this->method('loadCss', 'loadFrontendUserCss');

        self::assertStringContainsString("css_' . \$configKey . '_usecustom", $method);
        self::assertStringContainsString("'/css/com_jem/'", $method);
        self::assertStringContainsString("'administrator/templates/'", $method);
        self::assertStringContainsString("'templates/'", $method);
        self::assertStringContainsString("'media/com_jem/css/'", $method);
        self::assertStringContainsString('registerAndUseStyle($asset, $styleUri)', $method);
        self::assertStringNotContainsString('addStyleSheet(', $method);

        $custom = strpos($method, "media/com_jem/css/custom/' . \$file");
        $template = strpos($method, "'/css/com_jem/'");
        $media = strpos($method, "media/com_jem/css/' . \$variant");

        self::assertIsInt($custom);
        self::assertIsInt($template);
        self::assertIsInt($media);
        self::assertLessThan($template, $custom, 'CSS Manager replacements must be checked before template overrides.');
        self::assertLessThan($media, $template, 'Template overrides must be checked before the media fallback.');
    }

    public function testFrontendUserCssDependsOnAllLoadedComponentStyles(): void
    {
        self::assertStringContainsString('protected static $frontendCssAssets = array();', $this->helper);
        self::assertStringContainsString('self::$frontendCssAssets[$asset] = $asset;', $this->helper);

        $method = $this->method('loadFrontendUserCss', 'loadModuleUserCss');
        self::assertStringContainsString('array_values(self::$frontendCssAssets)', $method);

        $loader = $this->method('loadUserCssFile', 'loadModuleStyleSheet');
        self::assertStringContainsString('$dependencies = array()', $loader);
        self::assertStringContainsString('$wa->disableStyle($asset);', $loader);
        self::assertStringContainsString('$dependencies', $loader);
    }

    public function testAdministratorUsesOneCanonicalBackendStylesheet(): void
    {
        $method = $this->method('loadCss', 'loadFrontendUserCss');

        self::assertStringContainsString("\$layoutSuffix = \$isAdmin ? '' : self::getLayoutStyleSuffix();", $method);
        self::assertFileExists(JEM_TEST_ROOT . '/media/css/backend.css');
        self::assertFileDoesNotExist(JEM_TEST_ROOT . '/media/css/backend-responsive.css');

        foreach (array(
            'admin/models/forms/settings.xml',
            'admin/models/cssmanager.php',
            'admin/models/source.php',
        ) as $relativePath) {
            self::assertStringNotContainsString(
                'backend-responsive',
                (string) file_get_contents(JEM_TEST_ROOT . '/' . $relativePath),
                $relativePath
            );
        }
    }

    public function testModuleCssKeepsOneConsistentOverridePriority(): void
    {
        $method = $this->method('loadModuleStyleSheet', 'loadIconFont');
        $paths = array(
            "'/css/' . \$module . '/'" => 'template css override',
            "'/html/' . \$module . '/'" => 'legacy template html override',
            "'/media/' . \$module . '/css/'" => 'module media stylesheet',
            "'/modules/' . \$module . '/tmpl/'" => 'module tmpl fallback',
        );
        $lastPosition = -1;

        foreach ($paths as $needle => $label) {
            $position = strpos($method, $needle);
            self::assertIsInt($position, $label);
            self::assertGreaterThan($lastPosition, $position, $label . ' is out of order.');
            $lastPosition = $position;
        }

        self::assertStringContainsString("JPATH_SITE . '/templates/'", $method);
        self::assertStringContainsString("JPATH_SITE . '/media/'", $method);
        self::assertStringContainsString("JPATH_SITE . '/modules/'", $method);
        self::assertStringContainsString("assetExists('style', \$asset)", $method);
    }

    public function testViewsAndModulesDoNotBypassTheComponentCssResolver(): void
    {
        $roots = array('admin', 'site', 'modules');

        foreach ($roots as $root) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(JEM_TEST_ROOT . '/' . $root, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
                    continue;
                }

                $relativePath = str_replace('\\', '/', substr($file->getPathname(), strlen(JEM_TEST_ROOT) + 1));

                if ($relativePath === 'site/helpers/helper.php') {
                    continue;
                }

                $contents = (string) file_get_contents($file->getPathname());
                self::assertDoesNotMatchRegularExpression(
                    '/(?:addStyleSheet|register(?:AndUse)?Style)[^\r\n]*media\/com_jem\/css\//i',
                    $contents,
                    $relativePath . ' bypasses JemHelper::loadCss().'
                );
                self::assertStringNotContainsString('com_jem/backend.css', $contents, $relativePath);
            }
        }

        self::assertStringContainsString("JemHelper::loadCss('backend');", $this->read('admin/jem.php'));
        self::assertStringContainsString("JemHelper::loadCss('jem');", $this->read('site/views/venuesmap/view.html.php'));
        self::assertSame(
            1,
            substr_count($this->read('site/views/day/tmpl/timetable.php'), "JemHelper::loadCss('timetable');")
        );
    }

    public function testCssManagerActionsCaptureThePermissionTheyUse(): void
    {
        $template = $this->read('admin/views/cssmanager/tmpl/default.php');

        self::assertSame(2, substr_count($template, 'use ($canManageTools, $renderDownloadButton)'));
        self::assertStringNotContainsString('use ($canDo, $renderDownloadButton)', $template);
    }

    public function testContentPluginSupportsCanonicalAndLegacyTemplateOverrides(): void
    {
        $plugin = $this->read('plugins/plg_content_jemlistevents/jemlistevents.php');
        $canonical = strpos($plugin, '/css/plg_content_jemlistevents/jemlistevents.css');
        $legacy = strpos($plugin, '/css/jemlistevents.css');
        $fallback = strpos($plugin, 'media/plg_content_jemlistevents/css/jemlistevents.css');

        self::assertIsInt($canonical);
        self::assertIsInt($legacy);
        self::assertIsInt($fallback);
        self::assertLessThan($legacy, $canonical);
        self::assertLessThan($fallback, $legacy);
        self::assertStringContainsString("assetExists('style', 'plg_content_jemlistevents')", $plugin);
    }

    public function testCalendarGridDoesNotRequestTheRemovedLegacyCssName(): void
    {
        $module = $this->read('modules/mod_jem_cal/mod_jem_cal.php');
        $layout = $this->read('modules/mod_jem_cal/tmpl/grid.php');

        self::assertStringContainsString('JemHelper::loadModuleStyleSheet($mod_name, $layout);', $module);
        self::assertStringNotContainsString('mod_jem_cal_grid', $layout);
        self::assertFileExists(JEM_TEST_ROOT . '/modules/mod_jem_cal/tmpl/grid.css');
    }

    public function testModulesNormaliseLegacyAndEmptyLayoutValues(): void
    {
        $method = $this->method('getModuleLayoutName', 'getModuleLayoutPath');

        self::assertStringContainsString("strpos(\$layout, ':')", $method);
        self::assertStringContainsString("return \$layout !== '' ? \$layout : 'default';", $method);

        foreach (array('mod_jem', 'mod_jem_banner', 'mod_jem_cal', 'mod_jem_jubilee', 'mod_jem_map', 'mod_jem_teaser', 'mod_jem_wide') as $module) {
            $entry = $this->read('modules/' . $module . '/' . $module . '.php');
            self::assertStringContainsString('JemHelper::getModuleLayoutName(', $entry, $module);
            self::assertStringNotContainsString('substr(strstr(', $entry, $module);
        }

        $loader = $this->method('loadModuleStyleSheet', 'loadIconFont');
        self::assertStringContainsString('$css = self::getModuleLayoutName($css);', $loader);
    }

    private function method(string $name, string $nextName): string
    {
        $start = strpos($this->helper, 'function ' . $name . '(');
        $end = strpos($this->helper, 'function ' . $nextName . '(', $start === false ? 0 : $start + 1);

        self::assertIsInt($start, $name . '() was not found.');
        self::assertIsInt($end, $nextName . '() was not found.');

        return substr($this->helper, $start, $end - $start);
    }

    private function read(string $relativePath): string
    {
        $path = JEM_TEST_ROOT . '/' . $relativePath;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
