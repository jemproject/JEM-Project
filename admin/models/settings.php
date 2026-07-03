<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseDriver;
use Joomla\Registry\Registry;

require_once JPATH_SITE . '/components/com_jem/classes/customfields.class.php';

/**
 * JEM Component Settings Model
 *
 */
class JemModelSettings extends AdminModel
{
    /**
     * Constructor
     */
    public function __construct($config = array(), $factory = null)
    {
        parent::__construct($config, $factory);
        
        // Set the dispatcher for Joomla 6 compatibility
        if (method_exists($this, 'setDispatcher')) {
            $this->setDispatcher(Factory::getApplication()->getDispatcher());
        }
    }

    /**
     * Method to get the record form.
     *
     * @param  array   $data     Data for the form.
     * @param  boolean $loadData True if the form is to load its own data (default case), false if not.
     * @return mixed   A JForm object on success, false on failure
     */
    public function getForm($data = array(), $loadData = true)
    {
        // Get the form.
        $form = $this->loadForm('com_jem.settings', 'settings', array('control' => 'jform', 'load_data' => $loadData));

        if (empty($form)) {
            return false;
        }

        return $form;
    }

    /**
     * Loading the table data
     */
    public function getData()
    {
        $config = JemConfig::getInstance();
        $data = $config->toObject();

        if (isset($data->pdf_enabled_views) && !is_array($data->pdf_enabled_views)) {
            $data->pdf_enabled_views = array_filter(array_map('trim', explode(',', (string) $data->pdf_enabled_views)));
        }

        return $data;
    }

    /**
     * Return countries grouped by continent for the settings screen.
     *
     * @return array
     */
    public function getCountryGroups()
    {
        $continents = array(
            'AF' => Text::_('COM_JEM_CONTINENT_AFRICA'),
            'AN' => Text::_('COM_JEM_CONTINENT_ANTARCTICA'),
            'AS' => Text::_('COM_JEM_CONTINENT_ASIA'),
            'EU' => Text::_('COM_JEM_CONTINENT_EUROPE'),
            'NA' => Text::_('COM_JEM_CONTINENT_NORTH_AMERICA'),
            'OC' => Text::_('COM_JEM_CONTINENT_OCEANIA'),
            'SA' => Text::_('COM_JEM_CONTINENT_SOUTH_AMERICA'),
        );

        $groups = array();

        foreach ($continents as $code => $label) {
            $groups[$code] = array(
                'label'     => $label,
                'countries' => array(),
                'total'     => 0,
                'active'    => 0,
            );
        }

        try {
            $db = Factory::getContainer()->get(DatabaseDriver::class);
            $this->ensureCountriesPublishedColumn($db);

            $query = $db->getQuery(true)
                ->select($db->quoteName(array('continent', 'iso2', 'iso3', 'name', 'published')))
                ->from($db->quoteName('#__jem_countries'))
                ->order($db->quoteName('continent') . ' ASC, ' . $db->quoteName('name') . ' ASC');

            $db->setQuery($query);

            foreach ((array) $db->loadObjectList() as $country) {
                $continent = (string) $country->continent;

                if (!isset($groups[$continent])) {
                    $groups[$continent] = array(
                        'label'     => $continent,
                        'countries' => array(),
                        'total'     => 0,
                        'active'    => 0,
                    );
                }

                $country->published = (int) $country->published;
                $groups[$continent]['countries'][] = $country;
                $groups[$continent]['total']++;

                if ($country->published) {
                    $groups[$continent]['active']++;
                }
            }
        } catch (Exception $e) {
            $this->setError($e->getMessage());
        }

        return $groups;
    }

    /**
     * Method to get the data that should be injected in the form.
     */
    protected function loadFormData()
    {
        // Check the session for previously entered form data.
        $data = Factory::getApplication()->getUserState('com_jem.edit.settings.data', array());

        if (empty($data)) {
            $data = $this->getData();
        }

        return $data;
    }

    /**
     * Saves the settings
     */
    public function store($data)
    {
        // If the source value is an object, get its accessible properties.
        if (is_object($data)) {
            $data = get_object_vars($data);
        }

        // additional data:
        $jinput = Factory::getApplication()->input;
        $varmetakey = $jinput->get('meta_keywords','','');
        $data['meta_keywords'] = implode(', ', array_filter($varmetakey));
        $data['lastupdate'] = $jinput->get('lastupdate','',''); // 'lastupdate' indicates last cleanup etc., not when config as stored.
        if ($jinput->exists('jem_custom_fields')) {
            $data['custom_fields_config'] = json_encode(JemCustomFields::normaliseConfig($jinput->get('jem_custom_fields', array(), 'array')));
        }

        // sanitize
        if (empty($data['imagewidth'])) {
            $data['imagewidth'] = 100;
        }
        if (empty($data['imagehight'])) {
            $data['imagehight'] = 100;
        }
        if (empty($data['pdf_imagewidth'])) {
            $data['pdf_imagewidth'] = 40;
        }
        if (empty($data['pdf_imageheight'])) {
            $data['pdf_imageheight'] = 40;
        }
        if (!empty($data['pdf_enabled_views']) && is_array($data['pdf_enabled_views'])) {
            $data['pdf_enabled_views'] = implode(',', array_unique(array_filter(array_map('trim', $data['pdf_enabled_views']))));
        }
        if (empty($data['pdf_enabled_views'])) {
            $data['pdf_enabled_views'] = 'annualcalendar,attendeeregistrations,calendar,categories,category,day,event,eventslist,eventsmap,myattendances,myevents,mytimeline,myvenues,specialdays,typeevents,typevenues,venue,venues,venueslist,venuesmap,weekcal';
        }
        $pdfDefaults = array(
            'pdf_paper_size' => 'A4',
            'pdf_orientation' => 'L',
            'pdf_calendar_layout' => 'calendar',
            'pdf_margin_profile' => 'medium',
            'pdf_margin_top' => 14,
            'pdf_margin_right' => 14,
            'pdf_margin_bottom' => 14,
            'pdf_margin_left' => 14,
            'pdf_background_color' => '#ffffff',
            'pdf_include_view_text' => 1,
            'pdf_title_font_family' => 'helvetica',
            'pdf_header_font_family' => 'helvetica',
            'pdf_body_font_family' => 'helvetica',
            'pdf_accent_color' => '#1d4ed8',
            'pdf_title_font_size' => 18,
            'pdf_base_font_size' => 8,
            'pdf_heading_font_size' => 12,
            'pdf_event_layout' => 'details',
            'pdf_list_paper_size' => 'A4',
            'pdf_list_orientation' => 'P',
            'pdf_map_paper_size' => 'A4',
            'pdf_map_orientation' => 'L',
            'pdf_event_description_mode' => 'complete',
            'pdf_venue_description_mode' => 'complete',
            'pdf_event_imagewidth' => 40,
            'pdf_event_imageheight' => 40,
            'pdf_event_image_position' => 'right',
            'pdf_venue_imagewidth' => 40,
            'pdf_venue_imageheight' => 40,
            'pdf_venue_image_position' => 'right',
            'pdf_event_show_images' => 1,
            'pdf_event_include_links' => 1,
            'pdf_event_include_attachments' => 1,
            'pdf_event_include_registration' => 1,
            'pdf_event_include_contacts' => 1,
            'pdf_event_include_online_meeting' => 1,
            'pdf_event_venue_mode' => 'full',
            'pdf_event_include_venue_map' => 'none',
            'pdf_annual_paper_size' => 'A4',
            'pdf_annual_orientation' => 'L',
            'pdf_annual_month_matrix' => 'auto',
            'pdf_annual_vertical_align' => 'top',
            'pdf_annual_show_day_types_legend' => 1,
            'pdf_annual_show_categories_legend' => 1,
            'pdf_annual_event_titles' => 'auto',
            'pdf_annual_event_limit' => 6,
            'pdf_annual_column_gap' => 1,
            'pdf_annual_row_gap' => 1,
        );

        foreach ($pdfDefaults as $key => $value) {
            if (!isset($data[$key]) || $data[$key] === '') {
                $data[$key] = $value;
            }
        }

        //
        // Store into new table
        //
        $config = JemConfig::getInstance();

        // Bind the form fields to the table
        if (!$config->bind($data)) {
            $this->setError(Text::_('?'));
            return false;
        }
        if (!$config->store()) {
            $this->setError(Text::_('?'));
            return false;
        }

        if (!$this->storeCountryStates()) {
            return false;
        }

        //
        // Old table - deprecated, maybe already removed
        //
        try {
            $settings = Table::getInstance('Settings', 'JemTable');

            $fields = $settings->getFields();
            if (!empty($fields)) {
                // Bind the form fields to the table
                if (!$settings->bind($data,'')) {
                    $this->setError($settings->getError());
                    return false;
                }

                $varmetakey = $jinput->get('meta_keywords','','');
                $settings->meta_keywords = $varmetakey;

                $meta_key="";
                foreach ($settings->meta_keywords as $meta_keyword) {
                    if ($meta_key != "") {
                        $meta_key .= ", ";
                    }
                    $meta_key .= $meta_keyword;
                }

                // binding the input fields (outside the jform)
                $varlastupdate = $jinput->get('lastupdate','','');
                $settings->lastupdate = $varlastupdate;

                $settings->meta_keywords = $meta_key;
                $settings->id = 1;

                if (!$settings->store()) {
                    $this->setError($settings->getError());
                    return false;
                }
            }
            // else: ok, old table removed - simply ignore
        }
        catch(Exception $e) {
            // ok, old table removed - simply ignore
        }

        $this->logSettingsAction($data);

        return true;
    }

    /**
     * Record settings saves in Joomla User Actions Log when enabled.
     *
     * @param   array  $data  Saved settings data.
     *
     * @return void
     */
    private function logSettingsAction(array $data): void
    {
        $global = $data['globalattribs'] ?? array();

        if (is_object($global)) {
            $global = get_object_vars($global);
        }

        if (
            (int) ($global['actionlog_enabled'] ?? 0) !== 1
            || !PluginHelper::isEnabled('actionlog', 'jem')
        ) {
            return;
        }

        try {
            $plugin = PluginHelper::getPlugin('actionlog', 'jem');
            $params = new Registry($plugin->params ?? '{}');

            if ((int) $params->get('log_settings', 1) !== 1) {
                return;
            }

            Factory::getLanguage()->load('plg_actionlog_jem', JPATH_ADMINISTRATOR);
            Factory::getLanguage()->load('plg_actionlog_jem', JPATH_PLUGINS . '/actionlog/jem');

            $app = Factory::getApplication();
            $user = $app->getIdentity();
            $message = array(
                'action' => 'update',
                'type' => 'PLG_ACTIONLOG_JEM_TYPE_SETTINGS',
                'id' => 0,
                'title' => Text::_('COM_JEM_SETTINGS_TITLE'),
                'extension' => 'COM_JEM',
                'userid' => $user->id,
                'username' => $user->username,
                'accountlink' => 'index.php?option=com_users&task=user.edit&id=' . (int) $user->id,
            );

            $model = $app
                ->bootComponent('com_actionlogs')
                ->getMVCFactory()
                ->createModel('Actionlog', 'Administrator', array('ignore_request' => true));

            $model->addLog(array($message), 'PLG_ACTIONLOG_JEM_SETTINGS_UPDATED', 'com_jem.settings', (int) $user->id);
        } catch (Exception $e) {
            // Never let action logging block saving settings.
        }
    }

    /**
     * Store the built-in example custom field configuration.
     *
     * @return bool
     */
    public function loadExampleCustomFields()
    {
        $config = JemConfig::getInstance();

        return $config->set('custom_fields_config', json_encode(JemCustomFields::getExampleConfig())) !== null;
    }

    /**
     * Persist enabled countries posted from the Countries settings tab.
     *
     * @return bool
     */
    protected function storeCountryStates()
    {
        $input = Factory::getApplication()->input;

        if (!$input->exists('jem_country_selection') && !$input->exists('jem_country_published')) {
            return true;
        }

        try {
            $db = Factory::getContainer()->get(DatabaseDriver::class);
            $this->ensureCountriesPublishedColumn($db);

            if ($input->exists('jem_country_selection')) {
                return $this->storeCountrySelection($db, (string) $input->get('jem_country_selection', '', 'raw'));
            }

            $states = $input->get('jem_country_published', array(), 'array');

            foreach ($states as $iso2 => $published) {
                $iso2 = strtoupper(substr(preg_replace('/[^A-Z]/i', '', (string) $iso2), 0, 2));

                if ($iso2 === '') {
                    continue;
                }

                $query = $db->getQuery(true)
                    ->update($db->quoteName('#__jem_countries'))
                    ->set($db->quoteName('published') . ' = ' . ((int) $published ? 1 : 0))
                    ->where($db->quoteName('iso2') . ' = ' . $db->quote($iso2));

                $db->setQuery($query)->execute();
            }
        } catch (Exception $e) {
            $this->setError($e->getMessage());

            return false;
        }

        return true;
    }

    /**
     * Persist the compact Countries settings payload.
     *
     * @param   DatabaseDriver  $db       Database driver.
     * @param   string          $payload  JSON payload posted by the settings form.
     *
     * @return bool
     */
    protected function storeCountrySelection(DatabaseDriver $db, string $payload): bool
    {
        $selection = json_decode($payload, true);

        if (!is_array($selection)) {
            $this->setError(Text::_('COM_JEM_COUNTRIES_INVALID_SELECTION'));

            return false;
        }

        $all = !empty($selection['all']);
        $continents = $this->normaliseCountryCodes((array) ($selection['continents'] ?? array()), 2);
        $include = $this->normaliseCountryCodes((array) ($selection['include'] ?? array()), 2);

        if ($all) {
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__jem_countries'))
                ->set($db->quoteName('published') . ' = 1');

            $db->setQuery($query)->execute();

            return true;
        }

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__jem_countries'))
            ->set($db->quoteName('published') . ' = 0');

        $db->setQuery($query)->execute();

        $where = array();

        if ($continents) {
            $where[] = $db->quoteName('continent') . ' IN (' . implode(',', array_map(array($db, 'quote'), $continents)) . ')';
        }

        if ($include) {
            $where[] = $db->quoteName('iso2') . ' IN (' . implode(',', array_map(array($db, 'quote'), $include)) . ')';
        }

        if (!$where) {
            return true;
        }

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__jem_countries'))
            ->set($db->quoteName('published') . ' = 1')
            ->where('(' . implode(' OR ', $where) . ')');

        $db->setQuery($query)->execute();

        return true;
    }

    /**
     * Normalise country or continent codes.
     *
     * @param   array  $codes   Raw codes.
     * @param   int    $length  Maximum code length.
     *
     * @return array
     */
    protected function normaliseCountryCodes(array $codes, int $length): array
    {
        $normalised = array();

        foreach ($codes as $code) {
            $code = strtoupper(substr(preg_replace('/[^A-Z]/i', '', (string) $code), 0, $length));

            if ($code !== '') {
                $normalised[] = $code;
            }
        }

        return array_values(array_unique($normalised));
    }

    /**
     * Add the published column on beta upgrade paths that have not run the SQL update yet.
     *
     * @param DatabaseDriver $db
     *
     * @return void
     */
    protected function ensureCountriesPublishedColumn(DatabaseDriver $db)
    {
        $columns = $db->getTableColumns('#__jem_countries');

        if (isset($columns['published'])) {
            return;
        }

        $db->setQuery('ALTER TABLE ' . $db->quoteName('#__jem_countries') . ' ADD COLUMN ' . $db->quoteName('published') . " tinyint(1) NOT NULL DEFAULT '1' AFTER " . $db->quoteName('name'))->execute();
        $db->setQuery('ALTER TABLE ' . $db->quoteName('#__jem_countries') . ' ADD KEY ' . $db->quoteName('idx_continent') . ' (' . $db->quoteName('continent') . ')')->execute();
        $db->setQuery('ALTER TABLE ' . $db->quoteName('#__jem_countries') . ' ADD KEY ' . $db->quoteName('idx_published') . ' (' . $db->quoteName('published') . ')')->execute();
    }

    /**
     * Method to auto-populate the model state.
     *
     * @Note Calling getState in this method will result in recursion.
     *
     * @since 1.6
     */
    protected function populateState()
    {
        $app = Factory::getApplication();

        // Load the parameters.
        $params = ComponentHelper::getParams('com_jem');
        $this->setState('params', $params);
    }

    /**
     * Return config information
     */
    public function getConfigInfo()
    {
        $config = new stdClass();

        // Get PHP version
        $phpversion = phpversion();
        $config->vs_php = $phpversion;

        // Magic quotes have been discontinued since PHP 5.4, so simply leave it blank
        $config->vs_php_magicquotes = '';

        // Get GD version
        $gd_version = '?';
        if (function_exists('gd_info')) {
            $gd_info = gd_info();
            if (array_key_exists('GD Version', $gd_info)) {
                $gd_version = $gd_info['GD Version'];
            }
        }
        $config->vs_gd = $gd_version;

        // Get info about all JEM parts
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select(['name', 'type', 'enabled', 'manifest_cache'])
            ->from('#__extensions')
            ->where('name LIKE "%jem%"');
        $db->setQuery($query);
        $extensions = $db->loadObjectList('name');

        $known_extensions = array('pkg_jem', 'com_jem', 'mod_jem', 'mod_jem_cal',
                                  'mod_jem_banner', 'mod_jem_jubilee', 'mod_jem_teaser', 'mod_jem_wide', 'mod_jem_map', 'mod_jem_types',
                                  'plg_content_jemlistevents', 'plg_content_jemembed',
                                  'plg_finder_jem',
                                  'plg_quickicon_jem', 'Quick Icon - JEM',
                                  'plg_jem_comments', 'plg_jem_mailer', 'plg_jem_demo',
                                  'AcyMailing Tag : insert events from JEM 2.1+');

        foreach ($extensions as $name => $extension) {
            if (in_array($name, $known_extensions)) {
                $manifest = json_decode($extension->manifest_cache, true);
                $extension->version      = (!empty($manifest) && array_key_exists('version',      $manifest)) ? $manifest['version']      : '?';
                $extension->creationDate = (!empty($manifest) && array_key_exists('creationDate', $manifest)) ? $manifest['creationDate'] : '?';
                $extension->author       = (!empty($manifest) && array_key_exists('author',       $manifest)) ? $manifest['author']       : '?';
                $config->$name = clone $extension;
            }
        }

        $config->libraries = $this->getLibraryInfo();

        return $config;
    }

    /**
     * Return third-party library and font information included or used by JEM.
     *
     * @return array
     */
    protected function getLibraryInfo()
    {
        return array(
            array(
                'name'    => 'Joomla CMS API',
                'version' => defined('JVERSION') ? JVERSION : '?',
                'license' => 'GPL-2.0-or-later',
                'scope'   => 'runtime',
                'path'    => 'Joomla CMS',
            ),
            array(
                'name'    => 'TCPDF',
                'version' => $this->readFirstLine(JPATH_SITE . '/components/com_jem/classes/tcpdf/VERSION', '6.10.0'),
                'license' => 'LGPL-3.0-or-later',
                'scope'   => 'bundled',
                'path'    => 'site/classes/tcpdf',
            ),
            array(
                'name'    => 'TCPDF core font definitions',
                'version' => $this->readFirstLine(JPATH_SITE . '/components/com_jem/classes/tcpdf/VERSION', '6.10.0'),
                'license' => 'LGPL-3.0-or-later',
                'scope'   => 'bundled',
                'path'    => 'site/classes/tcpdf/fonts',
                'notes'   => 'Courier, Helvetica, Times, Symbol, ZapfDingbats',
            ),
            array(
                'name'    => 'DejaVu Sans TCPDF fonts',
                'version' => 'bundled TCPDF font files',
                'license' => 'Bitstream Vera / DejaVu Fonts license; public domain additions',
                'scope'   => 'bundled',
                'path'    => 'site/classes/tcpdf/fonts/dejavusans*',
                'notes'   => 'Regular and bold Unicode font files for PDF output',
            ),
            array(
                'name'    => 'iCalcreator',
                'version' => $this->detectIcalcreatorVersion(),
                'license' => 'LGPL-3.0-or-later',
                'scope'   => 'bundled',
                'path'    => 'site/classes/icalcreator',
            ),
            array(
                'name'    => 'Zebra_Image',
                'version' => '3.0.0',
                'license' => 'LGPL-3.0',
                'scope'   => 'bundled',
                'path'    => 'site/classes/Zebra_Image.php',
            ),
            array(
                'name'    => 'Leaflet',
                'version' => '1.9.4+v1.d15112c',
                'license' => 'BSD-2-Clause',
                'scope'   => 'bundled',
                'path'    => 'media/js/leaflet.js; media/css/leaflet.css',
            ),
            array(
                'name'    => 'Leaflet.fullscreen',
                'version' => '?',
                'license' => 'MIT',
                'scope'   => 'bundled',
                'path'    => 'media/js/leaflet-fullscreen.js; media/css/leaflet-fullscreen.css',
            ),
            array(
                'name'    => 'Leaflet.heat / simpleheat',
                'version' => '?',
                'license' => 'BSD-2-Clause',
                'scope'   => 'bundled',
                'path'    => 'media/js/leaflet-heat.js',
            ),
            array(
                'name'    => 'Lightbox2',
                'version' => '2.11.4',
                'license' => 'MIT',
                'scope'   => 'bundled',
                'path'    => 'media/js/lightbox.min.js; media/css/lightbox.min.css',
            ),
            array(
                'name'    => 'jQuery Geocomplete',
                'version' => '1.7.0',
                'license' => 'MIT',
                'scope'   => 'bundled',
                'path'    => 'media/js/jquery.geocomplete.js',
            ),
            array(
                'name'    => 'Google Maps InfoBox',
                'version' => '1.1.13',
                'license' => 'Apache-2.0',
                'scope'   => 'bundled',
                'path'    => 'media/js/infobox.js',
            ),
            array(
                'name'    => 'Font Awesome Free',
                'version' => '6.7.2',
                'license' => 'Icons: CC BY 4.0; Fonts: SIL OFL 1.1; Code: MIT',
                'scope'   => 'bundled',
                'path'    => 'media/vendor/fontawesome-free',
            ),
        );
    }

    /**
     * Read the first line of a small text file.
     *
     * @param string $path
     * @param string $fallback
     *
     * @return string
     */
    protected function readFirstLine($path, $fallback = '?')
    {
        if (!is_file($path) || !is_readable($path)) {
            return $fallback;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        return !empty($lines[0]) ? trim($lines[0]) : $fallback;
    }

    /**
     * Detect the bundled iCalcreator version from its autoload/constants file.
     *
     * @return string
     */
    protected function detectIcalcreatorVersion()
    {
        $paths = array(
            JPATH_SITE . '/components/com_jem/classes/icalcreator/autoload.php',
            JPATH_SITE . '/components/com_jem/classes/icalcreator/IcalBase.php',
        );

        foreach ($paths as $path) {
            if (!is_file($path) || !is_readable($path)) {
                continue;
            }

            $contents = file_get_contents($path);

            if (preg_match("/ICALCREATOR_VERSION'\\s*,\\s*'iCalcreator\\s+([^']+)'/", $contents, $matches)) {
                return $matches[1];
            }
        }

        return '2.41.92';
    }
}
