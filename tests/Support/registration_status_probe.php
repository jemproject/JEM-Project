<?php
/** Exercise the real shared model and template against an isolated database, never the site data. */
declare(strict_types=1);

define('_JEXEC', 1);
$source = dirname(__DIR__, 2);
$joomla = $argv[1] ?? 'C:/xampp/htdocs/jl602';
define('JPATH_SITE', $joomla);
define('JPATH_ROOT', $joomla);
define('JPATH_BASE', $joomla);
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
require $source . '/site/models/registrationstatus.php';

class JemFactory
{
    public static object $user;
    public static function getUser(): object { return self::$user; }
}
class JemHelper
{
    public static function config(): object { return (object) ['weekdaystart' => 1]; }
}
class JemHelperBackend
{
    public static function canManage($action): bool { return JemFactory::$user->manage; }
}
$config = new JConfig();
$server = new mysqli($config->host, $config->user, $config->password);
$database = 'jem_rs_' . bin2hex(random_bytes(7));
$server->query('CREATE DATABASE ' . $database . ' CHARACTER SET utf8mb4');
$checks = 0;
function checkStatus(bool $condition, string $label): void
{
    global $checks;
    ++$checks;
    if (!$condition) { throw new RuntimeException($label); }
}
try {
    $db = new Joomla\Database\Mysqli\MysqliDriver(['host' => $config->host, 'user' => $config->user,
        'password' => $config->password, 'database' => $database, 'prefix' => 't_']);
    preg_match_all('/CREATE TABLE IF NOT EXISTS `#__jem_[^`]+`.*?ENGINE=InnoDB;/s', file_get_contents($source . '/admin/sql/install.mysql.utf8.sql'), $schemas);
    foreach ($schemas[0] as $schema) { $db->setQuery($schema)->execute(); }
    $container = new Joomla\DI\Container();
    $container->set('DatabaseDriver', $db);
    $container->alias(Joomla\Database\DatabaseInterface::class, 'DatabaseDriver');
    (new ReflectionProperty(Joomla\CMS\Factory::class, 'container'))->setValue(null, $container);
    JemFactory::$user = new class {
        public int $id = 42;
        public bool $manage = true;
        public bool $history = true;
        public array $allowed = [1, 2, 3, 4, 5, 6];
        public function get($key) { return $this->$key; }
        public function can($action, $type, $id, ...$rest): bool { return in_array($id, $this->allowed, true); }
        public function authorise($action, $component): bool { return $action === 'jem.registrations.history' && $this->history; }
    };
    $app = new class {
        public bool $backend = false;
        public object $input;
        public array $options = [];
        public function isClient($client): bool { return $client === 'administrator' && $this->backend; }
        public function get($key, $default = null) { return $key === 'offset' ? 'Europe/Madrid' : $default; }
        public function getParams(): object { return new Joomla\Registry\Registry($this->options); }
        public function getLanguage(): object { return Joomla\CMS\Factory::$language; }
        public function bootComponent(string $option): object { return new stdClass(); }
    };
    $app->input = new Joomla\Input\Input(['rs_mode' => 'all']);
    Joomla\CMS\Factory::$application = $app;
    Joomla\CMS\Factory::$language = new Joomla\CMS\Language\Language('en-GB');
    $insert = static function (string $table, array $values) use ($db): void {
        if ($table === 'events') {
            $values += ['introtext' => '', 'fulltext' => '', 'created_by_alias' => '', 'metadata' => '{}'];
        }
        $row = (object) $values; $db->insertObject('#__jem_' . $table, $row);
    };
    $insert('venues', ['id' => 1, 'venue' => 'Madrid Venue', 'country' => 'ES', 'region' => 'Community of Madrid', 'state' => 'Madrid', 'city' => 'Madrid']);
    $insert('venues', ['id' => 2, 'venue' => 'Berlin Venue', 'country' => 'DE', 'region' => 'Berlin', 'state' => 'Berlin', 'city' => 'Berlin']);
    $insert('venues', ['id' => 3, 'venue' => 'Private Venue', 'country' => 'XX', 'region' => 'Private', 'state' => 'Private', 'city' => 'Private']);
    $snapshot = json_encode(['schema' => 'jem-venue-capacity/v1', 'spaces' => [[
        'name' => 'Main room', 'layout' => ['id' => 1, 'capacity' => 70], 'capacity_areas' => [
            ['id' => 1, 'name' => 'Front', 'capacity' => 40, 'published' => 1],
            ['id' => 2, 'name' => 'Back', 'capacity' => 30, 'published' => 1],
        ],
    ]]]);
    foreach ([
        [1, 'Programme', 0, '2025-01-01', 1, 'classic', 0, 0],
        [2, 'Area event', 1, '2099-11-15', 1, 'areas', 50, 2],
        [3, 'Berlin event', 1, '2099-11-16', 2, 'classic', 20, 0],
        [4, 'Online event', 0, '2099-11-17', 0, 'classic', 0, 0],
        [5, 'Online priced event', 0, '2099-11-18', 0, 'classic', 0, 0],
        [6, 'Archived', 0, '2025-01-01', 1, 'classic', 20, 0],
        [9, 'Private event', 0, '2099-11-17', 3, 'classic', 0, 0],
    ] as [$id, $title, $parent, $date, $venue, $mode, $capacity, $reserved]) {
        $insert('events', ['id' => $id, 'title' => $title, 'alias' => 'rs-' . $id, 'dates' => $date,
            'published' => $id === 6 ? 2 : 1, 'parent_event_id' => $parent ?: null, 'created_by' => 42,
            'locid' => $venue, 'registra' => $id === 1 ? 0 : 1, 'capacity_mode' => $mode,
            'maxplaces' => $capacity, 'reservedplaces' => $reserved, 'venue_snapshot' => $mode === 'areas' ? $snapshot : null,
            'pricing_mode' => $id === 5 ? 'multiple' : 'classic']);
    }
    foreach ([[1, 2, 1, 0, 40], [2, 2, 1, 1, 3], [3, 2, -1, 0, 8], [4, 3, 1, 0, 5], [5, 4, 1, 0, 2], [6, 5, 1, 0, 3]] as [$id, $event, $status, $waiting, $places]) {
        $insert('register', ['id' => $id, 'event' => $event, 'uid' => $id, 'status' => $status, 'waiting' => $waiting,
            'places' => $places, 'revision' => 2, 'reference' => 'R-' . str_pad((string) $id, 26, '0', STR_PAD_LEFT)]);
    }
    foreach ([[1, 2, 1, 25], [1, 2, 2, 15], [1, 1, 1, 39], [2, 2, 1, 3], [3, 2, 2, 8]] as [$registration, $revision, $area, $quantity]) {
        $insert('register_capacity_allocations', ['register_id' => $registration, 'registration_revision' => $revision,
            'event_id' => 2, 'venue_capacity_area_id' => $area, 'venue_layout_id' => 1, 'area_code' => 'area-' . $area,
            'quantity' => $quantity]);
    }
    foreach ([[1, 1, 1, 4, 2, 'places_changed'], [2, 1, -1, 8, 8, 'cancelled'], [3, 0, -1, 2, 0, 'cancelled']] as [$id, $old, $new, $before, $after, $action]) {
        $insert('register_history', ['id' => $id, 'operation_reference' => 'H' . $id, 'registration_id' => $id,
            'registration_reference' => 'R' . $id, 'revision' => 1, 'event_id' => 2, 'action' => $action,
            'old_status' => $old, 'new_status' => $new, 'old_places' => $before, 'new_places' => $after, 'occurred' => '2026-11-10 10:00:00']);
    }
    $insert('capacity_pools', ['id' => 1, 'event_id' => 5, 'code' => 'online', 'name' => 'Online places', 'capacity' => 10]);
    foreach ([1 => 8, 2 => 3] as $revision => $quantity) {
        $insert('register_items', ['register_id' => 6, 'registration_revision' => $revision, 'line_number' => 1,
            'line_kind' => 'admission', 'item_name' => 'Online', 'currency' => 'EUR', 'quantity' => $quantity, 'capacity_pool_id' => 1]);
    }
    $model = new JemModelRegistrationstatus(['dbo' => $db, 'ignore_request' => true]);
    $report = $model->getReport();
    $rows = array_column($report['rows'], null, 'key');
    checkStatus(!isset($rows['event-9']), 'Unauthorised event leaked');
    checkStatus(!isset($report['choices']['country']['XX']), 'Unauthorised filter value leaked');
    checkStatus($rows['event-2']['totals']['confirmed'] === 40, 'Confirmed places');
    checkStatus($rows['event-2']['totals']['waiting'] === 3, 'Waiting places');
    checkStatus($rows['event-2']['totals']['free'] === 8, 'Global capacity and reserved places');
    checkStatus($rows['event-2']['totals']['cancelled'] === 10, 'Partial and full cancellation history');
    checkStatus(array_column($rows['event-2']['totals']['pools'], 'confirmed') === [25, 15], 'Old revision or cancelled allocation counted');
    checkStatus(array_column($rows['event-2']['totals']['pools'], 'free') === [8, 8], 'Shared capacity limit');
    checkStatus($rows['event-1']['totals']['confirmed'] === 45, 'Programme aggregation');
    checkStatus(isset($rows['event-2-space-1']), 'Room hierarchy');
    checkStatus($rows['event-4']['totals']['free'] === null, 'Online unlimited capacity');
    checkStatus($rows['event-5']['totals']['free'] === 7, 'Priced pool current revision');
    $app->input = new Joomla\Input\Input(['rs_mode' => 'all', 'rs_country' => 'ES']);
    $filtered = $model->getReport();
    checkStatus(!isset($filtered['choices']['city']['Berlin']), 'Location cascade');
    checkStatus(count(array_filter($filtered['rows'], static fn ($r) => $r['key'] === 'event-3')) === 0, 'Country filter ignored');
    $app->options = ['show_country_filter' => 0, 'default_country' => 'ES'];
    $app->input = new Joomla\Input\Input(['rs_mode' => 'all', 'rs_country' => 'DE']);
    checkStatus($model->getReport()['filters']['country'] === 'ES', 'Hidden default bypass');
    $app->options = [];
    $app->input = new Joomla\Input\Input([]);
    $upcoming = array_column($model->getReport()['rows'], null, 'key');
    checkStatus($upcoming['event-1']['context'], 'Past parent context missing');
    $app->input = new Joomla\Input\Input(['rs_mode' => 'all', 'rs_publication' => '2']);
    checkStatus(array_column($model->getReport()['rows'], 'key') === ['event-6'], 'Archived filter');
    $app->input = new Joomla\Input\Input(['rs_history' => 9]);
    try { $model->getReport(); throw new RuntimeException('History leaked'); }
    catch (RuntimeException $error) { checkStatus($error->getCode() === 403, 'Scoped history denial'); }
    $app->input = new Joomla\Input\Input(['rs_history' => 2]);
    JemFactory::$user->history = false;
    try { $model->getReport(); throw new RuntimeException('History ACL leaked'); }
    catch (RuntimeException $error) { checkStatus($error->getCode() === 403, 'History permission'); }
    JemFactory::$user->history = true;
    checkStatus(count($model->getReport()['history']) === 3, 'Permitted history');
    $app->input = new Joomla\Input\Input(['rs_mode' => 'all']);
    $app->backend = true;
    JemFactory::$user->manage = false;
    try { $model->getReport(); throw new RuntimeException('Backend ACL leaked'); }
    catch (RuntimeException $error) { checkStatus($error->getCode() === 403, 'Backend permission'); }
    JemFactory::$user->manage = true;
    checkStatus(count($model->getReport()['rows']) > count($report['rows']), 'Backend manager scope');
    $app->backend = false;
    $app->options = ['status_page_size' => 10];
    for ($id = 20; $id < 45; ++$id) {
        $insert('events', ['id' => $id, 'title' => 'Paging ' . $id, 'published' => 1, 'dates' => '2099-12-01']);
        JemFactory::$user->allowed[] = $id;
    }
    $app->input = new Joomla\Input\Input(['rs_mode' => 'all', 'rs_page' => 2]);
    $paged = $model->getReport();
    checkStatus($paged['page'] === 2 && $paged['pages'] === 3, 'Group pagination');
    checkStatus(count(array_filter($paged['rows'], static fn ($row) => $row['parent'] === '')) === 10, 'Complete groups on each page');
    JemFactory::$user->allowed = [2];
    $app->input = new Joomla\Input\Input(['rs_mode' => 'all']);
    $restricted = $model->getReport();
    checkStatus(!in_array('event-1', array_column($restricted['rows'], 'key'), true), 'Hidden parent metadata leaked');
    checkStatus($restricted['rows'][0]['key'] === 'event-2' && $restricted['rows'][0]['parent'] === '', 'Permitted child without parent');
    JemFactory::$user->id = 0;
    try { $model->getReport(); throw new RuntimeException('Guest ACL leaked'); }
    catch (RuntimeException $error) { checkStatus($error->getCode() === 403, 'Guest permission'); }
    echo json_encode(['checks' => $checks, 'database' => 'isolated temporary database', 'result' => 'PASS'], JSON_THROW_ON_ERROR) . PHP_EOL;
} finally {
    if (isset($db)) { $db->disconnect(); }
    if (!preg_match('/^jem_rs_[a-f0-9]{14}$/D', $database)) { throw new RuntimeException('Unexpected fixture database name'); }
    $server->query('DROP DATABASE ' . $database);
    $server->close();
}
