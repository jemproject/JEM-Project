<?php
declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Language;
use Joomla\Database\DatabaseInterface;

require_once __DIR__ . '/JoomlaTestCase.php';

/** Real Joomla query/template checks; never installs or changes a language package. */
final class LanguageReinstallJoomlaTest extends JoomlaTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::bootJoomlaSite();
        $language = Language::getInstance('en-GB');
        Factory::$language = $language;
        Factory::getApplication()->loadLanguage($language);
        Factory::getApplication()->loadDocument();
        Factory::getApplication()->createExtensionNamespaceMap();
        $language->load('com_jem', JEM_TEST_ROOT . '/admin', 'en-GB', true);
        require_once JEM_TEST_ROOT . '/admin/controllers/languages.php';
    }

    public function testInstalledVersionQueryUsesExactFileExtensionIdentity(): void
    {
        $controller = new class (['base_path' => JPATH_ADMINISTRATOR . '/components/com_jem']) extends JemControllerLanguages {
            public function lookup(string $element): ?string
            {
                return $this->getInstalledPackageVersion($element);
            }
        };
        self::assertNull($controller->lookup('com_jem_nonexistent_test_language'));
        self::assertNull($controller->lookup("com_jem_es-ES' OR 1=1 --"));
        self::assertNull($controller->lookup('com_jem'), 'Do not confuse component and file manifests.');
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName(['element', 'manifest_cache']))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('file'));
        foreach ($db->setQuery($query)->loadObjectList() as $extension) {
            if (!preg_match('/^com_jem_[a-z]{2,3}-[A-Z]{2}$/', $extension->element)) {
                continue;
            }
            $cache = json_decode($extension->manifest_cache, true);
            if (is_array($cache) && !empty($cache['version'])) {
                self::assertSame(trim($cache['version']), $controller->lookup($extension->element));
            }
        }
    }

    public function testRealTemplateRendersOnlyAllowedActions(): void
    {
        $view = new class {
            public string $jemVersion = '5.1.0';
            public string $jemMajor = '5';
            public array $catalogStatus = ['available' => true, 'is_local' => false, 'published' => '2026-10-18', 'source' => 'Test catalog'];
            public array $items = [];
            public function render(): string
            {
                ob_start();
                try {
                    include JEM_TEST_ROOT . '/admin/views/languages/tmpl/default.php';
                    return ob_get_contents();
                } finally {
                    ob_end_clean();
                }
            }
        };
        $package = ['id' => 'es-ES-test', 'version' => '5.0.1', 'jem' => '5.0', 'released' => '2026-10-18'];
        foreach ([
            'available' => 'Install', 'update' => 'Update', 'reinstall' => 'Reinstall',
            'newer_installed' => null, 'installed' => null, 'built_in' => null,
            'joomla_required' => null, 'incompatible' => null, 'not_available' => null,
        ] as $state => $action) {
            $view->items = [[
                'state' => $state, 'tag' => 'es-ES', 'name' => 'Spanish',
                'joomla_installed' => $state !== 'joomla_required',
                'jem_installed' => in_array($state, ['update', 'reinstall', 'newer_installed', 'installed', 'built_in'], true),
                'joomla_site' => true, 'joomla_administrator' => true,
                'installed_version' => '5.0.1',
                'installed_date' => '2026-09-19',
                'package' => in_array($state, ['installed', 'incompatible', 'not_available'], true) ? null : $package,
                'packages' => [$package],
            ]];
            $html = $view->render();
            $document = new DOMDocument();
            @$document->loadHTML('<?xml encoding="UTF-8">' . $html);
            $xpath = new DOMXPath($document);
            self::assertSame(6, $xpath->query('//thead/tr/th')->length);
            self::assertSame('JEM language', trim($xpath->evaluate('string(//thead/tr/th[3])')));
            self::assertSame('Installed package', trim($xpath->evaluate('string(//thead/tr/th[4])')));
            self::assertSame('Catalog package', trim($xpath->evaluate('string(//thead/tr/th[5])')));
            self::assertSame(
                $state === 'built_in' ? 'Built in' : ($view->items[0]['jem_installed'] ? 'Installed' : 'Not installed'),
                trim($xpath->evaluate('string(//tbody/tr/td[2])'))
            );
            self::assertSame('5.0.1 [2026-09-19]', preg_replace('/\s+/', ' ', trim($xpath->evaluate('string(//tbody/tr/td[3])'))));
            self::assertSame('5.0.1 [2026-10-18]', preg_replace('/\s+/', ' ', trim($xpath->evaluate('string(//tbody/tr/td[4])'))));
            self::assertStringNotContainsString('Site / Administrator', $html);
            self::assertStringNotContainsString('5.0.1', $xpath->evaluate('string(//tbody/tr/th)'));
            self::assertStringNotContainsString('COM_JEM_LANGUAGES_', $html);
            self::assertSame($action !== null, str_contains($html, 'task=languages.install'), $state);
            if ($action !== null) {
                self::assertMatchesRegularExpression('/<button[^>]*>\s*' . $action . '\s*<\/button>/', $html);
                self::assertStringContainsString('method="post"', $html);
                self::assertMatchesRegularExpression('/type="hidden" name="[a-f0-9]{32}" value="1"/', $html);
            }
        }
        $view->items[0]['package'] = null;
        $view->items[0]['packages'] = [];
        $view->items[0]['installed_date'] = '';
        $view->items[0]['installed_version'] = '';
        $view->items[0]['jem_installed'] = true;
        $document = new DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">' . $view->render());
        $xpath = new DOMXPath($document);
        self::assertSame('—', trim($xpath->evaluate('string(//tbody/tr/td[3])')));
        self::assertSame('—', trim($xpath->evaluate('string(//tbody/tr/td[4])')));
        self::assertSame('Installed', trim($xpath->evaluate('string(//tbody/tr/td[2])')));
        $view->catalogStatus['available'] = false;
        self::assertStringNotContainsString('<table', $view->render());
    }

    public function testBundledEnglishUsesInstalledComponentMetadata(): void
    {
        require_once JEM_TEST_ROOT . '/admin/models/languages.php';
        $model = new class (['name' => 'Languages']) extends JemModelLanguages {
            public function installedMetadata(): array
            {
                return $this->getInstalledJemLanguages();
            }
        };
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('manifest_cache'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('com_jem'));
        $manifest = json_decode((string) $db->setQuery($query)->loadResult(), true);
        self::assertIsArray($manifest);
        $languages = $model->installedMetadata();
        self::assertArrayHasKey('en-GB', $languages);
        self::assertSame(trim($manifest['version']), $languages['en-GB']['version']);
        self::assertSame(trim($manifest['creationDate'] ?? ''), $languages['en-GB']['date']);
        self::assertSame('built_in', JemLanguageCatalogHelper::getPackageState('en-GB', true, $languages['en-GB']['version'], null));
    }
}
