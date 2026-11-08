<?php

/** Exercise checkout import code against disposable SQL tables, never installed site data. */
declare(strict_types=1);

if (getenv('JEM_TEST_IMPORT_RELATIONS') !== '1') {
    throw new RuntimeException('Set JEM_TEST_IMPORT_RELATIONS=1 to create disposable test databases.');
}
define('_JEXEC', 1);
[$script, $root, $source] = $argv;
define('JEM_TEST_ROOT', $source);
define('JPATH_ROOT', $root);
define('JPATH_ADMINISTRATOR', $root . '/administrator');
define('JPATH_COMPONENT_ADMINISTRATOR', $source . '/admin');
require $root . '/libraries/vendor/autoload.php';
spl_autoload_register(static function (string $class) use ($root): void {
    if (str_starts_with($class, 'Joomla\\CMS\\')) {
        $file = $root . '/libraries/src/' . str_replace('\\', '/', substr($class, 11)) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
});
require_once $source . '/tests/Support/recurrence_source_stream.php';
stream_wrapper_register('jemrecurrence', RecurrenceSourceStream::class);
define('JPATH_SITE', 'jemrecurrence://site');
require $root . '/configuration.php';
$config = new JConfig();
$server = new mysqli($config->host, $config->user, $config->password);
$database = 'jem_relations_' . bin2hex(random_bytes(8));
$server->query('CREATE DATABASE ' . $database . ' CHARACTER SET utf8mb4');
$db = null;

try {
    $db = new Joomla\Database\Mysqli\MysqliDriver([
        'host' => $config->host, 'user' => $config->user, 'password' => $config->password,
        'database' => $database, 'prefix' => 't_',
    ]);
    $container = new Joomla\DI\Container();
    $container->set('DatabaseDriver', $db, true);
    $container->alias(Joomla\Database\DatabaseInterface::class, 'DatabaseDriver');
    (new ReflectionProperty(Joomla\CMS\Factory::class, 'container'))->setValue(null, $container);
    $strings = parse_ini_file($source . '/admin/language/en-GB/com_jem.ini', false, INI_SCANNER_RAW);
    Joomla\CMS\Factory::$language = new class ($strings) {
        public function __construct(private array $strings) {}
        public function _($key, ...$args): string { return $this->strings[$key] ?? $key; }
        public function getTag(): string { return 'en-GB'; }
    };
    Joomla\CMS\Factory::$application = new class {
        public function getLanguage(): object { return Joomla\CMS\Factory::$language; }
        public function getIdentity(): object { return (object) ['id' => 99]; }
        public function getDispatcher(): object { return new Joomla\Event\Dispatcher(); }
    };

    // The event table adapter isolates event save hooks/ACL from the model's ID and rollback logic.
    // Table::store and the import persistence helper still execute real SQL in the disposable database.
    class JemTableEvent extends Joomla\CMS\Table\Table
    {
        public function __construct($db)
        {
            parent::__construct('#__jem_events', 'id', $db);
        }
        public function check(): bool { return $this->title !== ''; }
    }
    class JemConfig
    {
        public static function getInstance(): self { return new self(); }
        public function toRegistry(): Joomla\Registry\Registry { return new Joomla\Registry\Registry(); }
        public function set($key, $value): void {}
    }

    $sql = file_get_contents($source . '/admin/sql/install.mysql.utf8.sql');
    if (!preg_match('/CREATE TABLE IF NOT EXISTS `#__jem_cats_event_relations`.*?ENGINE=InnoDB;/s', $sql, $match)) {
        throw new RuntimeException('Cannot find relation schema.');
    }
    $db->setQuery($match[0])->execute();
    $db->setQuery("CREATE TABLE t_jem_events(id INT AUTO_INCREMENT PRIMARY KEY,title VARCHAR(255) NOT NULL DEFAULT '',"
        . 'created DATETIME NULL,created_by INT DEFAULT 0,modified DATETIME NULL,modified_by INT DEFAULT 0) ENGINE=InnoDB')->execute();
    $db->setQuery("CREATE TABLE t_jem_categories(id INT PRIMARY KEY,alias VARCHAR(100),catname VARCHAR(100)) ENGINE=InnoDB")->execute();
    $db->setQuery("INSERT INTO t_jem_categories VALUES(1,'root','root'),(2,'two','2xyz'),(3,'three','3'),(4,'four','4'),(5,'five','5')")->execute();
    require $source . '/admin/models/import.php';

    // Run the exact transaction, summary and error handling block from the current controller.
    // Upload/authentication and the form's restore-mode restriction are outside this fixture.
    $controller = file_get_contents($source . '/admin/controllers/import.php');
    $start = strpos($controller, '            // database update');
    $end = strpos($controller, "        } else {\r\n            parent::display();", $start);
    if ($end === false) {
        $end = strpos($controller, "        } else {\n            parent::display();", $start);
    }
    if ($start === false || $end === false) {
        throw new RuntimeException('Cannot identify controller transaction/summary block.');
    }
    $block = substr($controller, $start, $end - $start);
    $runner = eval('use Joomla\\CMS\\Factory; use Joomla\\CMS\\Language\\Text; use Joomla\\CMS\\Log\\Log; '
        . 'return function ($model, $fields, $records, $replace, $type) {'
        . '$msg=""; $logKey="probe"; $file=["name"=>"case.csv"];'
        . $block . 'return $result ?? null; };');
    $sink = new class {
        public array $logs = [];
        public array $redirects = [];
        public array $journal = [];
        public function addImportLogEntry(...$args): void { $this->logs[] = $args; }
        public function setRedirect(...$args): void { $this->redirects[] = $args; }
        public function writeImportRows($key, $model, $rollback, $failure = null): void
        {
            $this->journal[] = [$rollback, $model->getImportJournal()->drain($failure)];
        }
    };
    $runner = $runner->bindTo($sink, $sink);
    $read = static fn(): array => $db->setQuery('SELECT id,catid,itemid,ordering FROM t_jem_cats_event_relations ORDER BY itemid,catid')->loadAssocList();
    $readEvents = static fn(): array => $db->setQuery('SELECT * FROM t_jem_events ORDER BY id')->loadAssocList();

    $cases = [
        ['new', [], [[2,101,0]]],
        ['update', [[2,101,0]], [[2,101,7]]],
        ['unchanged', [[2,101,0]], [[2,101,0]]],
        ['two_events', [], [[2,101,0],[3,102,1]]],
        ['replace_category_set', [[2,101,0],[4,101,0],[3,102,0]], [[2,101,4],[5,101,1]]],
        ['duplicate_csv_pair', [], [[2,101,0],[2,101,9]]],
        ['leading_zero_pair', [], [['02','00101',0],[2,101,9]]],
        ['trimmed_pair', [], [[' 2 ',' 101 ',0]]],
        ['missing_ordering', [], [[2,101]], ['catid','itemid']],
        ['reordered_headers', [], [[0,101,2]], ['ordering','itemid','catid']],
        ['empty', [[2,101,0]], []],
        ['missing_category_header', [[2,101,0]], [[101,0]], ['itemid','ordering']],
        ['missing_event_header', [[2,101,0]], [[2,0]], ['catid','ordering']],
        ['missing_event_record', [[2,101,0]], [[3,101,1],[2,999999,0]]],
        ['missing_category_record', [[2,101,0]], [[3,101,1],[999999,102,0]]],
        ['replace_with_missing_category', [[2,101,0]], [[999999,101,0]]],
        ['bounded_errors', [[2,101,0]], [['2x',101,0],['2y',101,0],['2z',101,0],['2w',101,0]]],
        ['late_sql_error', [[2,101,0]], [[3,101,1],[5,102,0]]],
        ['query_chunking', [], array_map(static fn(int $id): array => [2,$id,0], range(1000,1500))],
        ['query_chunking_missing', [[2,101,0]], [[2,101,0], ...array_map(static fn(int $id): array => [2,$id,0], range(1000,1501))]],
    ];
    foreach (['numeric_prefix'=>'2xyz','decimal'=>'2.9','exponent'=>'2e0','negative'=>'-2','zero'=>'0',
        'empty'=>'','plus'=>'+2','overflow'=>'2147483648','huge'=>'9999999999999999999999999999',
        'html'=>'<b>2</b>','nul'=>"2\0",'text'=>'xyz'] as $name => $value) {
        foreach (['catid','itemid'] as $field) {
            $cases[] = ['invalid_' . $field . '_' . $name, [[2,101,0]],
                [[3,101,1], $field === 'catid' ? [$value,102,0] : [2,$value,0]]];
        }
    }
    foreach ([
        ['event_new_id', ['title','categories'], [['New generated event','2']], false],
        ['event_preserved_id', ['id','title','categories'], [[808,'Preserved event','2']], true],
        ['event_existing_id', ['id','title','categories'], [[101,'Updated event','3']], true],
        ['event_missing_category', ['title','categories'], [['Rolled back event','999999']], false],
        ['event_invalid_category', ['title','categories'], [['Rolled back event','2xyz']], false],
        ['event_html_category', ['title','categories'], [['Rolled back event','<b>2</b>']], false],
    ] as [$name,$fields,$rows,$replace]) {
        $cases[] = [$name, [[2,101,0]], $rows, $fields, 'events', $replace];
    }
    $report = [];
    foreach ($cases as $case) {
        [$name,$seed,$rows] = $case;
        $fields = $case[3] ?? ['catid','itemid','ordering'];
        $type = $case[4] ?? 'catevents';
        $replace = $case[5] ?? true;
        $db->setQuery('TRUNCATE TABLE t_jem_cats_event_relations')->execute();
        $db->setQuery('TRUNCATE TABLE t_jem_events')->execute();
        $db->setQuery("INSERT INTO t_jem_events(id,title) VALUES(101,'Original A'),(102,'Original B')")->execute();
        if (str_starts_with($name, 'query_chunking')) {
            $db->setQuery('INSERT INTO t_jem_events(id) VALUES(' . implode('),(', range(1000,1500)) . ')')->execute();
        }
        foreach ($seed as $row) {
            $db->setQuery('INSERT INTO t_jem_cats_event_relations(catid,itemid,ordering) VALUES(' . implode(',', $row) . ')')->execute();
        }
        $before = $read();
        $eventsBefore = $readEvents();
        $handle = fopen('php://temp', 'w+');
        fputcsv($handle, $fields, ';', '"', '');
        foreach ($rows as $row) {
            fputcsv($handle, $row, ';', '"', '');
        }
        rewind($handle);
        $fields = fgetcsv($handle, null, ';', '"', '');
        $records = [];
        while (($row = fgetcsv($handle, null, ';', '"', '')) !== false) {
            $row['_jem_source_line'] = count($records) + 2;
            $records[] = $row;
        }
        fclose($handle);
        if ($name === 'late_sql_error') {
            $server->query('CREATE TRIGGER ' . $database . '.probe_failure BEFORE INSERT ON ' . $database
                . ".t_jem_cats_event_relations FOR EACH ROW BEGIN IF NEW.itemid=102 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='2474 injected SQL failure'; END IF; END");
        }
        $model = (new ReflectionClass(JemModelImport::class))->newInstanceWithoutConstructor();
        $model->setDatabase($db);
        $sink->logs = $sink->redirects = $sink->journal = [];
        $warnings = [];
        set_error_handler(static function ($severity, $message) use (&$warnings): bool {
            if (error_reporting() & $severity && in_array($severity, [E_WARNING,E_NOTICE,E_USER_WARNING,E_USER_NOTICE], true)) {
                $warnings[] = [$severity,$message];
            }
            return true;
        });
        ob_start();
        try {
            $result = $runner($model, $fields, $records, $replace, $type);
        } finally {
            $output = ob_get_clean();
            restore_error_handler();
            if ($name === 'late_sql_error') {
                $server->query('DROP TRIGGER ' . $database . '.probe_failure');
            }
        }
        $report[$name] = [
            'result'=>$result,'before'=>$before,'after'=>$read(),'events_before'=>$eventsBefore,'events_after'=>$readEvents(),
            'warnings'=>$warnings,'redirects'=>$sink->redirects,'logs'=>$sink->logs,'journal'=>$sink->journal,'output'=>$output,
        ];
    }
    $model = (new ReflectionClass(JemModelImport::class))->newInstanceWithoutConstructor();
    $emptyData = [];
    $emptyRelations = $model->cateventsimport(['catid','itemid'], $emptyData);
    $emptyTypes = $model->typesimport([], $emptyData);
    $method = new ReflectionMethod($model, 'normaliseRelationId');
    $nonCsv = [];
    foreach ([true,false,2.0,[],null] as $value) {
        try {
            $method->invoke($model, $value, 'catid');
            $nonCsv[] = false;
        } catch (RuntimeException $error) {
            $nonCsv[] = true;
        }
    }
    echo json_encode(['cases'=>$report,'empty_relations'=>$emptyRelations,'empty_types'=>$emptyTypes,
        'non_csv_invalid_rejected'=>$nonCsv,'category_name'=>$db->setQuery('SELECT catname FROM t_jem_categories WHERE id=2')->loadResult()],
        JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
} finally {
    $db?->disconnect();
    if (preg_match('/^jem_relations_[a-f0-9]{16}$/D', $database)) {
        $server->query('DROP DATABASE ' . $database);
    }
    $server->close();
}
