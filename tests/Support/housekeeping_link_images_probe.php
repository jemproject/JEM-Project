<?php
/** Exercise current Housekeeping source with disposable SQL records and real temporary image files. */
declare(strict_types=1);
if (getenv('JEM_TEST_LINK_CLEANUP') !== '1') { throw new RuntimeException('Explicit disposable database test opt-in required.'); }
define('_JEXEC', 1);
[$script, $joomla, $source] = $argv;
$fixture = sys_get_temp_dir() . '/jem-2478-site-' . bin2hex(random_bytes(6));
mkdir($fixture);
define('JPATH_SITE', $fixture);
define('JPATH_BASE', $fixture);
define('JPATH_ROOT', $fixture);
define('JPATH_ADMINISTRATOR', $fixture . '/administrator');
define('JPATH_CACHE', $fixture . '/cache');
mkdir(JPATH_CACHE);
define('JPATH_LANGUAGES', $fixture . '/language');
require $joomla . '/libraries/vendor/autoload.php';
spl_autoload_register(static function (string $class) use ($joomla): void {
    if (str_starts_with($class, 'Joomla\\CMS\\')) {
        $file = $joomla . '/libraries/src/' . str_replace('\\', '/', substr($class, 11)) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
});
require $joomla . '/configuration.php';
$config = new JConfig();
$server = new mysqli($config->host, $config->user, $config->password);
$database = 'jem_2478_' . bin2hex(random_bytes(7));
$server->query('CREATE DATABASE ' . $database . ' CHARACTER SET utf8mb4');
$db = null;
$paths = [];
try {
    $db = new Joomla\Database\Mysqli\MysqliDriver([
        'host' => $config->host, 'user' => $config->user, 'password' => $config->password,
        'database' => $database, 'prefix' => 't_',
    ]);
    $container = new Joomla\DI\Container();
    $container->set('DatabaseDriver', $db, true);
    $container->alias(Joomla\Database\DatabaseInterface::class, 'DatabaseDriver');
    (new ReflectionProperty(Joomla\CMS\Factory::class, 'container'))->setValue(null, $container);
    Joomla\CMS\Factory::$language = new Joomla\CMS\Language\Language('en-GB');
    $strings = parse_ini_file($source . '/admin/language/en-GB/com_jem.ini', false, INI_SCANNER_RAW);
    (new ReflectionProperty(Joomla\CMS\Factory::$language, 'strings'))->setValue(Joomla\CMS\Factory::$language, $strings);
    Joomla\CMS\Factory::$application = new class {
        public $input;
        public function getInput(): object { return $this->input ??= new Joomla\Input\Input(); }
        public function get($key, $default = null): mixed { return $key === "secret" ? "2478-test" : false; }
        public function enqueueMessage($message, $type): void { throw new RuntimeException($message); }
    };
    $install = file_get_contents($source . '/admin/sql/install.mysql.utf8.sql');
    foreach (['events', 'venues', 'categories', 'links', ...(str_contains($install, '`#__jem_venue_capacity_profiles`') ? ['venue_capacity_profiles', 'venue_spaces', 'venue_layouts', 'venue_capacity_areas'] : [])] as $table) {
        if (!preg_match('/CREATE TABLE IF NOT EXISTS `#__jem_' . $table . '`.*?ENGINE=InnoDB;/s', $install, $match)) {
            throw new RuntimeException('Fixture schema missing: ' . $table);
        }
        $db->setQuery($match[0])->execute();
    }
    (new ReflectionProperty(Joomla\CMS\Uri\Uri::class, 'root'))->setValue(null, [
        'prefix' => 'https://site.example', 'path' => '/demo',
    ]);
    foreach ([
        'content' => ['introtext', 'fulltext', 'images', 'urls'],
        'modules' => ['content', 'params'], 'menu' => ['params'], 'categories' => ['description'],
    ] as $table => $columns) {
        $defs = array_map(static fn($name) => '`' . $name . '` TEXT NOT NULL DEFAULT \'\'', $columns);
        $db->setQuery('CREATE TABLE #__' . $table . ' (id INT PRIMARY KEY, ' . implode(', ', $defs) . ')')->execute();
    }
    $code = file_get_contents($source . '/admin/models/housekeeping.php');
    $code = str_replace("require_once JPATH_SITE . '/components/com_jem/classes/", "require_once \$source . '/site/classes/", $code);
    eval(preg_replace('/\?>\s*$/', '', substr($code, 5)));
    class JemHelper { public static function addLogEntry(...$args): void {} }
    $model = (new ReflectionClass(JemModelHousekeeping::class))->newInstanceWithoutConstructor();
    $model->setDatabase($db);
    (new ReflectionProperty($model, 'map'))->setValue($model, [
        1 => ['folder' => 'events', 'table' => 'events', 'field' => 'datimage'],
        2 => ['folder' => 'venues', 'table' => 'venues', 'field' => 'locimage'],
        3 => ['folder' => 'categories', 'table' => 'categories', 'field' => 'image'],
    ]);
    $modern = is_file($source . '/admin/classes/housekeepingfiles.class.php');
    if ($modern) {
        require $source . '/admin/classes/housekeepingfiles.class.php';
    }
    $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
    $results = [];
    $baseline = getenv('JEM_TEST_LINK_BASELINE') === '1';
    $scenarios = $baseline ? ['plain'] : ['plain', 'unpublished', 'archived', 'trashed', 'fragment', 'encoded',
        'backslash', 'thumbnail', 'elsewhere', 'external', 'empty', 'escaped_key', 'broken_json', 'array_image',
        'missing_table', 'missing_column', 'stale', 'changed_file', 'only_selected'];
    foreach ([1 => 'events', 2 => 'venues', 3 => 'categories'] as $type => $area) {
        foreach ($scenarios as $scenario) {
            foreach ($baseline ? ['quick'] : ['quick', 'selected'] as $mode) {
                if (in_array($scenario, ['stale', 'changed_file', 'only_selected'], true) && $mode === 'quick') {
                    continue;
                }
                foreach ($paths as $path) {
                    if (is_file($path)) {
                        unlink($path);
                    }
                }
                $paths = [];
                foreach (['events', 'venues', 'categories', 'links'] as $table) {
                    $db->setQuery('TRUNCATE TABLE #__jem_' . $table)->execute();
                }
                $event = (object) ['id' => 1, 'title' => 'Event', 'datimage' => 'cover.png', 'fullimage' => 'detail.png',
                    'introtext' => '', 'fulltext' => '', 'created_by_alias' => '', 'metadata' => '', 'published' => 1];
                $db->insertObject('#__jem_events', $event);
                $db->setQuery("INSERT INTO #__jem_venues (id,venue,locimage) VALUES (1,'Venue','cover.png')")->execute();
                $db->setQuery("INSERT INTO #__jem_categories (id,title,image) VALUES (1,'Category','cover.png')")->execute();
                $name = $scenario === 'encoded' ? 'linked photo.png' : 'linked.png';
                $reference = 'images/jem/' . $area . '/' . $name;
                if ($scenario === 'fragment') { $reference .= '#joomlaImage://local-images/linked.png?width=1&height=1'; }
                if ($scenario === 'encoded') { $reference = str_replace(' ', '%20', $reference); }
                if ($scenario === 'backslash') { $reference = str_replace('/', '\\', $reference); }
                if ($scenario === 'thumbnail') { $reference = 'images/jem/' . $area . '/small/' . $name; }
                if ($scenario === 'elsewhere') { $reference = 'images/jem/' . ($area === 'venues' ? 'events' : 'venues') . '/' . $name; }
                if ($scenario === 'external') { $reference = 'https://example.invalid/' . $reference; }
                if (in_array($scenario, ['empty', 'stale', 'only_selected'], true)) { $reference = ''; }
                $params = json_encode(['image' => $scenario === 'array_image' ? ['wrong'] : $reference], JSON_THROW_ON_ERROR);
                if ($scenario === 'escaped_key') { $params = str_replace('"image"', '"im\u0061ge"', $params); }
                if ($scenario === 'broken_json') { $params = '{"image":'; }
                $record = (object) ['id' => 1, 'event_id' => 1, 'type' => 'link', 'title' => 'Link', 'url' => '', 'created_by' => 1,
                    'state' => $scenario === 'unpublished' ? 0 : 1, 'params' => $params];
                $db->insertObject('#__jem_links', $record);
                $published = ['unpublished' => 0, 'archived' => 2, 'trashed' => -2][$scenario] ?? 1;
                $db->setQuery('UPDATE #__jem_events SET published = ' . $published)->execute();
                $base = $fixture . '/images/jem/' . $area;
                foreach (['cover.png', ...($area === 'events' ? ['detail.png'] : []), $name, 'orphan.png'] as $file) {
                    foreach (['', 'small/'] as $thumb) {
                        $path = $base . '/' . $thumb . $file;
                        if (!is_dir(dirname($path))) { mkdir(dirname($path), 0777, true); }
                        file_put_contents($path, $image);
                        $paths[] = $path;
                    }
                }
                $scan = static function () use (&$paths, $base): array {
                    clearstatcache();
                    return array_values(array_map(static fn($p) => substr($p, strlen($base) + 1), array_filter($paths, 'is_file')));
                };
                $row = ['before' => $scan(), 'preview' => null, 'preview_error' => null, 'delete_error' => null, 'deleted' => null];
                $blocked = in_array($scenario, ['broken_json', 'array_image', 'missing_table', 'missing_column'], true);
                if ($scenario === 'missing_table') { $db->setQuery('RENAME TABLE #__jem_links TO #__jem_links_held')->execute(); }
                if ($scenario === 'missing_column') { $db->setQuery('ALTER TABLE #__jem_links CHANGE params params_held TEXT')->execute(); }
                try {
                    $selected = [];
                    try {
                        if ($baseline) {
                            $row['preview'] = (new ReflectionMethod($model, 'getImages'))->invoke($model, $type);
                        } else {
                            $candidates = $modern ? (new JemHousekeepingFiles($db, $model))->imageCandidates($area) : $model->getImageCleanupCandidates($type);
                            $row['preview'] = array_column($candidates, 'relative');
                            foreach ($candidates as $candidate) {
                                if ($scenario !== 'only_selected' || $candidate['relative'] === 'orphan.png') {
                                    $selected[] = $candidate['id'];
                                }
                            }
                        }
                    } catch (Throwable $error) {
                        $row['preview_error'] = $error->getMessage();
                    }
                    if ($scenario === 'stale') {
                        $db->setQuery('UPDATE #__jem_links SET params = ' . $db->quote(json_encode(['image' => 'images/jem/' . $area . '/' . $name])))->execute();
                    }
                    if ($scenario === 'changed_file') {
                        file_put_contents($base . '/orphan.png', $image . 'changed');
                        clearstatcache();
                    }
                    try {
                        if ($mode === 'quick') {
                            $row['deleted'] = $model->delete($type);
                        } else {
                            // Supply a well-formed id when preview failed: the model must still fail closed.
                            $selected = $selected ?: [str_repeat('a', 64)];
                            $result = $modern ? (new JemHousekeepingFiles($db, $model))->delete($area, $selected)
                                : $model->deleteSelectedImages($type, $selected);
                            $row['deleted'] = $result['deleted'];
                            $row['skipped'] = $result['skipped'];
                        }
                    } catch (Throwable $error) {
                        $row['delete_error'] = $error->getMessage();
                        $row['cause'] = $error->getPrevious() ? get_class($error->getPrevious()) : '';
                    }
                    $row['after'] = $scan();
                    $results[$area . ':' . $scenario . ':' . $mode] = $row;
                } finally {
                    if ($scenario === 'missing_table') { $db->setQuery('RENAME TABLE #__jem_links_held TO #__jem_links')->execute(); }
                    if ($scenario === 'missing_column') { $db->setQuery('ALTER TABLE #__jem_links CHANGE params_held params TEXT')->execute(); }
                }
            }
        }
    }
    echo json_encode(['cases' => $results], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);

} finally {
    if ($db) {
        $db->disconnect();
    }
    $server->query('DROP DATABASE ' . $database);
    $server->close();
    $base = realpath($fixture);
    if ($base && str_starts_with($base, realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR . 'jem-2478-site-')) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $entry) {
            if ($entry->isDir() && !$entry->isLink()) {
                rmdir($entry->getPathname());
            } else {
                unlink($entry->getPathname());
            }
        }
        rmdir($base);
    }
}
