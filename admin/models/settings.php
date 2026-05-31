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
use Joomla\Database\DatabaseDriver;

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

        // sanitize
        if (empty($data['imagewidth'])) {
            $data['imagewidth'] = 100;
        }
        if (empty($data['imagehight'])) {
            $data['imagehight'] = 100;
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

        return true;
    }

    /**
     * Persist enabled countries posted from the Countries settings tab.
     *
     * @return bool
     */
    protected function storeCountryStates()
    {
        $input = Factory::getApplication()->input;

        if (!$input->exists('jem_country_published')) {
            return true;
        }

        $states = $input->get('jem_country_published', array(), 'array');

        try {
            $db = Factory::getContainer()->get(DatabaseDriver::class);
            $this->ensureCountriesPublishedColumn($db);

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
                                  'plg_content_jem', 'plg_content_jemlistevents', 'plg_content_jemembed',
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
        return $config;
    }
}
