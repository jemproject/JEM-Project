<?php
declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\Registry\Registry;
use JemProject\Tests\TypeLanguage as Policy;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Production methods and Joomla Table over disposable SQLite; no installed database is used. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class TypeLanguagePolicyTest extends TestCase
{
    private Policy\Database $db;

    /** @return void Load native Joomla services without booting an installed site. */
    protected function setUp(): void
    {
        $root = rtrim((string) getenv('JEM_TEST_JOOMLA_ROOT'), '/\\');
        if (!is_file($root . '/libraries/vendor/autoload.php') || !extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('Requires Joomla libraries and pdo_sqlite.');
        }
        require_once $root . '/libraries/vendor/autoload.php';
        spl_autoload_register(static function (string $class) use ($root): void {
            if (str_starts_with($class, 'Joomla\\CMS\\')) {
                $path = $root . '/libraries/src/' . str_replace('\\', '/', substr($class, 11)) . '.php';
                if (is_file($path)) {
                    require_once $path;
                }
            }
        });
        require_once JEM_TEST_ROOT . '/tests/Support/type_language_environment.php';
        $this->db = new Policy\Database(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => 't_']);
        $this->db->connect();
        $container = new \Joomla\DI\Container();
        $container->set('DatabaseDriver', $this->db, true);
        (new ReflectionProperty(Factory::class, 'container'))->setValue(null, $container);
        Factory::$config = new Registry(['unicodeslugs' => 1, 'access' => 1]);
        Factory::$application = new Policy\Application();
        Factory::$language = new class {
            /** @param string $text ASCII fixture. @return string Transliteration input. */
            public function transliterate(string $text): string
            {
                return $text;
            }
        };
        $this->db->setQuery("CREATE TABLE t_jem_types (
            id INTEGER PRIMARY KEY, name TEXT DEFAULT 'Original', alias TEXT DEFAULT 'original',
            description TEXT DEFAULT 'Original description', base_language TEXT DEFAULT 'en-GB',
            translation_languages TEXT, translations TEXT, entity INTEGER DEFAULT 1,
            icon TEXT DEFAULT 'icon-star', color TEXT DEFAULT '#123456', published INTEGER DEFAULT 1,
            ordering INTEGER DEFAULT 0, access INTEGER DEFAULT 1, language TEXT DEFAULT '*',
            checked_out INTEGER, checked_out_time TEXT, created TEXT, created_by INTEGER DEFAULT 1,
            modified TEXT, modified_by INTEGER DEFAULT 0, attribs TEXT DEFAULT '{}'
        )")->execute();
        Policy\loadMethods();
    }

    /** @param int $id Identifier. @param array $changes Overrides. @return void Insert a fixture. */
    private function insert(int $id, array $changes = []): void
    {
        $row = (object) array_merge(['id' => $id], $changes);
        $this->db->insertObject('#__jem_types', $row);
    }

    /** @return void Exact language or original text, never an unrelated translation. */
    public function testTranslationPolicyForEveryEntityAndAliasedProjection(): void
    {
        foreach ([1, 2, 3, 4] as $entity) {
            foreach (['', 'type_', 'venue_type_', 'category_type_'] as $prefix) {
                $row = (object) [
                    'id' => 7, 'entity' => $entity, $prefix . 'name' => 'Original',
                    $prefix . 'description' => 'Original description', $prefix . 'base_language' => 'en-GB',
                    $prefix . 'translations' => json_encode([
                        'en-GB' => ['name' => 'Must not replace original'],
                        'es-ES' => ['name' => 'Traducido', 'description' => ''],
                        'de-DE' => ['name' => 'Deutsch'],
                    ]),
                ];
                foreach (['es-ES' => 'Traducido', 'fr-FR' => 'Original', 'en-GB' => 'Original'] as $tag => $expected) {
                    Factory::$application->tag = $tag;
                    self::assertSame($expected, Policy\JemOutput::getTypeTranslationValue($row, $prefix, 'name'));
                    self::assertSame('Original description', Policy\JemOutput::getTypeTranslationValue($row, $prefix, 'description'));
                }
                Factory::$application->tag = 'es-ES';
                foreach (['{broken', 'null', '{"es-ES":{"name":[]}}', '{"es-ES":{"name":" "}}'] as $invalid) {
                    $row->{$prefix . 'translations'} = $invalid;
                    self::assertSame('Original', Policy\JemOutput::getTypeTranslationValue($row, $prefix, 'name'));
                }
                self::assertSame(7, $row->id);
            }
        }
    }

    /** @return void Saves and import-style binds normalize without replacing the base language. */
    public function testTableSaveAndImportKeepOriginalIdentityAndBaseLanguage(): void
    {
        require_once JEM_TEST_ROOT . '/admin/tables/jem_types.php';
        foreach ([1, 2, 3, 4] as $entity) {
            $table = new \jem_types($this->db);
            self::assertTrue($table->bind([
                'name' => 'Original ' . $entity, 'alias' => 'original-' . $entity,
                'entity' => $entity, 'language' => 'es-ES', 'base_language' => 'de-DE',
                'created' => '2026-11-01 09:00:00', 'created_by' => 1,
                'translations' => ['es-ES' => ['name' => 'Traducido']],
            ]));
            self::assertSame('*', $table->language);
            $table->language = 'fr-FR';
            self::assertTrue($table->check());
            self::assertTrue($table->store());
            $id = (int) $table->id;
            $import = new \jem_types($this->db);
            self::assertTrue($import->load($id));
            self::assertTrue($import->bind(['language' => 'en-GB', 'base_language' => 'en-GB']));
            self::assertTrue($import->check());
            self::assertTrue($import->store());
            $stored = $this->db->setQuery('SELECT * FROM t_jem_types WHERE id = ' . $id)->loadObject();
            self::assertSame('*', $stored->language);
            self::assertSame('de-DE', $stored->base_language);
            self::assertSame($entity, (int) $stored->entity);
            self::assertSame('Traducido', json_decode($stored->translations, true)['es-ES']['name']);
        }
    }

    /** @return void Language does not hide Types; entity, publication and ACL still apply. */
    public function testFrontendTypeQueriesKeepTheirOtherRestrictions(): void
    {
        foreach ([1 => Policy\EventModel::class, 2 => Policy\CategoryModel::class, 3 => Policy\VenueModel::class] as $entity => $class) {
            $id = $entity * 10;
            $this->insert($id, ['entity' => $entity, 'language' => 'de-DE', 'base_language' => '', 'translations' => null]);
            $model = new $class();
            $model->id = $id;
            $model->selectType($id);
            self::assertSame($id, (int) $model->getType()->id);
            self::assertSame(1, (int) $model->getType()->user_has_access_type);
            $this->db->setQuery('UPDATE t_jem_types SET access = 2 WHERE id = ' . $id)->execute();
            $model = new $class();
            $model->id = $id;
            $model->selectType($id);
            self::assertSame(0, (int) $model->getType()->user_has_access_type);
            $this->db->setQuery('UPDATE t_jem_types SET published = 0 WHERE id = ' . $id)->execute();
            $model = new $class();
            $model->id = $id;
            $model->selectType($id);
            self::assertNull($model->getType());
        }
        $this->insert(50, ['entity' => 2, 'language' => 'fr-FR', 'translations' => '{"es-ES":{"name":"Categoría"}}']);
        $categories = (new Policy\CategoryModel())->getTypes();
        self::assertCount(1, $categories);
        self::assertSame('Categoría', $categories[0]->name);
    }

    /** @return void Reinstalls and SQL upgrades are repeatable and alter only compatibility language. */
    public function testUpgradeNormalizationIsIdempotentAndPreservesAllOtherColumns(): void
    {
        foreach (['de-DE', '*', '', null] as $index => $language) {
            $this->insert($index + 1, ['entity' => $index + 1, 'language' => $language,
                'base_language' => 'de-DE', 'translations' => '{"es-ES":{"name":"Texto"}}']);
        }
        // insertObject omits null values: explicitly model a legacy nullable language column.
        $this->db->setQuery('UPDATE t_jem_types SET language = NULL WHERE id = 4')->execute();
        $before = $this->db->setQuery('SELECT * FROM t_jem_types ORDER BY id')->loadAssocList();
        $method = new ReflectionMethod(Policy\Installer::class, 'normaliseTypeLanguages');
        $installer = new Policy\Installer();
        for ($pass = 0; $pass < 2; $pass++) {
            $method->invoke($installer);
            $after = $this->db->setQuery('SELECT * FROM t_jem_types ORDER BY id')->loadAssocList();
            foreach ($after as $index => $row) {
                self::assertSame('*', $row['language']);
                $row['language'] = $before[$index]['language'];
                self::assertSame($before[$index], $row);
            }
        }
        foreach (['5.0.2', '5.1.0'] as $version) {
            $path = JEM_TEST_ROOT . '/admin/sql/updates/mysql/' . $version . '.sql';
            if (!is_file($path)) {
                continue;
            }
            self::assertSame(1, preg_match('/UPDATE \x60#__jem_types\x60 SET \x60language\x60 = \'\*\'[^;]*;/', file_get_contents($path), $match));
            $this->db->setQuery("UPDATE t_jem_types SET language = 'de-DE'")->execute();
            $this->db->setQuery($match[0])->execute();
            self::assertSame(['*'], $this->db->setQuery('SELECT DISTINCT language FROM t_jem_types')->loadColumn());
        }
    }

    /** @return void Special Day labels must not change numeric or legacy-name references. */
    public function testSpecialDayLabelsKeepStoredKeysAcrossLanguages(): void
    {
        $this->insert(1, ['entity' => 4, 'name' => 'Weekend', 'translations' => '{"es-ES":{"name":"Fin de semana"}}']);
        $this->insert(2, ['entity' => 4, 'name' => 'Hidden', 'access' => 2]);
        $this->insert(3, ['entity' => 4, 'name' => 'Unpublished', 'published' => 0]);
        $this->db->setQuery("CREATE TABLE t_jem_special_days (
            id INTEGER PRIMARY KEY, title TEXT DEFAULT '', day_type_id INTEGER DEFAULT 1, day_type TEXT DEFAULT 'Weekend',
            start_date TEXT DEFAULT '2026-11-01', end_date TEXT, weekdays TEXT, description TEXT DEFAULT '',
            article_id INTEGER DEFAULT 0, url TEXT DEFAULT '', show_dates INTEGER DEFAULT 1,
            access INTEGER DEFAULT 1, ordering INTEGER DEFAULT 0, published INTEGER DEFAULT 1
        )")->execute();
        $this->db->setQuery('INSERT INTO t_jem_special_days (id) VALUES (1)')->execute();
        foreach (['es-ES' => 'Fin de semana', 'en-GB' => 'Weekend', 'fr-FR' => 'Weekend'] as $tag => $label) {
            Factory::$application->tag = $tag;
            self::assertSame(['Weekend'], array_keys(Policy\JemHelper::calendarSpecialDayTypes()));
            foreach ([1, 'Weekend'] as $reference) {
                $type = Policy\JemHelper::resolveCalendarSpecialDayType($reference);
                self::assertSame('Weekend', $type['name']);
                self::assertSame($label, $type['label']);
            }
            $days = Policy\JemHelper::calendarSpecialDays('2026-11-01', '2026-11-01');
            self::assertSame('Weekend', $days['2026-11-01'][0]['type']);
            self::assertSame($label, $days['2026-11-01'][0]['type_label']);
            $legend = array_values(Policy\JemHelper::calendarSpecialDayLegend('2026-11-01', '2026-11-01'));
            self::assertSame($label, $legend[0]['type']);
            self::assertSame('Weekend', $legend[0]['type_name']);
            self::assertSame('special-day-type-weekend', $legend[0]['filter_class']);
        }
    }

    /** @return void Form and installer wiring follow the compatibility policy. */
    public function testFormAndInstallerWiring(): void
    {
        $xml = simplexml_load_file(JEM_TEST_ROOT . '/admin/models/forms/type.xml');
        self::assertSame([], $xml->xpath('//field[@name="language"]'));
        self::assertNotEmpty($xml->xpath('//field[@name="base_language"]'));
        $script = file_get_contents(JEM_TEST_ROOT . '/script.php');
        self::assertStringContainsString('$this->normaliseTypeLanguages();', $script);
        self::assertLessThan(strpos($script, '$this->normaliseTypeLanguages();'), strpos($script, '$this->repair502SchemaParity();'));
    }
}
