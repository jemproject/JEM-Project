<?php
/** Exercise current Housekeeping source with disposable SQL records and real temporary image files. */
declare(strict_types=1);
if (getenv('JEM_TEST_REFERENCE_CLEANUP') !== '1') { throw new RuntimeException('Explicit disposable database test opt-in required.'); }
define('_JEXEC', 1);
[$script, $joomla, $source] = $argv;
$fixture = sys_get_temp_dir() . '/jem-2487-site-' . bin2hex(random_bytes(6));
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
$database = 'jem_2487_' . bin2hex(random_bytes(7));
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
    $code = file_get_contents($source . '/admin/models/housekeeping.php');
    $code = str_replace("require_once JPATH_SITE . '/components/com_jem/classes/", "require_once \$source . '/site/classes/", $code);
    eval(preg_replace('/\?>\s*$/', '', substr($code, 5)));
    class JemHelper { public static function addLogEntry(...$args): void {} public static function config(): object { return (object) ['attachments_path' => 'attachments']; } }
    $model = (new ReflectionClass(JemModelHousekeeping::class))->newInstanceWithoutConstructor();
    $model->setDatabase($db);
    (new ReflectionProperty($model, 'map'))->setValue($model, [
        1 => ['folder' => 'events', 'table' => 'events', 'field' => 'datimage'],
        2 => ['folder' => 'venues', 'table' => 'venues', 'field' => 'locimage'],
        3 => ['folder' => 'categories', 'table' => 'categories', 'field' => 'image'],
    ]);
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
    require $source . '/admin/classes/housekeepingfiles.class.php';
    $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
    $cases = [];
    foreach (['events', 'venues', 'categories'] as $area) {
        $base = $fixture . '/images/jem/' . $area;
        mkdir($base . '/small', 0777, true);
        foreach (['menu_original', 'menu_thumbnail', 'orphan_thumbnail', 'article', 'core_category', 'module',
            'late_5101', 'query_failure', 'unused', 'new_reference_after_preview', 'sixth_reference',
            'longer_filename', 'external_host', 'same_host', 'root_relative', 'encoded', 'unicode_json',
            'fragment', 'entities'] as $case) {
            foreach ([false, true] as $acknowledged) {
                foreach (['content', 'modules', 'menu', 'categories'] as $table) {
                    $db->setQuery('TRUNCATE TABLE #__' . $table)->execute();
                }
                $name = $case === 'encoded' ? 'photo space.png' : ($case === 'entities' ? 'photo&one.png' : 'photo.png');
                foreach (glob($base . '/*.png') as $old) { unlink($old); }
                foreach (glob($base . '/small/*.png') as $old) { unlink($old); }
                foreach ([$name, 'small/' . $name] as $file) { file_put_contents($base . '/' . $file, $image); }
                if ($case === 'orphan_thumbnail') { unlink($base . '/' . $name); }
                clearstatcache();
                $path = 'images/jem/' . $area . '/' . (in_array($case, ['menu_thumbnail', 'orphan_thumbnail'], true) ? 'small/' : '') . $name;
                $stored = match ($case) {
                    'longer_filename' => $path . '.backup.png',
                    'external_host' => 'https://other.example/demo/' . $path,
                    'same_host' => 'https://site.example/demo/' . $path,
                    'root_relative' => '/demo/' . $path,
                    'encoded' => str_replace(' ', '%20', $path),
                    'fragment' => $path . '#joomlaImage://local-images/photo.png?width=1&height=1',
                    'entities' => str_replace('&', '&amp;', $path),
                    default => $path,
                };
                $reference = json_encode(['image' => $stored], JSON_THROW_ON_ERROR);
                if ($case === 'unicode_json') { $reference = str_replace('images', '\u0069mages', $reference); }
                $table = match ($case) {
                    'article' => 'content', 'core_category' => 'categories', 'module' => 'modules', default => 'menu',
                };
                $column = match ($case) {
                    'article' => 'images', 'core_category' => 'description', default => 'params',
                };
                if (!in_array($case, ['late_5101', 'query_failure', 'unused', 'new_reference_after_preview'], true)) {
                    $db->setQuery('INSERT INTO #__' . $table . ' (id, `' . $column . '`) VALUES (1, ' . $db->quote($reference) . ')')->execute();
                }
                if ($case === 'sixth_reference') {
                    for ($id = 2; $id <= 6; $id++) {
                        $db->setQuery('INSERT INTO #__menu (id, params) VALUES (' . $id . ', ' . $db->quote($reference) . ')')->execute();
                    }
                }
                if ($case === 'late_5101') {
                    for ($start = 1; $start <= 5101; $start += 500) {
                        $values = [];
                        for ($id = $start; $id <= min(5101, $start + 499); $id++) {
                            $values[] = '(' . $id . ', ' . $db->quote($id === 5101 ? $path : 'images/jem/' . $area . '/different.png') . ')';
                        }
                        $db->setQuery('INSERT INTO #__modules (id, content) VALUES ' . implode(',', $values))->execute();
                    }
                }
                $service = new JemHousekeepingFiles($db, $model);
                $preview = $service->imageCandidates($area);
                if ($case === 'new_reference_after_preview') {
                    $db->setQuery('INSERT INTO #__menu (id, params) VALUES (1, ' . $db->quote($reference) . ')')->execute();
                }
                if ($case === 'sixth_reference') { $db->setQuery('DELETE FROM #__menu WHERE id = 6')->execute(); }
                if ($case === 'query_failure') { $db->setQuery('ALTER TABLE #__menu CHANGE params params_held TEXT')->execute(); }
                $result = null;
                $error = null;
                $cause = false;
                try {
                    $result = $service->delete($area, array_column($preview, 'id'), $acknowledged);
                } catch (JemHousekeepingImageReferenceException $caught) {
                    $error = $caught->getMessage();
                    $cause = $caught->getPrevious() !== null;
                } finally {
                    if ($case === 'query_failure') { $db->setQuery('ALTER TABLE #__menu CHANGE params_held params TEXT')->execute(); }
                }
                clearstatcache();
                $cases[$area . ':' . $case . ':' . (int) $acknowledged] = [
                    'references' => $preview[0]['elsewhere'] ?? [], 'result' => $result,
                    'original_survives' => is_file($base . '/' . $name),
                    'thumbnail_survives' => is_file($base . '/small/' . $name), 'error' => $error, 'cause' => $cause,
                ];
            }
        }
    }

    // A mixed batch must not delete its unused first item before an unacknowledged warning.
    $base = $fixture . '/images/jem/events';
    foreach (['content', 'modules', 'menu', 'categories'] as $table) { $db->setQuery('TRUNCATE TABLE #__' . $table)->execute(); }
    foreach (glob($base . '/*.png') as $old) { unlink($old); }
    foreach (glob($base . '/small/*.png') as $old) { unlink($old); }
    file_put_contents($base . '/a-unused.png', $image);
    file_put_contents($base . '/z-used.png', $image);
    $db->setQuery('INSERT INTO #__menu (id, params) VALUES (1, ' . $db->quote('images/jem/events/z-used.png') . ')')->execute();
    $service = new JemHousekeepingFiles($db, $model);
    $selected = array_column($service->imageCandidates('events'), 'id');
    $blocked = false;
    try { $service->delete('events', $selected); } catch (JemHousekeepingImageReferenceException $error) { $blocked = true; }
    $mixed = ['blocked' => $blocked, 'unused_survives' => is_file($base . '/a-unused.png'), 'used_survives' => is_file($base . '/z-used.png')];
    $mixed['confirmed'] = $service->delete('events', $selected, true);

    $db->setQuery('CREATE TABLE #__jem_attachments (id INT PRIMARY KEY, object VARCHAR(255), file VARCHAR(255))')->execute();
    mkdir($fixture . '/attachments/event1', 0777, true);
    file_put_contents($fixture . '/attachments/event1/REGISTERED.TXT', 'registered');
    file_put_contents($fixture . '/attachments/event1/unused.txt', 'unused');
    $caseSensitive = !is_file($fixture . '/attachments/event1/registered.txt');
    if ($caseSensitive) { file_put_contents($fixture . '/attachments/event1/registered.txt', 'different file'); }
    $db->setQuery("INSERT INTO #__jem_attachments VALUES (1, 'event1', 'registered.txt')")->execute();
    $attachments = ['case_sensitive' => $caseSensitive, 'candidates' => array_column($service->attachmentCandidates(), 'relative')];

    echo json_encode(['cases' => $cases, 'mixed' => $mixed, 'attachments' => $attachments], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
} finally {
    if ($db) {
        $db->disconnect();
    }
    $server->query('DROP DATABASE ' . $database);
    $server->close();
    $base = realpath($fixture);
    if ($base && str_starts_with($base, realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR . 'jem-2487-site-')) {
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
