<?php
/** Analysis only: real cancellation model/service, disposable SQL, no installed-site writes. */
declare(strict_types=1);
if (getenv('JEM_TEST_PRICED_CANCELLATION') !== '1') { throw new RuntimeException('Disposable database opt-in required.'); }
define('_JEXEC', 1);
[$script, $joomla, $source] = $argv;
define('JPATH_SITE', $source . '/site');
define('JPATH_BASE', $joomla);
define('JPATH_ROOT', $joomla);
define('JPATH_ADMINISTRATOR', $joomla . '/administrator');
define('JPATH_CACHE', sys_get_temp_dir());
define('JPATH_LANGUAGES', $joomla . '/language');
require $joomla . '/libraries/vendor/autoload.php';
spl_autoload_register(static function (string $class) use ($joomla): void {
    if (str_starts_with($class, 'Joomla\\CMS\\')) {
        $file = $joomla . '/libraries/src/' . str_replace('\\', '/', substr($class, 11)) . '.php';
        if (is_file($file)) { require_once $file; }
    }
});
require $joomla . '/configuration.php';
$config = new JConfig();
$server = new mysqli($config->host, $config->user, $config->password);
$database = 'jem_2489_' . bin2hex(random_bytes(7));
$server->query('CREATE DATABASE ' . $database . ' CHARACTER SET utf8mb4');
$db = null;
$results = [];
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    $checks++;
    if (!$ok) { throw new RuntimeException($message); }
};
class JemFactory {
    public static function getUser(): object { return new Joomla\Registry\Registry(['id' => 42]); }
}
try {
    $db = new Joomla\Database\Mysqli\MysqliDriver([
        'host' => $config->host, 'user' => $config->user, 'password' => $config->password,
        'database' => $database, 'prefix' => 't_',
    ]);
    $container = new Joomla\DI\Container();
    $container->set('DatabaseDriver', $db, true);
    $container->alias(Joomla\Database\DatabaseInterface::class, 'DatabaseDriver');
    $container->alias(Joomla\Database\DatabaseDriver::class, 'DatabaseDriver');
    (new ReflectionProperty(Joomla\CMS\Factory::class, 'container'))->setValue(null, $container);
    Joomla\CMS\Factory::$language = new Joomla\CMS\Language\Language('en-GB');
    $strings = parse_ini_file($source . '/site/language/en-GB/com_jem.ini', false, INI_SCANNER_RAW);
    (new ReflectionProperty(Joomla\CMS\Factory::$language, 'strings'))->setValue(Joomla\CMS\Factory::$language, $strings);
    Joomla\CMS\Factory::$application = new class {
        public $input;
        public function bootComponent($option): object { return new stdClass(); }
        public function getInput(): object { return $this->input ??= new Joomla\Input\Input(); }
        public function get($key, $default = null): mixed { return $key === 'secret' ? 'analysis-only-2489' : $default; }
        public function getLanguage(): object { return Joomla\CMS\Factory::$language; }
    };
    $app = Joomla\CMS\Factory::$application;
    $install = file_get_contents($source . '/admin/sql/install.mysql.utf8.sql');
    foreach (['events', 'register', 'register_items', 'register_capacity_allocations', 'register_history', 'config'] as $table) {
        if (!preg_match('/CREATE TABLE IF NOT EXISTS `#__jem_' . $table . '`.*?ENGINE=InnoDB;/s', $install, $match)) {
            throw new RuntimeException('Missing fixture schema');
        }
        $db->setQuery($match[0])->execute();
    }
    $db->setQuery("INSERT INTO #__jem_config (keyname, value) VALUES ('registration_schema_ready', '1')")->execute();
    require $source . '/site/classes/registrationidentity.class.php';
    require $source . '/site/classes/registrationtransition.class.php';
    require $source . '/site/classes/registrationservice.class.php';
    require $source . '/site/models/event.php';
    $method = new ReflectionMethod(JemModelEvent::class, 'cancelRegistrationResponse');
    $snapshot = static function () use ($db): string {
        $data = [];
        foreach (['register', 'register_items', 'register_capacity_allocations', 'register_history'] as $table) {
            $data[$table] = $db->setQuery('SELECT * FROM #__jem_' . $table . ' ORDER BY id')->loadAssocList();
        }
        return hash('sha256', json_encode($data));
    };
    foreach (['single', 'multiple', 'priced', 'waiting', 'areas', 'partial', 'zero', 'excess', 'no_confirmation',
        'changed_revision', 'series_equal', 'series_unequal', 'classic_full', 'classic_partial', 'event_now_classic', 'external_payment_preserved', 'unpriced_event_now_priced'] as $case) {
        foreach (['events', 'register', 'register_items', 'register_capacity_allocations', 'register_history'] as $table) {
            $db->setQuery('TRUNCATE TABLE #__jem_' . $table)->execute();
        }
        $isPriced = !str_starts_with($case, 'classic_') && $case !== 'unpriced_event_now_priced';
        $mode = in_array($case, ['single', 'multiple', 'priced'], true) ? $case : ($isPriced ? 'multiple' : 'classic');
        $isArea = $case === 'areas';
        $events = $registrations = [];
        foreach (str_starts_with($case, 'series_') ? [11, 12] : [11] as $eventId) {
            $event = (object) ['id' => $eventId, 'title' => 'Test workshop', 'introtext' => '', 'fulltext' => '',
                'metadata' => '{}', 'created_by_alias' => '', 'dates' => '2030-01-01', 'minbookeduser' => 1,
                'maxbookeduser' => 10, 'maxplaces' => 20, 'waitinglist' => 1, 'pricing_mode' => $case === 'event_now_classic' ? 'classic' : ($case === 'unpriced_event_now_priced' ? 'multiple' : $mode),
                'capacity_mode' => $isArea ? 'areas' : 'classic'];
            if ($isArea) {
                $event->venue_snapshot = json_encode(['schema' => 'jem-venue-capacity/v1', 'spaces' => [[
                    'name' => 'Hall', 'code' => 'H', 'layout' => ['id' => 1, 'revision' => 1],
                    'capacity_areas' => [['id' => 1, 'code' => 'A', 'name' => 'A', 'published' => 1, 'capacity' => 10], ['id' => 2, 'code' => 'B', 'name' => 'B', 'published' => 1, 'capacity' => 10]],
                ]]]);
            }
            $db->insertObject('#__jem_events', $event);
            $places = $case === 'series_unequal' && $eventId === 12 ? 6 : 4;
            $registration = (object) ['id' => $eventId + 90, 'event' => $eventId, 'uid' => 42, 'places' => $places,
                'status' => 1, 'waiting' => $case === 'waiting' ? 1 : 0, 'revision' => 1,
                'reference' => JemRegistrationIdentity::generateRegistrationReference(), 'pricing_mode' => $mode,
                'currency' => 'EUR', 'grand_total' => $places * 12, 'payment_state' => $case === 'external_payment_preserved' ? 'paid' : null];
            $db->insertObject('#__jem_register', $registration);
            if ($isPriced) {
                $item = (object) ['register_id' => $registration->id, 'registration_revision' => 1,
                    'line_number' => 1, 'line_kind' => 'admission', 'item_name' => 'Standard',
                    'quantity' => $places, 'currency' => 'EUR', 'unit_gross' => '12.00', 'line_gross' => $places * 12];
                $db->insertObject('#__jem_register_items', $item);
            }
            if ($isArea) {
                $allocation = (object) ['register_id' => $registration->id, 'registration_revision' => 1,
                    'event_id' => $eventId, 'venue_layout_id' => 1, 'venue_layout_revision' => 1,
                    'venue_capacity_area_id' => 1, 'area_code' => 'A', 'area_name' => 'A',
                    'space_name' => 'Hall', 'space_code' => 'H', 'quantity' => 2];
                $db->insertObject('#__jem_register_capacity_allocations', $allocation);
                $allocation->venue_capacity_area_id = 2;
                $allocation->area_code = $allocation->area_name = 'B';
                $db->insertObject('#__jem_register_capacity_allocations', $allocation);
            }
            $events[] = $event;
            $registrations[$eventId] = $db->setQuery('SELECT * FROM #__jem_register WHERE id = ' . $registration->id)->loadObject();
        }
        $before = $snapshot();
        $app->input = new Joomla\Input\Input();
        $model = new JemModelEvent();
        $model->setId(11);
        $quantity = match ($case) { 'partial', 'classic_partial', 'unpriced_event_now_priced' => 1, 'zero' => 0, 'excess' => 5, default => 4 };
        $preview = $method->invoke($model, $events, $registrations, $quantity, true);
        $assert($snapshot() === $before, $case . ': preview wrote data');
        if (in_array($case, ['partial', 'zero', 'excess', 'series_unequal'], true)) {
            $assert($preview === false, $case . ': invalid preview accepted');
            $results[$case] = ['refused' => true, 'error' => $model->getError()];
            continue;
        }
        $assert(is_array($preview) && $preview['required'], $case . ': preview failed: ' . $model->getError());
        if ($case !== 'no_confirmation') {
            $app->input->post->set('jem_cancel_confirm', 1);
            $app->input->post->set('jem_cancel_fingerprint', $preview['fingerprint']);
        }
        if ($case === 'changed_revision') {
            $db->setQuery('UPDATE #__jem_register SET revision = revision + 1 WHERE id = 101')->execute();
            $before = $snapshot();
        }
        $saved = $method->invoke($model, $events, $registrations, $quantity, false);
        if (in_array($case, ['no_confirmation', 'changed_revision'], true)) {
            $assert($saved === false && $snapshot() === $before, $case . ': unexpected write');
            $results[$case] = ['refused' => true];
            continue;
        }
        $assert($saved === 101, $case . ': save failed: ' . $model->getError());
        $after = $db->setQuery('SELECT * FROM #__jem_register WHERE id = 101')->loadObject();
        $assert((int) $after->status === (in_array($case, ['classic_partial', 'unpriced_event_now_priced'], true) ? 1 : -1), $case . ': wrong status');
        $assert((int) $after->places === ($isPriced ? 4 : (in_array($case, ['classic_partial', 'unpriced_event_now_priced'], true) ? 3 : 0)), $case . ': wrong quantity');
        $assert((string) $after->grand_total === '48.00' && $after->payment_state === ($case === 'external_payment_preserved' ? 'paid' : null), $case . ': financial data changed');
        $active = (int) $db->setQuery('SELECT COALESCE(SUM(places), 0) FROM #__jem_register WHERE status = 1 AND waiting = 0')->loadResult();
        $assert($active === (in_array($case, ['classic_partial', 'unpriced_event_now_priced'], true) ? 3 : 0), $case . ': active capacity wrong');
        if ($isPriced) {
            $lines = $db->setQuery('SELECT registration_revision, quantity, line_gross FROM #__jem_register_items WHERE register_id = 101 ORDER BY registration_revision')->loadAssocList();
            $assert(count($lines) === 2 && (int) $lines[0]['quantity'] === 4 && (int) $lines[1]['quantity'] === 4, $case . ': commercial history changed');
            try {
                (new JemRegistrationService($db))->save((object) ['id' => 101, 'event' => 11, 'uid' => 42, 'places' => 4, 'status' => 1]);
                throw new RuntimeException('Reactivation should be refused');
            } catch (RuntimeException $e) {
                $assert(str_contains($e->getMessage(), 'reactivated from a current quote'), $case . ': unexpected reactivation result');
            }
        }
        if ($isArea) {
            $capacity = (new JemRegistrationService($db))->capacityOptions(11);
            $assert(array_column($capacity->options, 'used') === [0, 0], 'Both areas must be free');
        }
        $history = $db->setQuery('SELECT * FROM #__jem_register_history WHERE registration_id = 101 ORDER BY revision')->loadObjectList();
        $assert(count($history) === 1, 'Exactly one history operation expected');
        $results[$case] = ['saved' => true, 'history_action' => $history[0]->action,
            'history_old_places' => (int) $history[0]->old_places, 'history_new_places' => (int) $history[0]->new_places, 'status' => (int) $after->status, 'places' => (int) $after->places,
            'amount' => $after->grand_total, 'payment_state' => $after->payment_state, 'active_places' => $active,
            'events' => count($preview['events'])];
    }
    // Exercise the actual frontend response guard after an event changes back to classic mode.
    class JemHelper { public static function config(): object { return (object) []; } }
    $guardModel = new class extends JemModelEvent {
        public function getItem($pk = null) { return $this->_db->setQuery('SELECT * FROM #__jem_events WHERE id = 11')->loadObject(); }
        protected function getAuthoritativeRegistrationEvents(array $candidates, $primaryEvent, $userId = 0, $allowOwnedCancellation = false) { return $candidates; }
    };
    $guardModel->setId(11);
    $db->setQuery("UPDATE #__jem_events SET pricing_mode = 'classic' WHERE id = 11")->execute();
    $guards = [];
    foreach ([1, -1, 2] as $status) {
        $db->setQuery("UPDATE #__jem_register SET pricing_mode = 'multiple', status = " . $status . ' WHERE id = 101')->execute();
        $before = $snapshot();
        $app->input = new Joomla\Input\Input(['reg_check' => 1, 'addplaces' => 1]);
        $guards[$status] = $guardModel->userregister() === false
            && $guardModel->getError() === Joomla\CMS\Language\Text::_('COM_JEM_PRICED_REGISTRATION_REQUIRES_ORDER')
            && $snapshot() === $before;
    }
    // Render real templates with real nested partials; isolate routing and token generation only.
    class Analysis2489Route { public static function _($url): string { return (string) $url; } }
    class_alias(Analysis2489Route::class, 'Joomla\\CMS\\Router\\Route');
    Joomla\CMS\HTML\HTMLHelper::register('form.token', static fn(): string => '');
    $ui = $htmlPages = [];
    foreach (['default_regform.php', 'responsive/default_regform.php'] as $layout) {
        foreach (['priced_new', 'priced_existing', 'priced_waiting', 'priced_invited', 'priced_cancelled',
            'priced_closed', 'priced_areas', 'priced_series', 'priced_classic_event', 'unpriced_priced_event',
            'requested_no_answer', 'requested_no_answer_closed', 'classic_new', 'classic_existing'] as $case) {
            $view = new class {
                public $showRegForm = true, $print = false, $item, $registereduser = null, $registers = [],
                    $registration = null, $isregistered = false, $allowRegistration = true, $allowAnnulation = true,
                    $user, $jemsettings, $capacityRegistration, $root, $layout;
                public function getModel($name): object { return new class { public function getListRecurrenceEventsbyId(...$args): array { return []; } }; }
                public function loadTemplate($name): string {
                    $base = $this->root . '/site/views/event/tmpl/';
                    return $this->render($base . (str_starts_with($this->layout, 'responsive/') ? 'responsive/' : '') . 'default_' . $name . '.php');
                }
                public function escape($text): string { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
                public function render(string $path): string { ob_start(); include $path; return ob_get_clean(); }
            };
            $view->root = $source;
            $view->layout = $layout;
            $view->item = (object) ['id' => 11, 'did' => 11, 'maxplaces' => 20, 'booked' => 4, 'reservedplaces' => 0,
                'waitinglist' => 1, 'maxbookeduser' => 10, 'minbookeduser' => 1, 'seriesbooking' => $case === 'priced_series' ? 1 : 0,
                'singlebooking' => 1, 'recurrence_type' => $case === 'priced_series' ? 1 : 0, 'recurrence_first_id' => 11,
                'requestanswer' => str_starts_with($case, 'requested_no_answer') ? 1 : 0, 'registra' => 1,
                'pricing_mode' => str_starts_with($case, 'classic_') || $case === 'priced_classic_event' ? 'classic' : 'multiple'];
            $view->user = (object) ['id' => 42];
            $view->jemsettings = (object) ['regallowcomments' => 1];
            $view->capacityRegistration = (object) ['enabled' => false, 'options' => []];
            if (!in_array($case, ['priced_new', 'classic_new', 'requested_no_answer', 'requested_no_answer_closed'], true)) {
                $status = match ($case) { 'priced_waiting' => 2, 'priced_invited' => 0, 'priced_cancelled' => -1, default => 1 };
                $view->registration = (object) ['id' => 101, 'status' => $status, 'places' => 4,
                    'pricing_mode' => in_array($case, ['unpriced_priced_event', 'classic_existing'], true) ? 'classic' : 'multiple',
                    'comment' => '<script>bad()</script>'];
                $view->isregistered = $status;
                $view->registers = [$view->registration];
                $view->registereduser = 0;
            }
            if ($case === 'priced_areas') {
                $view->capacityRegistration->options = [
                    ['key' => 'area:1', 'current_quantity' => 2, 'space_name' => 'Hall', 'area_name' => 'A'],
                    ['key' => 'area:2', 'current_quantity' => 2, 'space_name' => 'Hall', 'area_name' => 'B']];
            }
            $view->allowAnnulation = $case !== 'priced_closed';
            $view->allowRegistration = $case !== 'requested_no_answer_closed';
            $html = $view->render($source . '/site/views/event/tmpl/' . $layout);
            $doc = new DOMDocument();
            @$doc->loadHTML('<!doctype html><meta charset="utf-8">' . $html);
            $xpath = new DOMXPath($doc);
            $count = static fn(string $path): int => $xpath->query($path)->length;
            $key = $layout . '/' . $case;
            $ui[$key] = [
                'attend_enabled' => $count('//input[@name="reg_check" and @value="1" and not(@disabled)]') === 1,
                'editable_quantity' => $count('//input[@name="cancelplaces" and @type="number"]') === 1,
                'cancel_selected' => $count('//input[@name="reg_check" and @value="-1" and @checked]') === 1,
                'fixed_quantity' => $xpath->evaluate('string(//input[@name="cancelplaces" and @type="hidden"]/@value)'),
                'submit' => $count('//*[@id="jem_send_attend" and not(@disabled)]') === 1,
                'dialog' => $count('//dialog[contains(@class,"jem-cancellation-dialog")]') === 1,
                'editable_areas' => $count('//input[@data-cancel-area]'),
                'script' => $count('//script'),
                'series' => $count('//input[@name="reg_check_series"]'),
                'notice' => str_contains($html, Text('COM_JEM_PRICED_REGISTRATION_REQUIRES_ORDER')),
            ];
            $htmlPages[$key] = $html;
        }
    }
    // Render the complete administrator history detail for a cancellation with preserved quantity.
    $adminStrings = parse_ini_file($source . '/admin/language/en-GB/com_jem.ini', false, INI_SCANNER_RAW);
    (new ReflectionProperty(Joomla\CMS\Factory::$language, 'strings'))->setValue(Joomla\CMS\Factory::$language, $strings + $adminStrings);
    $historyView = new class {
        public $item, $timeline;
        public function escape($text): string { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
        public function render($file): string { ob_start(); include $file; return ob_get_clean(); }
    };
    $entry = (object) ['id' => 1, 'revision' => 2, 'registration_reference' => 'R-test', 'operation_reference' => 'O-test',
        'current_event_id' => 11, 'event_id' => 11, 'event_display_title' => 'Workshop', 'event_title' => 'Workshop', 'action' => 'cancelled',
        'occurred' => '2030-01-01 10:00:00', 'source' => 'site.event.registration_response', 'reason_code' => null,
        'actor_user_id' => 42, 'actor_name' => 'User', 'actor_username' => 'user', 'forced' => 0,
        'old_status' => 1, 'new_status' => -1, 'old_places' => 4, 'new_places' => 4,
        'old_user_id' => 42, 'new_user_id' => 42, 'new_holder_name' => 'User', 'old_holder_name' => 'User',
        'new_holder_username' => 'user', 'old_holder_username' => 'user', 'changed_fields' => '["status"]'];
    $historyView->item = $entry;
    $historyView->timeline = [$entry];
    $historyHtml = $historyView->render($source . '/admin/views/registrationhistoryentry/tmpl/default.php');
    $historyMessage = Joomla\CMS\Language\Text::sprintf('COM_JEM_REGISTRATION_HISTORY_CANCELLED_PLACES', 4);
    echo json_encode(['php' => PHP_VERSION, 'cases' => $results, 'ui' => $ui, 'guards' => $guards,
        'history_message' => str_contains($historyHtml, $historyMessage), 'html' => $htmlPages,
        'assertions' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} finally {
    if ($db) { $db->disconnect(); }
    $server->query('DROP DATABASE ' . $database);
    $server->close();
}

/** Resolve the actual language text for the rendered form assertions. */
function Text(string $key): string { return Joomla\CMS\Language\Text::_($key); }
