<?php
/** Exercise current Housekeeping source with disposable SQL records and real temporary image files. */
declare(strict_types=1);
if (getenv('JEM_TEST_PURGED_DETAILS') !== '1') { throw new RuntimeException('Explicit disposable database test opt-in required.'); }
define('_JEXEC', 1);
[$script, $joomla, $source] = $argv;
$fixture = sys_get_temp_dir() . '/jem-2488-site-' . bin2hex(random_bytes(6));
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
$database = 'jem_2488_' . bin2hex(random_bytes(7));
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
        public function bootComponent($option): object { return new stdClass(); }
        public function getInput(): object { return $this->input ??= new Joomla\Input\Input(); }
        public function get($key, $default = null): mixed { return $key === "secret" ? "2478-test" : false; }
        public function enqueueMessage($message, $type): void { throw new RuntimeException($message); }
    };

    $install = file_get_contents($source . '/admin/sql/install.mysql.utf8.sql');
    foreach (['events', 'register', 'register_items', 'register_capacity_allocations', 'register_history', 'config'] as $table) {
        if (!preg_match('/CREATE TABLE IF NOT EXISTS `#__jem_' . $table . '`.*?ENGINE=InnoDB;/s', $install, $match)) {
            throw new RuntimeException('Fixture schema missing: ' . $table);
        }
        $db->setQuery($match[0])->execute();
    }
    $db->setQuery("INSERT INTO #__jem_config (keyname, value) VALUES ('registration_schema_ready', '1')")->execute();
    $code = file_get_contents($source . '/admin/models/housekeeping.php');
    $code = str_replace("require_once JPATH_SITE . '/components/com_jem/classes/", "require_once \$source . '/site/classes/", $code);
    eval(preg_replace('/\?>\s*$/', '', substr($code, 5)));
    // The model above is real source; satisfy the installed-path include without loading site files.
    mkdir(JPATH_ADMINISTRATOR . '/components/com_jem/models', 0777, true);
    file_put_contents(JPATH_ADMINISTRATOR . '/components/com_jem/models/housekeeping.php', '<?php // Model already loaded from source.');
    require $source . '/admin/classes/housekeepingdiagnostics.class.php';
    require $source . '/site/classes/registrationidentity.class.php';
    require $source . '/site/classes/registrationtransition.class.php';
    require $source . '/site/classes/registrationservice.class.php';
    $container->alias(Joomla\Database\DatabaseDriver::class, 'DatabaseDriver');
    $model = new JemModelHousekeeping();
    $diagnostics = new JemHousekeepingDiagnostics($db);
    Joomla\CMS\HTML\HTMLHelper::register('image', static fn(...$args): string => '');

    /** Read all temporary data to verify the diagnostic and rendering remain read-only. */
    $snapshot = static function () use ($db): string {
        $tables = $db->getTableList();
        sort($tables);
        $data = [];
        foreach ($tables as $table) {
            $data[$table] = $db->setQuery('SELECT * FROM ' . $db->quoteName($table))->loadAssocList();
        }
        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    };

    /** Seed existing immutable detail snapshots; subsequent cancellation/purge use the real service. */
    $insertDetails = static function (int $id, int $revision, int $eventId = 11) use ($db): void {
        $item = (object) ['register_id' => $id, 'registration_revision' => $revision, 'line_number' => 1,
            'line_kind' => 'admission', 'currency' => 'EUR', 'quantity' => 2];
        $db->insertObject('#__jem_register_items', $item);
        $allocation = (object) ['register_id' => $id, 'registration_revision' => $revision, 'event_id' => $eventId,
            'venue_layout_id' => 1, 'area_code' => 'A', 'quantity' => 2];
        $db->insertObject('#__jem_register_capacity_allocations', $allocation);
    };

    $cases = [];
    foreach (['empty', 'live', 'cancelled_parent', 'native_purge', 'cancel_then_purge', 'deleted_event',
        'genuine_orphan', 'cancelled_without_purge', 'newer_revision', 'equal_revision', 'zero_revision',
        'zero_id', 'wrong_event', 'mixed', 'multiple_revisions', 'duplicate_purge', 'missing_history_table',
        'missing_history_revision', 'missing_items', 'missing_allocations', 'missing_detail_revision'] as $case) {
        foreach (['events', 'register', 'register_items', 'register_capacity_allocations', 'register_history'] as $table) {
            $db->setQuery('TRUNCATE TABLE #__jem_' . $table)->execute();
        }
        $event = (object) ['id' => 11, 'title' => 'Retained event title', 'introtext' => '', 'fulltext' => '',
            'created_by_alias' => '', 'metadata' => ''];
        $db->insertObject('#__jem_events', $event);
        $cancelledStatus = null;
        if ($case !== 'empty') {
            $registration = (object) ['id' => 101, 'event' => 11, 'reference' => JemRegistrationIdentity::generateRegistrationReference(),
                'revision' => 3, 'places' => 2];
            $db->insertObject('#__jem_register', $registration);
            $insertDetails(101, 3, $case === 'wrong_event' ? 22 : 11);
            $service = new JemRegistrationService($db);
            if (in_array($case, ['cancelled_parent', 'cancel_then_purge', 'deleted_event', 'cancelled_without_purge'], true)) {
                $service->cancelByIds([101], 11, ['source' => 'test.housekeeping']);
                $cancelledStatus = (int) $db->setQuery('SELECT status FROM #__jem_register WHERE id = 101')->loadResult();
            }
            if (in_array($case, ['genuine_orphan', 'cancelled_without_purge'], true)) {
                $db->setQuery('DELETE FROM #__jem_register')->execute();
            } elseif (!in_array($case, ['live', 'cancelled_parent'], true)) {
                $service->purgeForEvent(11, ['source' => 'test.housekeeping']);
            }
            if ($case === 'deleted_event') { $db->setQuery('DELETE FROM #__jem_events WHERE id = 11')->execute(); }
            if (in_array($case, ['equal_revision', 'newer_revision', 'zero_revision'], true)) {
                foreach (['register_items', 'register_capacity_allocations'] as $table) {
                    $db->setQuery('UPDATE #__jem_' . $table . ' SET registration_revision = '
                        . ['equal_revision' => 4, 'newer_revision' => 5, 'zero_revision' => 0][$case])->execute();
                }
            }
            if ($case === 'zero_id') {
                foreach (['register_items', 'register_capacity_allocations'] as $table) {
                    $db->setQuery('UPDATE #__jem_' . $table . ' SET register_id = 0')->execute();
                }
                $db->setQuery('UPDATE #__jem_register_history SET registration_id = 0')->execute();
            }
            if ($case === 'mixed') { $insertDetails(102, 1); }
            if ($case === 'multiple_revisions') { $insertDetails(101, 1); $insertDetails(101, 2); }
            if ($case === 'duplicate_purge') {
                $history = $db->setQuery('SELECT * FROM #__jem_register_history')->loadObject();
                unset($history->id);
                $history->revision = 5;
                $history->operation_reference = JemRegistrationIdentity::generateOperationReference();
                $db->insertObject('#__jem_register_history', $history);
            }
        }
        $history = $db->setQuery('SELECT registration_id, registration_reference, event_id, event_title, revision, action FROM #__jem_register_history ORDER BY revision')->loadAssocList();
        $restore = null;
        if (in_array($case, ['missing_history_table', 'missing_items', 'missing_allocations'], true)) {
            $table = ['missing_history_table' => 'register_history', 'missing_items' => 'register_items',
                'missing_allocations' => 'register_capacity_allocations'][$case];
            $db->setQuery('RENAME TABLE #__jem_' . $table . ' TO #__jem_' . $table . '_held')->execute();
            $restore = 'RENAME TABLE #__jem_' . $table . '_held TO #__jem_' . $table;
        } elseif (in_array($case, ['missing_history_revision', 'missing_detail_revision'], true)) {
            [$table, $column] = $case === 'missing_history_revision' ? ['register_history', 'revision'] : ['register_items', 'registration_revision'];
            $db->setQuery('ALTER TABLE #__jem_' . $table . ' CHANGE ' . $column . ' held_revision INT UNSIGNED NOT NULL')->execute();
            $restore = 'ALTER TABLE #__jem_' . $table . ' CHANGE held_revision ' . $column . ' INT UNSIGNED NOT NULL';
        }
        try {
            $before = $snapshot();
            $counts = $model->getOrphanRegistrationDetails();
            $report = ['notice' => 0, 'warning' => 0, 'error' => 0, 'lines' => [],
                'inventory' => ['registrations' => ['total' => 0, 'orphaned' => 0, 'last_id' => 0]]];
            (new ReflectionMethod($diagnostics, 'checkRegistrations'))->invokeArgs($diagnostics, [&$report]);
            $view = new class ($counts) {
                public array $registrationOrphans = [];
                public bool $registrationPurgeAllowed = false;
                public string $registrationNonce = '';
                public function __construct(public array $registrationDetails) {}
                /** @return string Actual registration diagnostic template with a read-only fixture. */
                public function render(string $template): string { ob_start(); require $template; return ob_get_clean(); }
            };
            $html = $view->render($source . '/admin/views/housekeeping/tmpl/registrations.php');
            $cases[$case] = ['counts' => $counts, 'diagnostics' => $report, 'html' => $html,
                'unchanged' => $before === $snapshot(), 'history' => $history, 'cancelled_status' => $cancelledStatus];
        } finally {
            if ($restore !== null) { $db->setQuery($restore)->execute(); }
        }
    }
    echo json_encode(['cases' => $cases], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
} finally {
    if ($db) {
        $db->disconnect();
    }
    $server->query('DROP DATABASE ' . $database);
    $server->close();
    $base = realpath($fixture);
    if ($base && str_starts_with($base, realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR . 'jem-2488-site-')) {
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
