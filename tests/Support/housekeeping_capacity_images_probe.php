<?php
/** Exercise current Housekeeping source with disposable SQL records and real temporary image files. */
declare(strict_types=1);
if (getenv('JEM_TEST_CAPACITY_CLEANUP') !== '1') { throw new RuntimeException('Explicit disposable database test opt-in required.'); }
define('_JEXEC', 1);
[$script, $joomla, $source] = $argv;
$fixture = sys_get_temp_dir() . '/jem-2477-site-' . bin2hex(random_bytes(6));
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
$database = 'jem_2477_' . bin2hex(random_bytes(7));
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
        public function get($key, $default = null): mixed { return $key === "secret" ? "2477-test" : false; }
        public function enqueueMessage($message, $type): void { throw new RuntimeException($message); }
    };
    $install = file_get_contents($source . '/admin/sql/install.mysql.utf8.sql');
    foreach (['events', 'categories', 'venues', 'links', 'venue_capacity_profiles', 'venue_spaces', 'venue_layouts', 'venue_capacity_areas'] as $table) {
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
    require $source . '/admin/classes/housekeepingfiles.class.php';
    class JemHelper
    {
        public static int $tokenChecks = 0;
        /** @return void Record the controller boundary; authentication is outside this fixture. */
        public static function requirePostToken(): void { ++self::$tokenChecks; }
        /** @return void Keep fixture log output in memory; no installed-site log is written. */
        public static function addLogEntry(...$args): void {}
    }
    $model = (new ReflectionClass(JemModelHousekeeping::class))->newInstanceWithoutConstructor();
    $model->setDatabase($db);
    (new ReflectionProperty($model, 'map'))->setValue($model, [
        1 => ['folder' => 'events', 'table' => 'events', 'field' => 'datimage'],
        2 => ['folder' => 'venues', 'table' => 'venues', 'field' => 'locimage'],
        3 => ['folder' => 'categories', 'table' => 'categories', 'field' => 'image'],
    ]);
    $files = new JemHousekeepingFiles($db, $model);
    $records = [
        'venue_capacity_profiles' => ['id' => 201, 'venue_id' => 101, 'code' => 'profile', 'image' => 'profile.png'],
        'venue_spaces' => ['id' => 301, 'venue_id' => 101, 'code' => 'space', 'image' => 'space.png'],
        'venue_layouts' => ['id' => 401, 'venue_space_id' => 301, 'code' => 'layout', 'image' => 'layout.png'],
        'venue_capacity_areas' => ['id' => 501, 'venue_layout_id' => 401, 'code' => 'area', 'image' => 'area.png'],
    ];
    $folders = [
        JemVenueImagePath::profileFolder(101, 201),
        JemVenueImagePath::spaceFolder(101, 301),
        JemVenueImagePath::layoutFolder(101, 301, 401),
        JemVenueImagePath::areaFolder(101, 301, 401, 501),
    ];
    $scenarios = ['published', 'unpublished', 'missing_original', 'shared_names', 'encoded_names',
        'missing_space', 'missing_layout', 'invalid_venue', 'invalid_filename', 'empty_images', 'stale_selection'];
    foreach (array_keys($records) as $table) {
        $scenarios[] = 'missing_table:' . $table;
        $scenarios[] = 'missing_column:' . $table;
    }
    $results = [];
    $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
    foreach ($scenarios as $scenario) {
        foreach (['quick', 'selected'] as $mode) {
            if ($scenario === 'stale_selection' && $mode === 'quick') {
                continue;
            }
            // Restore only the known fixture files; never remove installed-site files.
            foreach (array_unique($paths) as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            $paths = [];
            foreach (['venues', 'links', ...array_keys($records)] as $table) {
                $db->setQuery('TRUNCATE TABLE #__jem_' . $table)->execute();
            }
            $db->setQuery("INSERT INTO #__jem_venues (id,venue,locimage) VALUES(101,'Disposable venue','main.png')")->execute();
            $db->setQuery("INSERT INTO #__jem_links (id,event_id,title,type,url,created_by,params) VALUES(1,1,'Link','link','',1,'{\"image\":\"images/jem/venues/link.png\"}')")->execute();
            $capacity = [];
            foreach ($records as $table => $values) {
                $i = count($capacity);
                if ($scenario === 'unpublished') {
                    $values['published'] = 0;
                }
                if ($scenario === 'shared_names') {
                    $values['image'] = 'shared.png';
                }
                if ($scenario === 'encoded_names') {
                    $values['image'] = 'Plan%20Photo.PNG';
                }
                $physicalName = rawurldecode($values['image']);
                $capacity[] = $folders[$i] . '/' . $physicalName;
                if ($scenario === 'empty_images' || $scenario === 'stale_selection') {
                    $values['image'] = '';
                }
                $record = (object) $values;
                $db->insertObject('#__jem_' . $table, $record);
            }
            $all = [...$capacity, 'orphan.png', 'main.png', 'link.png'];
            foreach ($all as $relative) {
                foreach (['', 'small/'] as $thumb) {
                    // Encoded names also exercise an in-use thumbnail without its original.
                    if (in_array($scenario, ['missing_original', 'encoded_names'], true) && $thumb === '' && $relative === $capacity[0]) {
                        continue;
                    }
                    $file = $fixture . '/images/jem/venues/' . $thumb . $relative;
                    if (!is_dir(dirname($file))) {
                        mkdir(dirname($file), 0777, true);
                    }
                    file_put_contents($file, $image);
                    $paths[] = $file;
                }
            }
            $selected = array_column($files->candidates('venues'), 'id');
            $restoreSql = null;
            if (str_starts_with($scenario, 'missing_table:')) {
                $table = explode(':', $scenario)[1];
                $db->setQuery('RENAME TABLE #__jem_' . $table . ' TO #__jem_' . $table . '_held')->execute();
                $restoreSql = 'RENAME TABLE #__jem_' . $table . '_held TO #__jem_' . $table;
            } elseif (str_starts_with($scenario, 'missing_column:')) {
                $table = explode(':', $scenario)[1];
                $db->setQuery('ALTER TABLE #__jem_' . $table . ' CHANGE image image_held varchar(100) NOT NULL')->execute();
                $restoreSql = 'ALTER TABLE #__jem_' . $table . ' CHANGE image_held image varchar(100) NOT NULL';
            } elseif ($scenario === 'missing_space') {
                $db->setQuery('DELETE FROM #__jem_venue_spaces WHERE id=301')->execute();
            } elseif ($scenario === 'missing_layout') {
                $db->setQuery('DELETE FROM #__jem_venue_layouts WHERE id=401')->execute();
            } elseif ($scenario === 'invalid_venue') {
                $db->setQuery('UPDATE #__jem_venue_capacity_profiles SET venue_id=0')->execute();
            } elseif ($scenario === 'invalid_filename') {
                $db->setQuery("UPDATE #__jem_venue_capacity_profiles SET image='../profile.png'")->execute();
            } elseif ($scenario === 'stale_selection') {
                foreach ($records as $table => $values) {
                    $db->setQuery('UPDATE #__jem_' . $table . ' SET image=' . $db->quote($values['image']))->execute();
                }
            }
            $before = array_values(array_filter($paths, 'is_file'));
            $row = ['preview' => null, 'preview_error' => null, 'delete_error' => null, 'deleted' => null];
            try {
                try {
                    $row['preview'] = array_column($files->candidates('venues'), 'relative');
                } catch (JemHousekeepingImageReferenceException $error) {
                    $row['preview_error'] = $error->getMessage();
                }
                try {
                    $result = $mode === 'quick' ? $model->delete(JemModelHousekeeping::VENUES) : $files->delete('venues', $selected);
                    $row['deleted'] = $mode === 'quick' ? $result : $result['deleted'];
                    $row['result'] = $result;
                } catch (JemHousekeepingImageReferenceException $error) {
                    $row['delete_error'] = $error->getMessage();
                    $row['cause'] = get_class($error->getPrevious());
                }
                clearstatcache();
                $relative = static fn(string $path): string => str_replace($fixture . '/images/jem/venues/', '', $path);
                $row['before'] = array_map($relative, $before);
                $row['after'] = array_map($relative, array_values(array_filter($before, 'is_file')));
                $row['capacity'] = $capacity;
                $results[$scenario . ':' . $mode] = $row;
            } finally {
                if ($restoreSql !== null) {
                    $db->setQuery($restoreSql)->execute();
                }
            }
        }
    }
    // Execute the actual controller method with its model and authorisation boundaries isolated.
    $controller = file_get_contents($source . '/admin/controllers/housekeeping.php');
    if (!preg_match('/^    public function delete\(\)[^\n]*\n.*?^    }/ms', $controller, $method)) {
        throw new RuntimeException('Cleanup controller method missing');
    }
    eval('use Joomla\\CMS\\Factory; use Joomla\\CMS\\Language\\Text; class CleanupController2477 {'
        . 'public $model; public array $redirects = []; public bool $allowed = false;'
        . 'public function getModel($name) { return $this->model; }'
        . 'public function allowHousekeeping() { $this->allowed = true; }'
        . 'public function setRedirect(...$args) { $this->redirects[] = $args; }'
        . $method[0] . '}');
    $input = Joomla\CMS\Factory::$application->getInput();
    $input->post->set('task', 'housekeeping.cleanvenueimg');
    Joomla\CMS\Factory::$application->input = $input;
    $controllerResults = [];
    foreach ([false, true] as $failure) {
        $harness = new CleanupController2477();
        $harness->model = new class ($failure) {
            public const VENUES = 2;
            public function __construct(private bool $failure) {}
            public function delete($type): int {
                if ($this->failure) {
                    throw new JemHousekeepingImageReferenceException(Joomla\CMS\Language\Text::_('COM_JEM_HOUSEKEEPING_VENUE_IMAGE_REFERENCES_FAILED'));
                }
                return 1;
            }
        };
        $harness->delete();
        $controllerResults[] = ['allowed' => $harness->allowed, 'redirects' => $harness->redirects];
    }
    $view = file_get_contents($source . '/admin/views/housekeeping/view.html.php');
    $start = strpos($view, '            $this->filesCounts = array();');
    $end = strpos($view, '            $this->filesBatchLimit', $start);
    $prepare = eval('return function ($files, $areas) {' . substr($view, $start, $end - $start) . '};');
    $viewData = new class {
        public string $filesArea = 'venues';
        public array $filesCounts;
        public array $filesCandidates;
        public array $filesErrors;
    };
    $reader = new class {
        public function candidates($area): array {
            if ($area === 'venues') {
                throw new JemHousekeepingImageReferenceException('Unverified <capacity> references');
            }
            return [['relative' => 'available.png']];
        }
    };
    $prepare->call($viewData, $reader, ['events', 'venues', 'categories', 'attachments']);
    $template = file_get_contents($source . '/admin/views/housekeeping/tmpl/files.php');
    $start = strpos($template, '    <?php if (!empty($this->filesErrors[$area]))');
    $end = strpos($template, '    <?php else : ?>', $start);
    if ($start === false || $end === false) {
        throw new RuntimeException('Preview warning template missing');
    }
    $render = eval('return function () { $area = "venues"; $escape = static fn($s) => htmlspecialchars($s, ENT_QUOTES, "UTF-8"); ?>'
        . substr($template, $start, $end - $start) . '<?php endif; };');
    ob_start();
    $render->call($viewData);
    $warningHtml = ob_get_clean();
    echo json_encode(['cases' => $results, 'controller' => $controllerResults, 'token_checks' => JemHelper::$tokenChecks,
        'view' => $viewData, 'warning_html' => $warningHtml], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
} finally {
    if ($db) {
        $db->disconnect();
    }
    $server->query('DROP DATABASE ' . $database);
    $server->close();
    $base = realpath($fixture);
    if ($base && str_starts_with($base, realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR . 'jem-2477-site-')) {
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
