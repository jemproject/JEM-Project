<?php
/** Real SQL/locks/PDF integration, isolated database, no site data or email transport. */
declare(strict_types=1);
define('_JEXEC', 1);
$source = dirname(__DIR__, 2);
$joomla = $argv[1] ?? 'C:/xampp/htdocs/jl602';
define('JPATH_SITE', $joomla);
define('JPATH_ROOT', $joomla);
define('JPATH_BASE', $joomla);
define('JPATH_COMPONENT_SITE', $source . '/site');
define('JPATH_ADMINISTRATOR', $joomla . '/administrator');
define('JPATH_CACHE', sys_get_temp_dir());
require $joomla . '/libraries/vendor/autoload.php';
spl_autoload_register(static function (string $class) use ($joomla): void {
    if (str_starts_with($class, 'Joomla\\CMS\\')) {
        $file = $joomla . '/libraries/src/' . str_replace('\\', '/', substr($class, 11)) . '.php';
        if (is_file($file)) { require_once $file; }
    }
});
require $joomla . '/configuration.php';
require $source . '/site/classes/ticketservice.class.php';
require $source . '/site/classes/registrationservice.class.php';
require $source . '/site/classes/ticketpdf.class.php';
$config = new JConfig();
$connect = static fn ($name) => new Joomla\Database\Mysqli\MysqliDriver(['host' => $config->host, 'user' => $config->user,
    'password' => $config->password, 'database' => $name, 'prefix' => 't_']);
$operator = new class {
    public int $id = 42;
    public array $rights = ['pay', 'admit'];
    public function authorise($action, $asset): bool {
        return $asset === 'com_jem.category.2' && in_array(str_replace('jem.tickets.', '', $action), $this->rights, true);
    }
};
// Worker mode exercises independent database connections and real row locks.
if (($argv[2] ?? '') === 'worker') {
    $database = $argv[3];
    if (!preg_match('/^jem_ticket_[a-f0-9]{14}$/D', $database)) { exit(2); }
    $db = $connect($database);
    try {
        $result = (new JemTicketService($db))->execute('admission', json_decode(base64_decode($argv[4]), true), $operator);
        echo json_encode(['ok' => true, 'duplicate' => $result['duplicate']]);
    } catch (DomainException $error) { echo json_encode(['ok' => false, 'error' => $error->getMessage()]); }
    exit;
}
$server = new mysqli($config->host, $config->user, $config->password);
$database = 'jem_ticket_' . bin2hex(random_bytes(7));
$server->query('CREATE DATABASE ' . $database . ' CHARACTER SET utf8mb4');
$checks = 0;
$check = static function (bool $yes, string $name) use (&$checks): void { ++$checks; if (!$yes) { throw new RuntimeException($name); } };
$reject = static function (callable $call, string $key) use ($check): void {
    try { $call(); throw new RuntimeException('Expected rejection: ' . $key); }
    catch (DomainException | InvalidArgumentException $error) { $check($error->getMessage() === $key, 'Unexpected rejection: ' . $error->getMessage()); }
};
try {
    $db = $connect($database);
    preg_match_all('/CREATE TABLE IF NOT EXISTS `#__jem_[^`]+`.*?ENGINE=InnoDB;/s', file_get_contents($source . '/admin/sql/install.mysql.utf8.sql'), $schemas);
    foreach ($schemas[0] as $schema) { $db->setQuery($schema)->execute(); }
    $db->setQuery('CREATE TABLE t_users (id INT PRIMARY KEY,name VARCHAR(100),email VARCHAR(100),block INT DEFAULT 0,activation VARCHAR(100) DEFAULT NULL)')->execute();
    $db->setQuery("INSERT INTO t_users(id,name,email) VALUES (42,'QA owner','qa@example.invalid'),(43,'Other','other@example.invalid')")->execute();
    $db->setQuery("INSERT INTO #__jem_config(keyname,value) VALUES ('registration_schema_ready','1')")->execute();
    $container = new Joomla\DI\Container();
    $container->set('DatabaseDriver', $db);
    $container->alias(Joomla\Database\DatabaseInterface::class, 'DatabaseDriver');
    (new ReflectionProperty(Joomla\CMS\Factory::class, 'container'))->setValue(null, $container);
    Joomla\CMS\Factory::$language = new Joomla\CMS\Language\Language('en-GB');
    Joomla\CMS\Factory::$language->load('com_jem', $source . '/site', 'en-GB', true);
    Joomla\CMS\Factory::$application = new class {
        public function get($key, $default = null) { return $default; }
        public function getLanguage() { return Joomla\CMS\Factory::$language; }
    };
    $event = (object) ['id' => 1, 'title' => 'QA Tickets <safe>', 'published' => 1, 'event_status' => 'scheduled',
        'dates' => '2026-11-10', 'times' => '09:30:00', 'introtext' => '', 'fulltext' => '', 'created_by_alias' => '', 'metadata' => '{}'];
    $db->insertObject('#__jem_events', $event);
    $db->setQuery('INSERT INTO #__jem_cats_event_relations(catid,itemid) VALUES(2,1)')->execute();
    $service = new JemTicketService($db);
    $make = static function (int $id, array $values = []) use ($db): object {
        $row = (object) ($values + ['id' => $id, 'event' => 1, 'uid' => $id + 40, 'places' => 3, 'status' => 1,
            'waiting' => 0, 'reference' => JemRegistrationIdentity::generateRegistrationReference(), 'pricing_mode' => 'classic',
            'revision' => 1, 'currency' => '', 'grand_total' => null]);
        $db->insertObject('#__jem_register', $row);
        return $row;
    };
    $input = static fn ($snapshot, $more = []) => $more + ['reference' => $snapshot['reference'], 'event_id' => $snapshot['event_id'],
        'operation' => $snapshot['operation'], 'version' => $snapshot['version']];
    $free = $make(2);
    $snapshot = $service->lookup($free->reference, 1, $operator);
    $check($snapshot['blocked'] === '' && $snapshot['payment']['state'] === 'free', 'Classic free online event');
    $reject(fn () => $service->lookup($free->reference, 2, $operator), 'JERROR_ALERTNOAUTHOR');
    $denied = clone $operator; $denied->rights = [];
    $reject(fn () => $service->lookup($free->reference, 1, $denied), 'JERROR_ALERTNOAUTHOR');
    $reject(fn () => $service->document($free->reference, 1, $denied = new class { public int $id=43; public function authorise(...$args) {return false;} }), 'COM_JEM_TK_NOT_FOUND');
    $gate = clone $operator; $gate->rights = ['admit'];
    $cashier = clone $operator; $cashier->rights = ['pay'];
    $reject(fn () => $service->execute('payment', $input($snapshot), $gate), 'JERROR_ALERTNOAUTHOR');
    $reject(fn () => $service->execute('admission', $input($snapshot), $cashier), 'JERROR_ALERTNOAUTHOR');
    $admit = $input($snapshot, ['quantity' => '1', 'pool' => 'general']);
    $one = $service->execute('admission', $admit, $gate);
    $check($one['entered'] === 1 && $one['remaining'] === 2, 'Partial group admission');
    $check($service->execute('admission', $admit, $gate)['duplicate'], 'Idempotent retry');
    $reject(fn () => $service->execute('admission', array_replace($admit, ['quantity' => '2']), $gate), 'COM_JEM_TK_INVALID_OPERATION');
    $reject(fn () => $service->execute('admission', array_replace($admit, ['operation' => JemRegistrationIdentity::generateOperationReference()]), $gate), 'COM_JEM_TK_STALE');
    $reject(fn () => $service->execute('admission', $input($one, ['quantity' => '3', 'pool' => 'general']), $gate), 'COM_JEM_TK_INVALID_QUANTITY');
    $done = $service->execute('admission', $input($one, ['quantity' => '2', 'pool' => 'general']), $gate);
    $check($done['blocked'] === 'COM_JEM_TK_USED', 'Exhausted locator');
    $reject(fn () => $service->execute('admission', $input($done, ['quantity' => '1', 'pool' => 'general']), $gate), 'COM_JEM_TK_USED');

    $priced = $make(3, ['pricing_mode' => 'priced', 'grand_total' => '30.00', 'currency' => 'EUR']);
    $snapshot = $service->lookup($priced->reference, 1, $operator);
    $check($snapshot['blocked'] === 'COM_JEM_TK_PAYMENT_REQUIRED', 'Unpaid entry blocked');
    $payment = $input($snapshot, ['amount' => '30.00', 'currency' => 'EUR', 'method' => 'cash', 'receipt' => 'Desk 1']);
    $reject(fn () => $service->execute('payment', array_replace($payment, ['amount' => '29.99']), $cashier), 'COM_JEM_TK_INVALID_AMOUNT');
    $reject(fn () => $service->execute('payment', array_replace($payment, ['currency' => 'USD']), $cashier), 'COM_JEM_TK_INVALID_AMOUNT');
    $reject(fn () => $service->execute('payment', array_replace($payment, ['method' => 'bank_transfer', 'receipt' => '']), $cashier), 'COM_JEM_TK_RECEIPT_REQUIRED');
    $paid = $service->execute('payment', $payment, $cashier);
    $check($paid['payment']['paid'] === 3000 && $paid['blocked'] === '', 'Manual collection unlocks entry');
    $check($service->execute('payment', $payment, $cashier)['duplicate'], 'Payment retry does not collect twice');
    $check($paid['reference'] === $snapshot['reference'], 'Stable QR after payment');
    $row = $db->setQuery('SELECT * FROM #__jem_register WHERE id=3')->loadObject();
    $after = clone $row; $after->places = 2; $after->grand_total = '20.00';
    $service->reconcile($row, $after);
    $check($after->payment_state === 'refund_pending', 'Reduction leaves actual refund pending');
    $db->updateObject('#__jem_register', $after, 'id');
    $line = (object) ['register_id' => 3, 'registration_revision' => 1, 'line_number' => 1,
        'line_kind' => 'admission', 'quantity' => 2, 'currency' => 'EUR', 'line_gross' => '20.00', 'item_name' => 'Online'];
    $db->insertObject('#__jem_register_items', $line);
    $remaining = $service->lookup($priced->reference, 1, $operator);
    $check($remaining['blocked'] === '' && $remaining['payment']['refund'] === 1000, 'Paid remaining places still enter');
    $service->execute('admission', $input($remaining, ['quantity' => '1', 'pool' => 'pool:0']), $gate);
    $writer = new JemRegistrationService($db);
    $writer->cancelByIds([3], 1, ['actorId' => 42, 'source' => 'ticket-test']);
    $cancelled = $service->lookup($priced->reference, 1, $operator);
    $check($cancelled['blocked'] === 'COM_JEM_TK_NOT_CONFIRMED' && $cancelled['payment']['refund'] === 3000, 'Actual writer cancellation preserves paid audit');
    $check($cancelled['entered'] === 1, 'Cancellation retains previous admission');
    $before = $db->setQuery('SELECT * FROM #__jem_register WHERE id=3')->loadObject();
    $reactivated = clone $before; $reactivated->status = 1;
    $reject(fn () => $service->reconcile($before, $reactivated), 'COM_JEM_TK_SETTLEMENT_REVIEW');
    $pending = clone $before; $pending->status = 1; $pending->grand_total = '10.00';
    $increased = clone $pending; $increased->grand_total = '20.00';
    $reject(fn () => $service->reconcile($pending, $increased), 'COM_JEM_TK_SETTLEMENT_REVIEW');
    $unknown = $make(10, ['payment_state' => 'paid', 'grand_total' => '10.00', 'pricing_mode' => 'priced', 'currency' => 'EUR']);
    $reject(fn () => $service->lookup($unknown->reference, 1, $operator), 'COM_JEM_TK_SETTLEMENT_REVIEW');

    $reject(fn () => $service->execute('admission', $input($cancelled, ['quantity' => '1', 'pool' => 'general']), $gate), 'COM_JEM_TK_NOT_CONFIRMED');
    $reject(fn () => $service->document($priced->reference, 1, $operator), 'COM_JEM_TK_NOT_CONFIRMED');
    foreach ([['status' => -1], ['status' => 0], ['waiting' => 1]] as $n => $state) {
        $invalid = $make(5 + $n, $state);
        $check($service->lookup($invalid->reference, 1, $operator)['blocked'] === 'COM_JEM_TK_NOT_CONFIRMED', 'Inactive reservation ' . $n);
    }
    $area = $make(8, ['places' => 4]);
    foreach ([101, 102] as $areaId) {
        $a = (object) ['register_id' => 8, 'registration_revision' => 1, 'event_id' => 1, 'venue_capacity_area_id' => $areaId,
            'venue_layout_id' => 1, 'area_code' => (string) $areaId, 'area_name' => 'Area ' . $areaId, 'quantity' => 2];
        $db->insertObject('#__jem_register_capacity_allocations', $a);
    }
    $areaState = $service->lookup($area->reference, 1, $operator);
    $areaState = $service->execute('admission', $input($areaState, ['quantity' => '2', 'pool' => 'area:101']), $gate);
    $check($areaState['pools']['area:101']['remaining'] === 0 && $areaState['pools']['area:102']['remaining'] === 2, 'Independent physical areas');
    $reject(fn () => $service->execute('admission', $input($areaState, ['quantity' => '1', 'pool' => 'area:101']), $gate), 'COM_JEM_TK_INVALID_QUANTITY');
    $document = JemTicketPdf::render($service->document($area->reference, 1, $operator));
    $check(str_starts_with($document, '%PDF-') && strlen($document) > 5000, 'Real TCPDF QR document');
    file_put_contents(sys_get_temp_dir() . '/jem-ticket-probe.pdf', $document);
    $db->setQuery("UPDATE #__jem_events SET event_status='cancelled' WHERE id=1")->execute();
    $check($service->lookup($area->reference, 1, $operator)['blocked'] === 'COM_JEM_TK_EVENT_CLOSED', 'Cancelled event blocks valid reservation');
    $db->setQuery("UPDATE #__jem_events SET event_status='scheduled' WHERE id=1")->execute();

    // Two simultaneous readers: only one can consume the last place.
    $concurrent = $make(9, ['places' => 1]);
    $state = $service->lookup($concurrent->reference, 1, $operator);
    $workers = [];
    $db->transactionStart(); $db->setQuery('SELECT id FROM #__jem_events WHERE id=1 FOR UPDATE')->loadResult();
    foreach ([0, 1] as $n) {
        $request = $input($state, ['quantity' => '1', 'pool' => 'general', 'operation' => JemRegistrationIdentity::generateOperationReference()]);
        $pipes = [];
        $process = proc_open([PHP_BINARY, __FILE__, $joomla, 'worker', $database, base64_encode(json_encode($request))],
            [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']], $pipes);
        fclose($pipes[0]); $workers[] = [$process, $pipes];
    }
    usleep(250000); $db->transactionCommit();
    $success = 0;
    foreach ($workers as [$process, $pipes]) {
        $output = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]); $code = proc_close($process);
        $check($code === 0 && $errors === '', 'Worker failure: ' . $errors);
        $result = json_decode($output, true, 512, JSON_THROW_ON_ERROR); $success += (int) $result['ok'];
    }
    $check($success === 1 && $service->lookup($concurrent->reference, 1, $operator)['entered'] === 1, 'Concurrent readers cannot over-admit');
    $count = (int) $db->setQuery('SELECT COUNT(*) FROM #__jem_ticket_operations WHERE registration_id=3')->loadResult();
    $db->setQuery('DELETE FROM #__jem_register WHERE id=3')->execute();
    $check((int) $db->setQuery('SELECT COUNT(*) FROM #__jem_ticket_operations WHERE registration_id=3')->loadResult() === $count, 'Audit survives reservation deletion');
    echo json_encode(['result' => 'PASS', 'checks' => $checks, 'database' => 'isolated', 'concurrency' => 'two real connections']) . PHP_EOL;
} finally {
    if (isset($db)) { $db->disconnect(); }
    if (!preg_match('/^jem_ticket_[a-f0-9]{14}$/D', $database)) { throw new RuntimeException('Invalid fixture database'); }
    $server->query('DROP DATABASE ' . $database); $server->close();
}
