<?php
/** Render the real two panels with Joomla form inputs and disposable fixture data. */
define('_JEXEC', 1);
define('JEM_TEST_ROOT', dirname(__DIR__, 3));
putenv('JEM_TEST_JOOMLA_ROOT=' . ($argv[1] ?? 'C:/xampp/htdocs/jl602'));
require JEM_TEST_ROOT . '/tests/Support/invitation_publication_environment.php';

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\Registry\Registry;

Factory::getContainer()->set(Joomla\CMS\HTML\Registry::class, new Joomla\CMS\HTML\Registry());
Factory::$application = new class {
    /** @return Joomla\CMS\Language\Language The fixture language. */
    public function getLanguage()
    {
        return Factory::$language;
    }

    /** @return Joomla\Input\Input Empty request input. */
    public function getInput()
    {
        return new Joomla\Input\Input();
    }

    /** @param string $client Requested client. @return bool No installed application is used. */
    public function isClient($client)
    {
        return false;
    }

    /** @return Joomla\CMS\User\User Read-only rendering identity. */
    public function getIdentity()
    {
        return new class extends Joomla\CMS\User\User {
            /** @param string $action Permission. @param string|null $assetname Asset. @return bool */
            public function authorise($action, $assetname = null)
            {
                return false;
            }
        };
    }

    /** @param string $name Setting. @param mixed $default Fallback. @return mixed The fallback. */
    public function get($name, $default = null)
    {
        return $default;
    }
};
$config = (new ReflectionClass(JemConfig::class))->newInstanceWithoutConstructor();
(new ReflectionProperty($config, '_data'))->setValue($config, new Registry(['defaultCountry' => 'ES']));
(new ReflectionProperty(JemConfig::class, 'instance'))->setValue(null, $config);

$scenario = $argv[2] ?? 'saved-event';
$assignments = [
    ['id' => 11, 'profile_id' => 3, 'space_name' => 'Floor', 'layout_name' => 'Seats', 'layout_id' => 21, 'capacity' => 50],
    ['id' => 12, 'profile_id' => 3, 'space_name' => 'Balcony', 'layout_name' => 'Seats', 'layout_id' => 22, 'capacity' => 50],
    ['id' => 13, 'profile_id' => 4, 'space_name' => 'Floor', 'layout_name' => 'Standing', 'layout_id' => 23, 'capacity' => 70],
];
$all = 'profile:3:selection:11,12';
$balcony = 'profile:3:selection:12';
$item = (object) [
    'id' => $scenario === 'new' ? 0 : 7, 'dates' => '2026-12-01', 'currency' => 'EUR', 'venue_allocation_mode' => $scenario === 'none' ? 'none' : 'profile',
    'capacity_mode' => 'areas', 'pricing_mode' => 'single', 'event_prices' => [],
    'venue_configuration_key' => $scenario === 'snapshot' ? 'saved' : $all, 'venue_assignment_ids' => $scenario === 'snapshot' ? '[99]' : '[11,12]',
    'venue_profile_id' => 3, 'venue_profile_revision' => 1, 'venue_snapshot' => '{"schema":"jem-venue-capacity/v1"}',
    'pricing_requirements' => [
        'venue_exists' => true, 'capacity_ready' => true, 'country_code' => 'ES',
        'space_count' => 2, 'configured_capacity' => 100, 'profile_revision' => 1,
        'configuration_options' => [
            ['key' => $all, 'label' => 'Entire venue', 'assignment_ids' => [11,12], 'capacity' => 100, 'pool_candidates' => [
                ['code' => 'floor', 'name' => 'Floor', 'capacity' => 50, 'venue_layout_id' => 21],
                ['code' => 'balcony', 'name' => 'Balcony', 'capacity' => 50, 'venue_layout_id' => 22],
            ]],
            ['key' => $balcony, 'label' => 'Balcony', 'assignment_ids' => [12], 'capacity' => 50, 'pool_candidates' => [
                ['code' => 'balcony', 'name' => 'Balcony', 'capacity' => 50, 'venue_layout_id' => 22],
            ]],
            ['key' => 'profile:4:selection:13', 'label' => 'Standing', 'assignment_ids' => [13], 'capacity' => 70, 'pool_candidates' => [
                ['code' => 'standing', 'name' => 'Standing', 'capacity' => 70, 'venue_layout_id' => 23],
            ]],
        ],
        'configuration_profiles' => [['profile_id' => 3, 'profile_name' => 'Main'], ['profile_id' => 4, 'profile_name' => 'Standing']],
        'configuration_assignments' => $assignments,
        'configuration_custom_required' => true,
    ],
];
$form = new Form('probe2524', ['control' => 'jform']);
$form->loadFile(JEM_TEST_ROOT . '/admin/models/forms/event.xml');
$form->bind((array) $item);
$view = new class ($item, $form) {
    public object $form;
    /** @param object $item Fixture event. @param Form $form Real Joomla field rendering. */
    public function __construct(public object $item, Form $form)
    {
        $this->form = new class ($form) {
            /** @param Form $form The real form loaded from JEM XML. */
            public function __construct(private Form $form)
            {
            }
            /** @param string $name Field name. @return string Actual controls; minimal price row for area updates. */
            public function getInput(string $name): string
            {
                if ($name === 'event_prices') {
                    return '<div class="subform-repeatable-group"><input name="jform[event_prices][row0][id]" value="1">'
                        . '<input name="jform[event_prices][row0][code]" value="adult">'
                        . '<input name="jform[event_prices][row0][amount]" value="7.50">'
                        . '<select name="jform[event_prices][row0][capacity_pool_id]"><option value="">General</option>'
                        . '<option value="31" selected>Floor [floor] - 50</option><option value="32">Balcony [balcony] - 50</option></select></div>';
                }
                return in_array($name, ['venue_allocation_mode','capacity_mode','pricing_mode','venue_profile_id',
                    'venue_profile_revision','venue_snapshot','venue_configuration_key','venue_assignment_ids',
                    'reload_venue_capacity'], true) ? $this->form->getInput($name) : '';
            }
            /** @param string $name Field name. @return string The actual field input. */
            public function renderField(string $name): string
            {
                return $this->getInput($name);
            }
        };
    }
    /** @param bool $pricing Include the optional Pricing panel. @return void */
    public function render(bool $pricing): void
    {
        echo '<!doctype html><html><body><form id="adminForm"><input id="jform_locid_id" value="1"><input id="jform_maxplaces" value="100">';
        require JEM_TEST_ROOT . '/admin/views/event/tmpl/edit_capacity.php';
        if ($pricing) {
            require JEM_TEST_ROOT . '/admin/views/event/tmpl/edit_pricing.php';
        }
        echo '</form></body></html>';
    }
};
$view->render($scenario !== 'capacity-only');
