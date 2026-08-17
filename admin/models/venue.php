<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Registry\Registry;
use Joomla\String\StringHelper;

require_once __DIR__ . '/admin.php';
require_once JPATH_SITE . '/components/com_jem/classes/customfields.class.php';
require_once JPATH_ADMINISTRATOR . '/components/com_jem/classes/venuecapacity.class.php';
require_once JPATH_SITE . '/components/com_jem/classes/venueimagepath.class.php';

/**
 * Model: Venue
 */
class JemModelVenue extends JemModelAdmin
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
     * Method to change the published state of one or more records.
     *
     * @param  array   &$pks  A list of the primary keys to change.
     * @param  integer $value The value of the published state.
     *
     * @return boolean True on success.
     *
     * @since  2.2.2
     */
    public function publish(&$pks, $value = 1)
    {
        // Additionally include the JEM plugins for the onContentChangeState event.
        PluginHelper::importPlugin('jem');

        return parent::publish($pks, $value);
    }

    /**
     * Method to test whether a record can be deleted.
     *
     * @param  object  A record object.
     * @return boolean True if allowed to delete the record. Defaults to the permission set in the component.
     */
    protected function canDelete($record)
    {
        if (!empty($record->id))
        {
            return JemHelperBackend::can('venue', 'delete', $record);
        }

        return false;
    }

    /**
     * Method to delete a venue
     */
    public function delete(&$pks = array())
    {
        $return = array();

        if ($pks)
        {
            $pksTodelete = array();
            $errorNotice = array();
            $db = Factory::getContainer()->get('DatabaseDriver');
            foreach ($pks as $pk)
            {
                $pk = (int) $pk;
                $result = array();

                $query = $db->getQuery(true);
                $query->select(array('COUNT(e.locid) as AssignedEvents'));
                $query->from($db->quoteName('#__jem_venues').' AS v');
                $query->join('LEFT', '#__jem_events AS e ON e.locid = v.id');
                $query->where(array('v.id = ' . $pk));
                $query->group('v.id');
                $db->setQuery($query);
                $assignedEvents = $db->loadResult();

                if ($assignedEvents > 0)
                {
                    $result[] = Text::_('COM_JEM_VENUE_ASSIGNED_EVENT');
                }

                $query = $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__jem_venues'))
                    ->where($db->quoteName('parent_venue_id') . ' = ' . $pk);
                $db->setQuery($query);
                if ((int) $db->loadResult() > 0) {
                    $result[] = Text::_('COM_JEM_VENUE_ERROR_HAS_CHILDREN');
                }

                if ($result)
                {
                    $pkInfo = array("id:".$pk);
                    $result = array_merge($pkInfo,$result);
                    $errorNotice[] = $result;
                }
                else
                {
                    $pksTodelete[] = $pk;
                }
            }

            if ($pksTodelete)
            {
                $return['removed'] = parent::delete($pksTodelete);
                $return['removedCount'] = count($pksTodelete);
            }
            else
            {
                $return['removed'] = false;
                $return['removedCount'] = false;
            }

            if ($errorNotice)
            {
                $return['error'] = $errorNotice;
            }
            else
            {
                $return['error'] = false;
            }

            return $return;
        }

        $return['removed'] = false;
        $return['error'] = false;
        $return['removedCount'] = false;

        return $return;
    }

    /**
     * Method to test whether a record can be deleted.
     *
     * @param  object  A record object.
     * @return boolean True if allowed to change the state of the record. Defaults to the permission set in the component.
     */
    protected function canEditState($record)
    {
        return JemHelperBackend::can('venue', 'edit.state', $record);
    }

    /**
     * Returns a reference to the a Table object, always creating it.
     *
     * @param  string The table to instantiate
     * @param  string A prefix for the table class name. Optional.
     * @param  array  Configuration array for model. Optional.
     * @return Table A database object
     */
    public function getTable($type = 'Venue', $prefix = 'JemTable', $config = array())
    {
        return Table::getInstance($type, $prefix, $config);
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
        $form = $this->loadForm('com_jem.venue', 'venue', array('control' => 'jform', 'load_data' => $loadData));

        if (empty($form)) {
            return false;
        }

        $scope = Factory::getApplication()->isClient('administrator') ? 'backend' : 'frontend_edit';
        JemCustomFields::applyFormLabels($form, 'venue', $scope);

        if ($scope === 'backend') {
            if (!JemHelperBackend::can('venue', 'edit.state')) {
                foreach (array('ordering', 'published') as $fieldName) {
                    $form->setFieldAttribute($fieldName, 'disabled', 'true');
                    $form->setFieldAttribute($fieldName, 'filter', 'unset');
                }
            }

            if (!JemHelperBackend::can('venue', 'edit.created')) {
                $form->setFieldAttribute('created_by', 'disabled', 'true');
                $form->setFieldAttribute('created_by', 'filter', 'unset');
            }
        }

        return $form;
    }

    /**
     * Method to get a single record.
     *
     * @param  integer The id of the primary key.
     *
     * @return mixed   Object on success, false on failure.
     */
    public function getItem($pk = null)
    {
        $jemsettings = JemAdmin::config();

        if ($item = parent::getItem($pk)) {
            $registry = new Registry;
            $registry->loadString($item->attribs ?? '{}');
            $item->attribs = $registry->toArray();

            $registry = new Registry;
            $registry->loadString($item->metadata ?? '{}');
            $item->metadata = $registry->toArray();

            $files = JemAttachment::getAttachments('venue'.$item->id, true);
            $item->attachments = $files;
        }

        JemVenueCapacityService::populateFormItem($item);

        $item->author_ip = JemHelper::getStoredIP();

        if (empty($item->id)) {
            $item->country = $jemsettings->defaultCountry;
            $item->map = (int) JemHelper::globalattribs()->get('global_show_mapserv', 0) > 0 ? 1 : 0;
        }

        list($item->latitude, $item->longitude) = $this->normaliseCoordinates(
            $item->latitude ?? null,
            $item->longitude ?? null
        );

        return $item;
    }

    /**
     * Method to get the data that should be injected in the form.
     */
    protected function loadFormData()
    {
        // Check the session for previously entered form data.
        $data = Factory::getApplication()->getUserState('com_jem.edit.venue.data', array());

        if (empty($data)) {
            $data = $this->getItem();
        }

        return $data;
    }

    /**
     * Prepare and sanitise the table data prior to saving.
     *
     * @param $table Table-object.
     */
    protected function _prepareTable($table)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $table->venue = htmlspecialchars_decode($table->venue, ENT_QUOTES);
        list($table->latitude, $table->longitude) = $this->normaliseCoordinates(
            $table->latitude ?? null,
            $table->longitude ?? null
        );

        // Increment version number.
        $table->version ++;
    }

    /**
     * Method to save the form data.
     *
     * @param $data array
     */
    public function save($data)
    {
        // Variables
        $app         = Factory::getApplication();
        $jinput      = $app->input;
        $jemsettings = JemHelper::config();
        $task        = $jinput->get('task', '', 'cmd');
        $capacityConfigurationSubmitted = !empty($data['capacity_configuration_submitted']);
        $capacityProfileName = JemVenueCapacityService::normaliseProfileName(
            isset($data['capacity_profile_name']) ? (string) $data['capacity_profile_name'] : null
        );
        $capacityProfileCapacity = max(0, (int) ($data['capacity_profile_capacity'] ?? 0));
        $capacityConfiguration = array('spaces' => array());
        if ($capacityConfigurationSubmitted) {
            try {
                $decodedCapacity = json_decode(
                    (string) ($data['capacity_configuration_json'] ?? ''),
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
                if (!is_array($decodedCapacity)) {
                    throw new JsonException('Capacity configuration must be a JSON object.');
                }
                $capacityConfiguration = $decodedCapacity;
            } catch (JsonException $e) {
                $this->setError(Text::_('COM_JEM_VENUE_CAPACITY_ERROR_INVALID_JSON'));

                return false;
            }
        }
        unset(
            $data['capacity_configuration_submitted'],
            $data['capacity_configuration_json'],
            $data['capacity_profile_id'],
            $data['capacity_profile_name'],
            $data['capacity_profile_capacity'],
            $data['capacity_profile_revision']
        );

        // Check if we're in the front or back
        $backend = (bool)$app->isClient('administrator');
        $new     = (bool)empty($data['id']);
        $previousVenueImage = $new ? array() : $this->getVenueImageStorageData((int) $data['id']);
        $submittedVenueImage = isset($data['locimage']) ? (string) $data['locimage'] : (string) ($previousVenueImage['locimage'] ?? '');
        $frontendImageFiles = $jinput->files->get('jform', array(), 'array');
        $frontendImageFile = $jinput->files->get('userfile', array(), 'array');
        if (empty($frontendImageFile) && !empty($frontendImageFiles['userfile'])) {
            $frontendImageFile = $frontendImageFiles['userfile'];
        }
        $venueImageChanged = $new
            || $submittedVenueImage !== (string) ($previousVenueImage['locimage'] ?? '')
            || !empty($frontendImageFile['name']);
        $data['image_path'] = JemVenueImagePath::normaliseRelativeFolder(
            $data['image_path'] ?? ($previousVenueImage['image_path'] ?? '')
        );

        // Store IP of author only.
        if ($new) {
            $author_ip = $jinput->get('author_ip', '', 'string');
            $data['author_ip'] = $author_ip;
        }

        $data['modified'] = (isset($data['modified']) && !empty($data['modified'])) ? $data['modified'] : null;
        $data['publish_up'] = (isset($data['publish_up']) && !empty($data['publish_up'])) ? $data['publish_up'] : null;
        $data['publish_down'] = (isset($data['publish_down']) && !empty($data['publish_down'])) ? $data['publish_down'] : null;
        $data['publish_down'] = (isset($data['publish_down']) && !empty($data['publish_down'])) ? $data['publish_down'] : null;
        $data['attribs'] = (isset($data['attribs'])) ? $data['attribs'] : '';
        $data['language'] = (isset($data['language'])) ? $data['language'] : '';
        list($data['latitude'], $data['longitude']) = $this->normaliseCoordinates(
            $data['latitude'] ?? null,
            $data['longitude'] ?? null
        );
        $data['map'] = $this->isMapEnabled($data['map'] ?? 0) ? 1 : 0;
        if ($data['map'] && !$this->hasMappableLocation($data)) {
            $this->setError(Text::_('COM_JEM_VENUE_ERROR_MAP_ADDRESS'));
            return false;
        }

        if ($capacityConfigurationSubmitted) {
            try {
                $capacityConfiguration = JemVenueCapacityService::normaliseFormData(
                    $capacityConfiguration,
                    $capacityProfileCapacity,
                    (int) ($data['capacity'] ?? 0)
                );
            } catch (InvalidArgumentException $e) {
                $this->setError($e->getMessage());

                return false;
            }
        }

        $customFieldErrors = array();
        if (!JemCustomFields::validateAndSanitizeData('venue', $data, $customFieldErrors)) {
            $this->setError(implode('<br>', $customFieldErrors));
            return false;
        }

        // Store as copy - reset creation date, modification fields, hit counter, version
        if ($task == 'save2copy') {
            list($data['venue'], $data['alias']) = $this->generateCopyTitleAndAlias(
                $data['venue'] ?? '',
                $data['alias'] ?? '',
                'venue'
            );
            unset($data['created']);
            unset($data['modified']);
            unset($data['modified_by']);
            unset($data['version']);
        //    unset($data['hits']);
        }

        //uppercase needed by mapservices
        if ($data['country']) {
            $data['country'] = StringHelper::strtoupper($data['country']);
        }

        // Save the venue
        $saved = parent::save($data);

        if ($saved) {
            // At this point we do have an id.
            $pk = $this->getState($this->getName() . '.id');

            if (!$this->syncVenueImageStorage((int) $pk, $venueImageChanged)) {
                return false;
            }

            try {
                if ($backend && $capacityConfigurationSubmitted) {
                    $savedCapacityConfiguration = JemVenueCapacityService::saveDefaultConfiguration(
                        (int) $pk,
                        $capacityConfiguration,
                        $capacityProfileName
                    );
                    JemVenueCapacityService::saveConfigurationMedia(
                        (int) $pk,
                        $savedCapacityConfiguration,
                        $capacityConfiguration,
                        (array) $jinput->files->get('capacity_space_image', array(), 'array'),
                        (array) $jinput->files->get('capacity_layout_image', array(), 'array')
                    );
                }
            } catch (Throwable $e) {
                $this->setError(Text::sprintf('COM_JEM_VENUE_CAPACITY_SAVE_FAILED', $e->getMessage()));

                return false;
            }

            // on frontend attachment uploads maybe forbidden
            // so allow changing name or description only
            $allowed = $backend || ($jemsettings->attachmentenabled > 0);

            if ($allowed) {
                // attachments, new ones first
                $attachments   = $jinput->files->get('attach', array(), 'array');
                $attach_name   = $jinput->post->get('attach-name', array(), 'array');
                $attach_descr  = $jinput->post->get('attach-desc', array(), 'array');
                $attach_access = $jinput->post->get('attach-access', array(), 'array');
                $attach_order  = $jinput->post->get('attach-order', array(), 'array');
                $attach_frontend = $jinput->post->get('attach-frontend', array(), 'array');
                foreach($attachments as $n => &$a) {
                    $a['customname']  = array_key_exists($n, $attach_access) ? $attach_name[$n]   : '';
                    $a['description'] = array_key_exists($n, $attach_access) ? $attach_descr[$n]  : '';
                    $a['access']      = array_key_exists($n, $attach_access) ? $attach_access[$n] : '';
                    $a['ordering']    = array_key_exists($n, $attach_order) ? $attach_order[$n] : 0;
                    $a['frontend']    = array_key_exists($n, $attach_frontend) ? $attach_frontend[$n] : 1;
                }
                JemAttachment::postUpload($attachments, 'venue' . $pk);
            }

            // and update old ones
            $old = array();
            $old['id']          = $jinput->post->get('attached-id', array(), 'array');
            $old['name']        = $jinput->post->get('attached-name', array(), 'array');
            $old['description'] = $jinput->post->get('attached-desc', array(), 'array');
            $old['access']      = $jinput->post->get('attached-access', array(), 'array');
            $old['ordering']    = $jinput->post->get('attached-order', array(), 'array');
            $old['frontend']    = $jinput->post->get('attached-frontend', array(), 'array');

            foreach ($old['id'] as $k => $id){
                $attach = array();
                $attach['id']          = $id;
                $attach['name']        = $old['name'][$k] ?? '';
                $attach['description'] = $old['description'][$k] ?? '';
                $attach['ordering']    = $old['ordering'][$k] ?? 0;
                if (array_key_exists($k, $old['frontend'])) {
                    $attach['frontend'] = $old['frontend'][$k];
                }
                if ($allowed && array_key_exists($k, $old['access'])) {
                    $attach['access']  = $old['access'][$k];
                } // else don't touch this field
                JemAttachment::update($attach, 'venue' . $pk);
            }
        }

        return $saved;
    }

    /**
     * Move only new or replaced venue images into the stable ID path. Existing
     * flat images are deliberately left untouched for compatibility.
     */
    private function syncVenueImageStorage(int $venueId, bool $imageChanged): bool
    {
        if ($venueId < 1) {
            return false;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName(array('locimage', 'image_path')))
                ->from($db->quoteName('#__jem_venues'))
                ->where($db->quoteName('id') . ' = ' . $venueId)
        );
        $current = (array) ($db->loadAssoc() ?: array());
        $filename = (string) ($current['locimage'] ?? '');

        if ($filename === '') {
            if ((string) ($current['image_path'] ?? '') !== '') {
                $db->updateObject('#__jem_venues', (object) array('id' => $venueId, 'image_path' => ''), 'id');
            }
            return true;
        }

        if (!$imageChanged) {
            return true;
        }

        $sourceFolder = JemVenueImagePath::normaliseRelativeFolder($current['image_path'] ?? '');
        $targetFolder = JemVenueImagePath::venueFolder($venueId);
        if (!JemVenueImagePath::relocateImages(
            $sourceFolder,
            $targetFolder,
            array($filename),
            JemHelper::config(),
            false
        )) {
            $this->setError(Text::_('COM_JEM_VENUE_IMAGE_STORAGE_FAILED'));
            return false;
        }

        $db->updateObject(
            '#__jem_venues',
            (object) array('id' => $venueId, 'image_path' => $targetFolder),
            'id'
        );

        return true;
    }

    private function getVenueImageStorageData(int $venueId): array
    {
        if ($venueId < 1) {
            return array();
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName(array('locimage', 'image_path')))
                ->from($db->quoteName('#__jem_venues'))
                ->where($db->quoteName('id') . ' = ' . $venueId)
        );

        return (array) ($db->loadAssoc() ?: array());
    }

    /**
     * A venue can expose a map link only when it has a full address or valid coordinates.
     */
    protected function hasMappableLocation(array $data): bool
    {
        $hasAddress = trim((string) ($data['street'] ?? '')) !== ''
            && trim((string) ($data['city'] ?? '')) !== ''
            && trim((string) ($data['country'] ?? '')) !== ''
            && trim((string) ($data['postalCode'] ?? '')) !== '';

        if ($hasAddress) {
            return true;
        }

        $latitude = trim((string) ($data['latitude'] ?? ''));
        $longitude = trim((string) ($data['longitude'] ?? ''));

        if ($latitude === '' || $longitude === '' || !is_numeric($latitude) || !is_numeric($longitude)) {
            return false;
        }

        $latitude = (float) $latitude;
        $longitude = (float) $longitude;

        return $latitude >= -90.0 && $latitude <= 90.0
            && $longitude >= -180.0 && $longitude <= 180.0
            && $latitude !== 0.0
            && $longitude !== 0.0;
    }

    /**
     * Store incomplete, empty and Null Island coordinates as NULL.
     */
    protected function normaliseCoordinates($latitude, $longitude): array
    {
        $latitude = trim((string) $latitude);
        $longitude = trim((string) $longitude);

        if (
            $latitude === ''
            || $longitude === ''
            || !is_numeric($latitude)
            || !is_numeric($longitude)
            || (float) $latitude === 0.0
            || (float) $longitude === 0.0
        ) {
            return array(null, null);
        }

        return array($latitude, $longitude);
    }

    /**
     * Normalise map checkbox values before validation and storage.
     */
    protected function isMapEnabled($value): bool
    {
        return in_array($value, array(1, '1', true, 'true', 'on', 'yes'), true);
    }
}
