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
use Joomla\CMS\Log\Log;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Path;
use Joomla\String\StringHelper;

use Joomla\Utilities\ArrayHelper;
require_once __DIR__ . '/admin.php';

/**
 * Event model.
 */
class JemModelEvent extends JemModelAdmin
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
        $result = false;

        if (!empty($record->id) && ($record->published == -2)) {
            $user = JemFactory::getUser();

            $result = $user->can('delete', 'event', $record->id, $record->created_by, !empty($record->catid) ? $record->catid : false);
        }

        return $result;
    }

    /**
     * Method to test whether a record can be published/unpublished.
     *
     * @param  object  A record object.
     * @return boolean True if allowed to change the state of the record. Defaults to the permission set in the component.
     */
    protected function canEditState($record)
    {
        $user = JemFactory::getUser();

        $id    = $record->id ?? false; // isset ensures 0 !== false
        $owner = !empty($record->created_by) ? $record->created_by : false;
        $cats  = !empty($record->catid) ? array($record->catid) : false;

        return $user->can('publish', 'event', $id, $owner, $cats);
    }

    /**
     * Returns a reference to the a Table object, always creating it.
     *
     * @param  type   The table type to instantiate
     * @param  string A prefix for the table class name. Optional.
     * @param  array  Configuration array for model. Optional.
     * @return Table A database object
     */
    public function getTable($type = 'Event', $prefix = 'JemTable', $config = array())
    {
        return Table::getInstance($type, $prefix, $config);
    }

    /**
     * Method to get the form.
     *
     * @param  array   $data     Data for the form.
     * @param  boolean $loadData True if the form is to load its own data (default case), false if not.
     * @return mixed   A JForm object on success, false on failure
     */
    public function getForm($data = array(), $loadData = true)
    {
        // Get the form.
        $form = $this->loadForm('com_jem.event', 'event', array('control' => 'jform', 'load_data' => $loadData));
        if (empty($form)) {
            return false;
        }

        if ((int) JemHelper::globalattribs()->get('event_use_associated_article', 1) !== 1) {
            $form->removeField('article_id');
            $form->removeField('article_target_category_id');
            $form->removeField('create_article');
            $form->removeField('article_auto_info');
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
            // Convert the params field to an array.
            // (this may throw an exception - but there is nothings we can do)
            $registry = new Registry;
            $registry->loadString($item->attribs ?? '{}');
            $item->attribs = $registry->toArray();

            // Convert the metadata field to an array.
            $registry = new Registry;
            $registry->loadString($item->metadata ?? '{}');
            $item->metadata = $registry->toArray();

            $item->articletext = ($item->fulltext && trim($item->fulltext) != '') ? $item->introtext . "<hr id=\"system-readmore\" />" . $item->fulltext : $item->introtext;

            $db = Factory::getContainer()->get('DatabaseDriver');

            $query = $db->getQuery(true);
            $query->select('SUM(places)');
            $query->from('#__jem_register');
            $query->where(array('event= ' . $db->quote($item->id), 'status=1', 'waiting=0'));

            $db->setQuery($query);
            $res = $db->loadResult();
            $item->booked = $res;

            $files = JemAttachment::getAttachments('event' . $item->id, true);
            $item->attachments = $files;

            // Load links of events
            if ($item->id) {
                $query = $db->getQuery(true)
                    ->select('*')
                    ->from($db->quoteName('#__jem_links'))
                    ->where($db->quoteName('event_id') . ' = ' . (int) $item->id)
                    ->order($db->quoteName('ordering') . ' ASC');

                $db->setQuery($query);
                $links = $db->loadObjectList();

                if ($links) {
                    foreach ($links as &$link) {
                        if (!empty($link->params)) {
                            $linkParams = json_decode($link->params, true);
                            if (is_array($linkParams)) {
                                foreach ($linkParams as $key => $value) {
                                    $link->$key = $value;
                                }
                            }
                        }
                    }
                }
                $item->event_links = $links;
            }
            

            if ($item->id) {
                // Store current recurrence values
                $item->recurr_bak = new stdClass;
                foreach (get_object_vars($item) as $k => $v) {
                    if (strncmp('recurrence_', $k, 11) === 0) {
                        $item->recurr_bak->$k = $v;
                    }
                }

            }

            $item->author_ip = JemHelper::getStoredIP();

            if (empty($item->id)){
                $item->country = $jemsettings->defaultCountry;
            }
        }

        return $item;
    }

    /**
     * Method to get the data that should be injected in the form.
     */
    protected function loadFormData()
    {
        // Check the session for previously entered form data.
        $data = Factory::getApplication()->getUserState('com_jem.edit.event.data', array());

        if (empty($data)){
            $data = $this->getItem();
        }

        return $data;
    }

    /**
     * Prepare and sanitise the table data prior to saving.
     *
     * @param  $table Table-object.
     */
    protected function _prepareTable($table)
    {
        $jinput = Factory::getApplication()->input;

        $db = Factory::getContainer()->get('DatabaseDriver');
        $table->title = htmlspecialchars_decode($table->title, ENT_QUOTES);

        // Increment version number.
        $table->version ++;

        //get time-values from time selectlist and combine them accordingly
        $starthours   = $jinput->get('starthours','','cmd');
        $startminutes = $jinput->get('startminutes','','cmd');
        $endhours     = $jinput->get('endhours','','cmd');
        $endminutes   = $jinput->get('endminutes','','cmd');

        // StartTime
        if ($starthours != '' && $startminutes != '') {
            $table->times = $starthours.':'.$startminutes;
        } else if ($starthours != '' && $startminutes == '') {
            $startminutes = "00";
            $table->times = $starthours.':'.$startminutes;
        } else if ($starthours == '' && $startminutes != '') {
            $starthours = "00";
            $table->times = $starthours.':'.$startminutes;
        } else {
            $table->times = "";
        }

        // EndTime
        if ($endhours != '' && $endminutes != '') {
            $table->endtimes = $endhours.':'.$endminutes;
        } else if ($endhours != '' && $endminutes == '') {
            $endminutes = "00";
            $table->endtimes = $endhours.':'.$endminutes;
        } else if ($endhours == '' && $endminutes != '') {
            $endhours = "00";
            $table->endtimes = $endhours.':'.$endminutes;
        } else {
            $table->endtimes = "";
        }
    }

    /**
     * Method to save the form data.
     *
     * @param  $data array
     */
    public function save($data)
    {
        // Variables
        $app         = Factory::getApplication();
        $jinput      = $app->input;
        $jemsettings = JemHelper::config();
        $table       = $this->getTable();

        // Check if we're in the front or back
        $backend = (bool)$app->isClient('administrator');
        $new     = (bool)empty($data['id']);

        // Variables
        $cats                 = $data['cats'];
        $invitedusers         = $data['invited'] ?? '';
        $recurrencenumber     = $jinput->get('recurrence_number', '', 'int');
        $recurrencebyday      = $jinput->get('recurrence_byday', '', 'string');
        $recurrencebylastday  = $jinput->get('recurrence_bylastday', '', 'string');
        $metakeywords         = $jinput->get('meta_keywords', '', '');
        $metadescription      = $jinput->get('meta_description', '', '');
        $task                 = $jinput->get('task', '', 'cmd');
        $data['metadata']     = $data['metadata'] ?? '';
        $data['attribs']      = $data['attribs'] ?? '';
        $data['ordering']     = $data['ordering'] ?? '';
        if (array_key_exists('type_id', $data) && $data['type_id'] === '') {
            $data['type_id'] = null;
        }
        if (array_key_exists('article_id', $data)) {
            $data['article_id'] = (int) $data['article_id'];
        }
        if (!$this->validateOnlineMeetingData($data)) {
            return false;
        }
        $createArticleMode = isset($data['create_article']) ? (int) $data['create_article'] : 0;
        $articleTargetCategoryId = isset($data['article_target_category_id']) ? (int) $data['article_target_category_id'] : 0;
        unset($data['create_article']);
        unset($data['article_target_category_id']);

        $associatedArticlesEnabled = (int) JemHelper::globalattribs()->get('event_use_associated_article', 1) === 1;

        if (!$associatedArticlesEnabled) {
            $data['article_id'] = 0;
            $createArticleMode  = 0;
        } elseif (!empty($data['article_id']) && !$this->validateAssociatedArticleSelection((int) $data['article_id'], $cats)) {
            return false;
        }

        // convert international date formats...
        $db = Factory::getContainer()->get('DatabaseDriver');
        if (!empty($data['dates']) && ($data['dates'] != null)) {
            $d = Factory::getDate($data['dates'], 'UTC');
            $data['dates'] = $d->format('Y-m-d', true, false);
        }
        if (!empty($data['enddates']) && ($data['enddates'] != null)) {
            $d = Factory::getDate($data['enddates'], 'UTC');
            $data['enddates'] = $d->format('Y-m-d', true, false);
        }
        if (!empty($data['recurrence_limit_date']) && ($data['recurrence_limit_date'] != null)) {
            $d = Factory::getDate($data['recurrence_limit_date'], 'UTC');
            $data['recurrence_limit_date'] = $d->format('Y-m-d', true, false);
        }

        // Load the event from the database, check if the event is the initial event (new, root, and not a recurrence).
        // In this case, the event only needs to be updated if the recurrence setting has not changed.
        $isInitialEvent = true;

        if(!$new && $data["recurrence_type"]) {
            // This event has recurrence, can be a root or child event because has ID (!new).
            $isInitialEvent = false;
            $this->eventid = $data["id"];

            // Get data event in DB
            $eventdb = (array)$this->getEventAllData();

            // Categories
            $eventdb ['cats'] = $this->getEventCats();
            if(isset($data['cats'][0])){
                $data['cats'] = implode(',', $data['cats']);
            }

            // Times
            $starthours = $jinput->get('starthours', 0, 'int');
            if ($starthours){
                $startminutes = $jinput->get('startminutes', 0, 'int');
                if ($startminutes){
                    $data['times'] = str_pad($starthours,2,'0', STR_PAD_LEFT) . ':' . str_pad($startminutes,2,'0', STR_PAD_LEFT) . ':00';
                } else {
                    $data['times'] = str_pad($starthours,2,'0', STR_PAD_LEFT) . ':00:00';
                }
            } else {
                $data['times'] = null;
            }

            //Endtimes
            $endhours = $jinput->get('endhours', 0, 'int');
            if ($endhours){
                $endminutes = $jinput->get('endminutes', 0, 'int');

                if ($endminutes){
                    $data['endtimes'] = str_pad($endhours,2,'0', STR_PAD_LEFT) . ':' . str_pad($endminutes,2,'0', STR_PAD_LEFT) . ':00';
                } else {
                    $data['endtimes'] = str_pad($endhours,2,'0', STR_PAD_LEFT) . ':00:00';
                }
            } else {
                $data['endtimes'] = null;
            }

            // Alias
            if(isset($data['alias'])) {
                if (!$data['alias']) {
                    $alias = strtolower($data['title']);
                    $alias = preg_replace('/[^a-z0-9]+/i', '-', $alias);
                    $alias = preg_replace('/-+/', '-', $alias);
                    $data['alias'] = trim($alias, '-');
                }
            }else{
                $data['alias'] = $eventdb['alias'];
            }

            // Introtext & Fulltext: Search for the {readmore} tag and split the text up accordingly.
            if (isset($data['articletext'])) {
                $pattern = '#<hr\s+id=("|\')system-readmore("|\')\s*\/*>#i';
                $tagPos = preg_match($pattern, $data['articletext']);

                if ($tagPos == 0) {
                    $data['introtext'] = $data['articletext'];
                    $data['fulltext'] = '';
                } else {
                    list ( $data['introtext'], $data['fulltext']) = preg_split($pattern, $data['articletext'], 2);
                }
            }else{
                $data['introtext'] = $data['articletext'];
            }

            // Contact
            if (!JemHelper::isContactComponentEnabled() || empty($data['contactid'])) {
                $data['contactid'] = '';
            }

            // Times <= Endtimes
            if($data['enddates']!== null && $data['enddates'] != ''){
                if($data['dates'] > $data['enddates']){
                    Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_EVENT_ERROR_END_BEFORE_START_DATES') . ' [ID:' . $data['id'] . ']', 'error');
                    return false;
                } else {
                    if($data['dates'] == $data['enddates']){
                        if($data['endtimes'] !== null && $data['endtimes'] != '') {
                            if ($data['times'] > $data['endtimes']) {
                                Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_EVENT_ERROR_END_BEFORE_START_TIMES') . ' [ID:' . $data['id'] . ']', 'error');
                                return false;
                            }
                        }
                    }
                }
            }

            // Get the fields changed
            $diff = array_diff_assoc($data, $eventdb);

            //If $diff contains some of fields (Defined in $fieldNotAllow) then dissolve recurrence and save again serie
            //If not, update de field of this event (save=false).
            $fieldNotAllow = ['recurrence_first_id', 'recurrence_number', 'recurrence_type', 'recurrence_counter', 'recurrence_limit', 'recurrence_limit_date', 'recurrence_byday', 'recurrence_bylastday'];
            foreach ($diff as $d => $value) {
                if (in_array($d, $fieldNotAllow)) {
                    // This event must be updated its fields
                    $data[$d] =  $value;
                    // Mark the event as root or new
                    $isInitialEvent = true;
                }
            }

            // If $isInitialEvent is true and recurrence_first_id != 0 then this event must be the first event of a new recurrence (series)
            if($isInitialEvent){
                if($eventdb['recurrence_first_id'] != 0) {

                    // Convert to root event
                    $data['recurrence_first_id'] = 0;

                    // Copy the recurrence data if it doesn't exist
                    if (!isset($data['recurrence_number'])) {
                        $data['recurrence_number'] = $eventdb['recurrence_number'];
                    }
                    if (!isset($data['recurrence_type'])) {
                        $data['recurrence_type'] = $eventdb['recurrence_type'];
                    }
                    if (!isset($data['recurrence_counter'])) {
                        $data['recurrence_counter'] = $eventdb['recurrence_counter'];
                    }
                    if (!isset($data['recurrence_limit'])) {
                        $data['recurrence_limit'] = $eventdb['recurrence_limit'];
                    }
                    if (!isset($data['recurrence_limit_date'])) {
                        $data['recurrence_limit_date'] = $eventdb['recurrence_limit_date'];
                    }
                    if (!isset($data['recurrence_byday'])) {
                        $data['recurrence_byday'] = $eventdb['recurrence_byday'];
                    }
                    if (!isset($data['recurrence_bylastday'])) {
                        $data['recurrence_bylastday'] = $eventdb['recurrence_bylastday'];
                    }
                }
            }else{
                // Get the recurrence_first_id for this recurrence event
                $data['recurrence_first_id'] = $eventdb ['recurrence_first_id'];
            }
        }

        // Set publish_down to null if they are empty (publish_up must have a datetime)
        if (empty($data['publish_down'])) {
            $data['publish_down'] = null;
        }

        // if the 'registra' field does not exist or is null, set it to the value from jem settings
        if(!isset($data['registra'])) {
            $data['registra'] =$jemsettings->showfroregistra;
        }

        // set to null if registration is empty
        if (empty($data['registra_from'])) {
            $data['registra_from'] = null;
        }
        if (empty($data['registra_until'])) {
            $data['registra_until'] = null;
        }
        if (empty($data['unregistra_until'])) {
            $data['unregistra_until'] = null;
        }
        if (empty($data['reginvitedonly'])) {
            $data['reginvitedonly'] = 0;
        }

        if($isInitialEvent) {
            // event maybe first of recurrence set -> dissolve complete set
            if (JemHelper::dissolve_recurrence($data['id'])) {
                $this->cleanCache();
            }

            if ($data['dates'] == null || $data['recurrence_type'] == '0') {
                $data['recurrence_number'] = '0';
                $data['recurrence_byday'] = '0';
                $data['recurrence_bylastday'] = '0';
                $data['recurrence_counter'] = '0';
                $data['recurrence_type'] = '0';
                $data['recurrence_limit'] = '0';
                $data['recurrence_limit_date'] = null;
                $data['recurrence_first_id'] = '0';
            } else {
                if (!$new) {
                    // edited event maybe part of a recurrence set
                    // -> drop event from set
                    $data['recurrence_first_id'] = '0';
                    $data['recurrence_counter'] = '0';
                }

                $data['recurrence_number'] = $recurrencenumber;
                $data['recurrence_byday'] = $recurrencebyday;
                $data['recurrence_bylastday'] = $recurrencebylastday;

                if (!empty($data['recurrence_limit_date']) && ($data['recurrence_limit_date'] != null)) {
                    $d = Factory::getDate($data['recurrence_limit_date'], 'UTC');
                    $data['recurrence_limit_date'] = $d->format('Y-m-d', true, false);
                }
            }

            $data['meta_keywords'] = $metakeywords;
            $data['meta_description'] = $metadescription;

            // Store IP of author only.
            if ($new) {
                $author_ip = $jinput->get('author_ip', '', 'string');
                $data['author_ip'] = $author_ip;
            }

            // Store as copy - reset creation date, modification fields, hit counter, version
            if ($task == 'save2copy') {
                unset($data['created']);
                unset($data['modified']);
                unset($data['modified_by']);
                unset($data['version']);
                unset($data['hits']);
            }

            // Save the event
            $saved = parent::save($data);

            if ($saved) {
                // At this point we do have an id.
                $pk = $this->getState($this->getName() . '.id');

                if (isset($data['featured'])) {
                    $this->featured($pk, $data['featured']);
                }

                // on frontend attachment uploads maybe forbidden
                // so allow changing name or description only
                $allowed = $backend || ($jemsettings->attachmentenabled > 0);

                if ($allowed) {
                    // attachments, new ones first
                    $attachments = $jinput->files->get('attach', array(), 'array');
                    $attach_name = $jinput->post->get('attach-name', array(), 'array');
                    $attach_descr = $jinput->post->get('attach-desc', array(), 'array');
                    $attach_access = $jinput->post->get('attach-access', array(), 'array');
                    $attach_order = $jinput->post->get('attach-order', array(), 'array');
                    $attach_frontend = $jinput->post->get('attach-frontend', array(), 'array');
                    foreach ($attachments as $n => &$a) {
                        $a['customname'] = array_key_exists($n, $attach_access) ? $attach_name[$n] : '';
                        $a['description'] = array_key_exists($n, $attach_access) ? $attach_descr[$n] : '';
                        $a['access'] = array_key_exists($n, $attach_access) ? $attach_access[$n] : '';
                        $a['ordering'] = array_key_exists($n, $attach_order) ? $attach_order[$n] : 0;
                        $a['frontend'] = array_key_exists($n, $attach_frontend) ? $attach_frontend[$n] : 1;
                    }
                    JemAttachment::postUpload($attachments, 'event' . $pk);
                }

                // Update existing attachments, but only when they belong to this event.
                $old = array();
                $old['id'] = $jinput->post->get('attached-id', array(), 'array');
                $old['name'] = $jinput->post->get('attached-name', array(), 'array');
                $old['description'] = $jinput->post->get('attached-desc', array(), 'array');
                $old['access'] = $jinput->post->get('attached-access', array(), 'array');
                $old['ordering'] = $jinput->post->get('attached-order', array(), 'array');
                $old['frontend'] = $jinput->post->get('attached-frontend', array(), 'array');

                foreach ($old['id'] as $k => $id) {
                    $attach = array();
                    $attach['id'] = $id;
                    $attach['name'] = $old['name'][$k] ?? '';
                    $attach['description'] = $old['description'][$k] ?? '';
                    $attach['ordering'] = $old['ordering'][$k] ?? 0;
                    if (array_key_exists($k, $old['frontend'])) {
                        $attach['frontend'] = $old['frontend'][$k];
                    }
                    if ($allowed && array_key_exists($k, $old['access'])) {
                        $attach['access'] = $old['access'][$k];
                    } // else don't touch this field
                    JemAttachment::update($attach, 'event' . $pk);
                }

                // Store cats
                if (!$this->_storeCategoriesSelected($pk, $cats, !$backend, $new)) {
                    //    JemHelper::addLogEntry('Error storing categories for event ' . $pk, __METHOD__, Log::ERROR);
                    $this->setError(Text::_('COM_JEM_EVENT_ERROR_STORE_CATEGORIES'));
                    $saved = false;
                }

                // Store invited users (frontend only, on backend no attendees on editevent view)
                if (!$backend && ($jemsettings->regallowinvitation == 1)) {
                    if (!$this->_storeUsersInvited($pk, $invitedusers, !$backend, $new)) {
                        //    JemHelper::addLogEntry('Error storing users invited for event ' . $pk, __METHOD__, Log::ERROR);
                        $this->setError(Text::_('COM_JEM_EVENT_ERROR_STORE_INVITED_USERS'));
                        $saved = false;
                    }
                }

                // check for recurrence
                // when filled it will perform the cleanup function
                $table->load($pk);
                if (($table->recurrence_number > 0) && ($table->dates != null)) {
                    JemHelper::cleanup(2); // 2 = force on save, needs special attention
                }

                // Store links event
                if (isset($data['event_links']))
                {
                    if (!$this->validateLinkData($data['event_links']))
                    {
                        $this->setError(Text::_('COM_JEM_EVENT_ERROR_VALIDATE_LINKS'));
                        return false;
                    }

                    if (!$this->saveLinks($pk, $data['event_links']))
                    {
                        $this->setError(Text::_('COM_JEM_EVENT_ERROR_SAVE_LINKS'));
                        return false;
                    }
                }

                $this->createAssociatedArticleIfRequested($pk, $data, $cats, $createArticleMode, $new, $articleTargetCategoryId);
            }
        } else {
            // This event is part of a recurrence series. Check if it is the root event to apply changes to all occurrences in the series.
            if (!$data["recurrence_first_id"]) {
                // the event is root
                $events = [];
                // retrieve all recurrence events associated with this root ID
                $allRecurrenceEvents = $this->getListRecurrenceEventsbyId($data['id'], $data['id'], time());

                // convert them to an array of objects each event and update the events with the data fields
                foreach ($allRecurrenceEvents as $recurrenceEvent){
                    $event = (array) $recurrenceEvent;
                    // update only the fields that were changed
                    foreach ($diff as $field => $value) {
                        if (array_key_exists($field, $event)) {
                            $event[$field] = $value;
                        }
                    }
                    $events[] = $event;
                }
            } else {
                // the event is a child
                $events[] = $data;
            }

            //Fields allowed to update
            $fieldAllow = ['title', 'locid', 'cats', 'dates', 'enddates', 'times', 'endtimes', 'title', 'alias', 'modified', 'modified_by', 'version', 'author_ip', 'created', 'introtext', 'meta_keywords', 'meta_description', 'datimage', 'checked_out', 'checked_out_time', 'registra', 'registra_from', 'registra_until', 'reginvitedonly', 'unregistra', 'unregistra_until', 'maxplaces', 'minbookeduser', 'maxbookeduser', 'reservedplaces', 'waitinglist', 'requestanswer', 'seriesbooking', 'singlebooking', 'published', 'event_status', 'ticket_availability', 'type_id', 'article_id', 'online_meeting_url', 'online_meeting_label', 'contactid', 'custom1', 'custom2', 'custom3', 'custom4', 'custom5', 'custom6', 'custom7', 'custom8', 'custom9', 'custom10', 'fulltext', 'created_by_alias', 'access', 'featured', 'language'];
            $saved = false;

            // get the fields update
            foreach ($events as $event) {
                $fieldsupdated = "";

                // save the event
                $saved = parent::save($event);

                if ($saved){
                    foreach ($diff as $d => $value) {
                        // update only the fields that were changed
                        if (in_array($d, $fieldAllow)) {
                            $this->updateField($data['id'], $d, $value);
                            $fieldsupdated = $fieldsupdated . ($fieldsupdated != '' ? ', ' : '') . $d;
                        }
                    }
                    if ($fieldsupdated != '') {
                        Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_EVENT_FIELDS_EVENT_UPDATED') . ' ' . $fieldsupdated . ' [ID:' . $event['id'] . ']', 'info');
                    }
                } else {
                    Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_EVENT_ERROR_EVENT_UPDATED') . ' ' . implode(", ", array_keys($diff)) . ' [ID:' . $event['id'] . ']', 'info');
                }
            }

            $table->load($data['id']);
            if (isset($table->id)) {
                $this->setState($this->getName() . '.id', $table->id);
            }

            if ($saved) {
                $this->createAssociatedArticleIfRequested((int) $table->id, $data, $cats, $createArticleMode, $new, $articleTargetCategoryId);
            }

            // Update  and new attachment file
            $allowed = $backend || ($jemsettings->attachmentenabled > 0);

            if ($allowed) {
                // attachments, new ones first
                $attachments = $jinput->files->get('attach', array(), 'array');
                $attach_name = $jinput->post->get('attach-name', array(), 'array');
                $attach_descr = $jinput->post->get('attach-desc', array(), 'array');
                $attach_access = $jinput->post->get('attach-access', array(), 'array');
                $attach_order = $jinput->post->get('attach-order', array(), 'array');
                $attach_frontend = $jinput->post->get('attach-frontend', array(), 'array');
                foreach ($attachments as $n => &$a) {
                    $a['customname'] = array_key_exists($n, $attach_access) ? $attach_name[$n] : '';
                    $a['description'] = array_key_exists($n, $attach_access) ? $attach_descr[$n] : '';
                    $a['access'] = array_key_exists($n, $attach_access) ? $attach_access[$n] : '';
                    $a['ordering'] = array_key_exists($n, $attach_order) ? $attach_order[$n] : 0;
                    $a['frontend'] = array_key_exists($n, $attach_frontend) ? $attach_frontend[$n] : 1;
                }
                JemAttachment::postUpload($attachments, 'event' . $this->eventid);
            }

            // and update old ones
            $old = array();
            $old['id'] = $jinput->post->get('attached-id', array(), 'array');
            $old['name'] = $jinput->post->get('attached-name', array(), 'array');
            $old['description'] = $jinput->post->get('attached-desc', array(), 'array');
            $old['access'] = $jinput->post->get('attached-access', array(), 'array');
            $old['ordering'] = $jinput->post->get('attached-order', array(), 'array');
            $old['frontend'] = $jinput->post->get('attached-frontend', array(), 'array');

            foreach ($old['id'] as $k => $id) {
                $attach = array();
                $attach['id'] = $id;
                $attach['name'] = $old['name'][$k] ?? '';
                $attach['description'] = $old['description'][$k] ?? '';
                $attach['ordering'] = $old['ordering'][$k] ?? 0;
                if (array_key_exists($k, $old['frontend'])) {
                    $attach['frontend'] = $old['frontend'][$k];
                }
                if ($allowed && array_key_exists($k, $old['access'])) {
                    $attach['access'] = $old['access'][$k];
                } // else don't touch this field
                JemAttachment::update($attach, 'event' . $this->eventid);
            }
        }

        if ($saved) {
            $stateName = $this->getName();
            $savedId   = (int) $this->getState($stateName . '.id');

            if (!$savedId && !empty($data['id'])) {
                $savedId = (int) $data['id'];
            }

            if ($savedId) {
                $this->setState('event.id', $savedId);
                $this->setState($stateName . '.id', $savedId);
            }

            $this->setState('event.new', $new);
            $this->setState($stateName . '.new', $new);
        }

        return $saved;
    }

    /**
     * Create and associate an unpublished Joomla article when requested.
     *
     * @param   integer       $eventId     Event id.
     * @param   array         $eventData   Event data being saved.
     * @param   array|string  $categories  Selected JEM category ids.
     * @param   integer       $mode        0 disabled, 1 manual create, 2 category auto.
     * @param   boolean       $new         True when saving a new event.
     * @param   integer       $targetId    Selected Joomla category id.
     *
     * @return  boolean
     */
    protected function createAssociatedArticleIfRequested($eventId, array $eventData, $categories, $mode, $new, $targetId = 0)
    {
        $eventId = (int) $eventId;
        $mode    = (int) $mode;
        $targetId = (int) $targetId;

        if ($eventId <= 0 || $mode === 0 || !empty($eventData['article_id'])) {
            return true;
        }

        $articleCategoryId = $this->resolveAssociatedArticleCategory($categories, $mode, $new, $targetId);

        if (!$articleCategoryId) {
            return true;
        }

        $user = Factory::getApplication()->getIdentity();

        if (!$user->authorise('core.create', 'com_content.category.' . $articleCategoryId) && !$user->authorise('core.create', 'com_content')) {
            Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_EVENT_ARTICLE_CREATE_NO_PERMISSION'), 'warning');

            return true;
        }

        $articleId = $this->createAssociatedContentArticle($eventData, $articleCategoryId);

        if (!$articleId) {
            Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_EVENT_ARTICLE_CREATE_FAILED'), 'warning');

            return false;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $article = (object) array(
            'id'         => $eventId,
            'article_id' => $articleId
        );

        $db->updateObject('#__jem_events', $article, 'id');
        Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_EVENT_ARTICLE_CREATED'), 'message');

        return true;
    }

    /**
     * Resolve the Joomla category that may receive the associated article.
     *
     * @param   array|string  $categories  Selected JEM category ids.
     * @param   integer       $mode        1 manual create, 2 category auto.
     * @param   boolean       $new         True when saving a new event.
     * @param   integer       $targetId    Selected Joomla category id.
     *
     * @return  integer
     */
    protected function resolveAssociatedArticleCategory($categories, $mode, $new, $targetId = 0)
    {
        $mode = (int) $mode;
        $targetId = (int) $targetId;
        $associations = $this->getAssociatedArticleCategoryOptions($categories);

        if ($mode === 2) {
            if (!$new) {
                return 0;
            }

            $autoConfigured = array_filter($associations, static function ($category) {
                return (int) $category->article_create_mode === 1;
            });
            $auto = array_filter($associations, static function ($category) {
                return (int) $category->article_create_mode === 1 && (int) $category->article_category_id > 0;
            });

            if ($autoConfigured && !$auto) {
                Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_EVENT_ARTICLE_CREATE_NO_CATEGORY'), 'warning');

                return 0;
            }

            if (!$autoConfigured) {
                Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_EVENT_ARTICLE_AUTO_NOT_ENABLED'), 'warning');

                return 0;
            }

            return $this->resolveAssociatedArticleCategoryFromCandidates($auto, $targetId, true);
        }

        if ($mode === 1) {
            $manualConfigured = array_filter($associations, static function ($category) {
                return (int) $category->article_create_mode === 2;
            });
            $manual = array_filter($associations, static function ($category) {
                return (int) $category->article_create_mode === 2 && (int) $category->article_category_id > 0;
            });

            if (!$manualConfigured) {
                Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_EVENT_ARTICLE_CREATE_CATEGORY_NOT_ALLOWED'), 'warning');

                return 0;
            }

            if ($manual) {
                return $this->resolveAssociatedArticleCategoryFromCandidates($manual, $targetId, false);
            }

            if (!$targetId) {
                Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_EVENT_ARTICLE_CREATE_SELECT_CATEGORY'), 'warning');

                return 0;
            }

            if (!$this->contentCategoryExists($targetId)) {
                Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_EVENT_ARTICLE_CREATE_SELECT_CATEGORY'), 'warning');

                return 0;
            }

            return $targetId;
        }

        return 0;
    }

    /**
     * Resolve one Joomla category from the selected JEM category associations.
     *
     * @param   array    $candidates  Candidate JEM categories.
     * @param   integer  $targetId    Selected Joomla category id.
     * @param   boolean  $auto        True when resolving automatic creation.
     *
     * @return  integer
     */
    protected function resolveAssociatedArticleCategoryFromCandidates(array $candidates, $targetId, $auto)
    {
        $articleCategoryIds = array_values(array_unique(array_map('intval', array_column($candidates, 'article_category_id'))));

        if (!$articleCategoryIds) {
            if (!$auto) {
                Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_EVENT_ARTICLE_CREATE_NO_CATEGORY'), 'warning');
            }

            return 0;
        }

        if (count($articleCategoryIds) === 1) {
            return (int) $articleCategoryIds[0];
        }

        if ($targetId && in_array((int) $targetId, $articleCategoryIds, true)) {
            return (int) $targetId;
        }

        Factory::getApplication()->enqueueMessage(Text::_('COM_JEM_EVENT_ARTICLE_CREATE_SELECT_ASSOCIATION'), 'warning');

        return 0;
    }

    /**
     * Get selected JEM categories with their associated Joomla categories.
     *
     * @param   array|string  $categories  Selected JEM category ids.
     *
     * @return  array
     */
    protected function getAssociatedArticleCategoryOptions($categories)
    {
        if (is_string($categories)) {
            $categories = explode(',', $categories);
        }

        if (!is_array($categories)) {
            return array();
        }

        $categoryIds = array_values(array_unique(array_filter(array_map('intval', $categories))));

        if (!$categoryIds) {
            return array();
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select(array(
                $db->quoteName('id'),
                $db->quoteName('article_category_id'),
                $db->quoteName('article_create_mode')
            ))
            ->from($db->quoteName('#__jem_categories'))
            ->where($db->quoteName('id') . ' IN (' . implode(',', $categoryIds) . ')');

        try {
            $db->setQuery($query);
            $categoryMap = $db->loadObjectList('id') ?: array();
        } catch (RuntimeException $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'warning');

            return array();
        }

        $categories = array();

        foreach ($categoryIds as $categoryId) {
            if (empty($categoryMap[$categoryId])) {
                continue;
            }

            $categories[] = $categoryMap[$categoryId];
        }

        return $categories;
    }

    /**
     * Check whether a selected existing article is valid for the selected JEM categories.
     *
     * @param   integer       $articleId   Joomla article id.
     * @param   array|string  $categories  Selected JEM category ids.
     *
     * @return  boolean
     */
    protected function validateAssociatedArticleSelection($articleId, $categories)
    {
        $articleId = (int) $articleId;

        if ($articleId <= 0) {
            return true;
        }

        $articleCategoryId = $this->getContentArticleCategoryId($articleId);

        if (!$articleCategoryId) {
            $this->setError(Text::_('COM_JEM_EVENT_ARTICLE_CREATE_CATEGORY_NOT_ALLOWED'));

            return false;
        }

        $associations = $this->getAssociatedArticleCategoryOptions($categories);
        $activeAssociations = array_filter($associations, static function ($category) {
            return (int) $category->article_create_mode !== 0 && (int) $category->article_category_id > 0;
        });
        $autoAssociations = array_filter($associations, static function ($category) {
            return (int) $category->article_create_mode === 1;
        });

        if ($autoAssociations) {
            $this->setError(Text::_('COM_JEM_EVENT_ARTICLE_CREATE_CATEGORY_NOT_ALLOWED'));

            return false;
        }

        if (!$activeAssociations) {
            $manualAllowed = array_filter($associations, static function ($category) {
                return (int) $category->article_create_mode === 2;
            });

            return (bool) $manualAllowed;
        }

        $allowedCategoryIds = array_values(array_unique(array_map('intval', array_column($activeAssociations, 'article_category_id'))));

        if (!in_array($articleCategoryId, $allowedCategoryIds, true)) {
            $this->setError(Text::_('COM_JEM_EVENT_ARTICLE_CREATE_CATEGORY_NOT_ALLOWED'));

            return false;
        }

        return true;
    }

    /**
     * Get the category id of a Joomla article.
     *
     * @param   integer  $articleId  Joomla article id.
     *
     * @return  integer
     */
    protected function getContentArticleCategoryId($articleId)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName('catid'))
            ->from($db->quoteName('#__content'))
            ->where($db->quoteName('id') . ' = ' . (int) $articleId);

        try {
            $db->setQuery($query);

            return (int) $db->loadResult();
        } catch (RuntimeException $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'warning');
        }

        return 0;
    }

    /**
     * Check whether a Joomla article category exists.
     *
     * @param   integer  $categoryId  Joomla category id.
     *
     * @return  boolean
     */
    protected function contentCategoryExists($categoryId)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__categories'))
            ->where($db->quoteName('id') . ' = ' . (int) $categoryId)
            ->where($db->quoteName('extension') . ' = ' . $db->quote('com_content'))
            ->where($db->quoteName('published') . ' IN (0,1)');

        try {
            $db->setQuery($query);

            return (int) $db->loadResult() > 0;
        } catch (RuntimeException $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'warning');
        }

        return false;
    }

    /**
     * Create an unpublished com_content article through Joomla's content model.
     *
     * @param   array    $eventData          Event data.
     * @param   integer  $articleCategoryId  Joomla article category id.
     *
     * @return  integer
     */
    protected function createAssociatedContentArticle(array $eventData, $articleCategoryId)
    {
        $app = Factory::getApplication();

        try {
            $component = $app->bootComponent('com_content');
            $factory = $component->getMVCFactory();
            $model = $factory->createModel('Article', 'Administrator', array('ignore_request' => true));
        } catch (Throwable $e) {
            $this->setError($e->getMessage());

            return 0;
        }

        if (!$model) {
            return 0;
        }

        $user = $app->getIdentity();
        $title = trim((string) ($eventData['title'] ?? ''));
        $categoryAccess = 1;

        try {
            $db = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true)
                ->select($db->quoteName('access'))
                ->from($db->quoteName('#__categories'))
                ->where($db->quoteName('id') . ' = ' . (int) $articleCategoryId)
                ->where($db->quoteName('extension') . ' = ' . $db->quote('com_content'));
            $db->setQuery($query);
            $categoryAccess = (int) $db->loadResult() ?: 1;
        } catch (Throwable $e) {
            $categoryAccess = 1;
        }

        $article = array(
            'id'          => 0,
            'catid'       => (int) $articleCategoryId,
            'title'       => $title,
            'alias'       => '',
            'introtext'   => '',
            'fulltext'    => '',
            'state'       => 0,
            'access'      => $categoryAccess,
            'language'    => !empty($eventData['language']) ? $eventData['language'] : '*',
            'created_by'  => (int) $user->id,
            'metadata'    => array(),
            'attribs'     => array()
        );

        try {
            if (!$model->save($article)) {
                $this->setError($model->getError());

                return 0;
            }
        } catch (Throwable $e) {
            $this->setError($e->getMessage());

            return 0;
        }

        return (int) $model->getState($model->getName() . '.id');
    }


    /**
     * Security validation for online meeting fields.
     *
     * @param   array  &$data  Event data
     * @return  bool           True if valid
     */
    public function validateOnlineMeetingData(&$data)
    {
        $url = isset($data['online_meeting_url']) ? trim((string) $data['online_meeting_url']) : '';
        $label = isset($data['online_meeting_label']) ? trim((string) $data['online_meeting_label']) : '';

        $data['online_meeting_url'] = $url;
        $data['online_meeting_label'] = $label;

        if ($url === '') {
            return true;
        }

        $urlScheme = parse_url($url, PHP_URL_SCHEME);
        if (!$urlScheme || !in_array(strtolower($urlScheme), array('http', 'https'), true)) {
            $this->setError(Text::sprintf('COM_JEM_EVENT_ERROR_UNSAFE_PROTOCOL', $urlScheme ?: 'none'));
            return false;
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $this->setError(Text::sprintf('COM_JEM_EVENT_ERROR_INVALID_URL', htmlspecialchars($url, ENT_QUOTES, 'UTF-8')));
            return false;
        }

        if (StringHelper::strlen($label) > 255) {
            $this->setError(Text::_('COM_JEM_EVENT_ERROR_ONLINE_MEETING_LABEL_TOO_LONG'));
            return false;
        }

        return true;
    }

    /**
     * Security validation for the link data.
     *
     * @param   array  $data  The links data
     * @return  bool          True if valid
     */
    public function validateLinkData($data)
    {
        if (empty($data)) return true;

        $jemsettings = JemHelper::config();

        // Get allowed image extensions from global configuration (defaults: jpg,jpeg,png,gif,webp,svg)
        $allowedExts = explode(',', str_replace(' ', '', $jemsettings->globalattribs->allowed_link_extensions ?? 'jpg,jpeg,png,gif,webp,svg'));

        // Get allowed URL schemes from global configuration (defaults: http,https,mailto,tel)
        $allowedSchemes = explode(',', str_replace(' ', '', $jemsettings->globalattribs->allowed_link_schemes ?? 'http,https,mailto,tel'));

        // Iterate through each link in the data array
        foreach ($data as $link) {
            $url = trim($link['url'] ?? '');
            $title = isset($link['title']) ? trim((string) $link['title']) : '';
            $icon = isset($link['icon']) ? trim((string) $link['icon']) : '';
            $image = isset($link['image']) ? trim((string) $link['image']) : '';

            // Description size validation
            $description = isset($link['description']) ? trim((string) $link['description']) : '';
            if (StringHelper::strlen($description) > 255) {
                $this->setError(Text::_('COM_JEM_EVENT_ERROR_LINK_DESCRIPTION_TOO_LONG'));
                return false;
            }

            if ($url === '' && $title === '' && $description === '' && $icon === '' && $image === '') {
                continue;
            }

            // Empty URL means "display without an active link".
            if ($url === '') {
                continue;
            }

            // URL scheme validation (check protocol is allowed)
            $urlScheme = parse_url($url, PHP_URL_SCHEME);
            if (!$urlScheme || !in_array(strtolower($urlScheme), $allowedSchemes)) {
                $this->setError(Text::sprintf('COM_JEM_EVENT_ERROR_UNSAFE_PROTOCOL', $urlScheme ?: 'none'));
                return false;
            }

            // Validate URL format using PHP's filter_var
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                $this->setError(Text::sprintf('COM_JEM_EVENT_ERROR_INVALID_URL', htmlspecialchars($url)));
                return false;
            }

            // Image validation (only for local images, not external URLs)
            if (!empty($link['image'])) {
                $img = $image;

                // Extract only the path, removing Joomla media query strings (e.g., ?width=...)
                $imgCleanPath = parse_url($img, PHP_URL_PATH);
                $cleanPath = Path::clean($imgCleanPath);

                // Security: Prevent directory traversal attacks (../)
                if (strpos($cleanPath, '..') !== false) {
                    $this->setError(Text::_('COM_JEM_EVENT_ERROR_UNSAFE_PATH'));
                    return false;
                }

                // Validate that the file extension is allowed
                $ext = strtolower(File::getExt($cleanPath));
                if (!in_array($ext, $allowedExts)) {
                    $this->setError(Text::sprintf('COM_JEM_EVENT_ERROR_INVALID_EXTENSION', $ext));
                    return false;
                }
            }
        }

        return true;
    }


    /**
     * Synchronizes links for an event: preserves 'created' and sets 'modified' only on updates.
     * Ordering is forced to integer to avoid SQL Error 1366.
     *
     * @param   int    $pk    The event ID
     * @param   array  $data  The links data from the subform
     * @return  bool          True on success
     */
    public function saveLinks($pk, $data)
    {
        $db   = $this->getDbo();
        $user = Factory::getUser();
        $now  = Factory::getDate()->toSql();

        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__jem_links'))
            ->where($db->quoteName('event_id') . ' = ' . (int) $pk)
            ->order($db->quoteName('ordering') . ' ASC');

        $db->setQuery($query);
        $existingIds = $db->loadColumn() ?: [];
        $keptIds = [];

        if (!empty($data)) {
            $rowOrder = 0;

            foreach ($data as $item) {
                $url = isset($item['url']) ? trim((string) $item['url']) : '';
                $title = isset($item['title']) ? trim((string) $item['title']) : '';
                $description = isset($item['description']) ? trim((string) $item['description']) : '';
                $icon = trim((string) ($item['icon'] ?? ''));
                $image = trim((string) ($item['image'] ?? ''));

                if ($url === '' && $title === '' && $description === '' && $icon === '' && $image === '') {
                    continue;
                }

                $link = new stdClass();

                $link->event_id = (int) $pk;
                $link->type     = $item['type'] ?? 'info';
                $link->title    = $title;
                $link->url      = $url;
                $link->ordering = (int) $rowOrder++;
                $link->state    = 1;
                $link->description = StringHelper::substr($description, 0, 255);

                // Normalize additional link configuration.
                $target = $item['target'] ?? '_blank';
                $target = in_array($target, ['_blank', '_self'], true) ? $target : '_blank';

                $color = trim((string) ($item['color'] ?? ''));

                if ($color !== '' && !preg_match('/^#[0-9a-f]{3,8}$/i', $color)) {
                    $color = '';
                }

                $frame = isset($item['frame']) ? (int) $item['frame'] : 0;
                $frame = $frame === 1 ? 1 : 0;

                $maxWidth = isset($item['max_width']) ? (int) $item['max_width'] : 0;
                $maxHeight = isset($item['max_height']) ? (int) $item['max_height'] : 0;

                $maxWidth = max(0, min($maxWidth, 2000));
                $maxHeight = max(0, min($maxHeight, 2000));

                // Store additional link configuration in params.
                $link->params = json_encode([
                    'target'       => $target,
                    'icon'         => $icon,
                    'image'        => $image,
                    'color'        => $color,
                    'frame'        => $frame,
                    'max_width'    => $maxWidth,
                    'max_height'   => $maxHeight,
                    'custom_class' => $item['custom_class'] ?? ''
                ]);

                if (!empty($item['id']) && in_array($item['id'], $existingIds)) {
                    $link->id          = (int) $item['id'];
                    $link->modified_by = $user->id;
                    $link->modified    = $now;

                    $db->updateObject('#__jem_links', $link, 'id');
                    $keptIds[] = $link->id;
                } else {
                    $link->created_by  = $user->id;
                    $link->created     = $now;
                    $link->modified_by = null;
                    $link->modified    = null;

                    $db->insertObject('#__jem_links', $link);
                }
            }
        }

        $toDelete = array_diff($existingIds, $keptIds);
        if (!empty($toDelete)) {
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__jem_links'))
                ->where($db->quoteName('id') . ' IN (' . implode(',', array_map('intval', $toDelete)) . ')');
            $db->setQuery($query);
            $db->execute();
        }

        return true;
    }


    /**
     * Method to get list recurrence events data.
     *
     * @param  int  The id of the event.
     * @param  int  The id of the parent event.
     * @return mixed  item data object on success, false on failure.
     */
    public function getListRecurrenceEventsbyId ($id, $pk, $datetimeFrom, $iduser=null, $status=null)
    {
        // Initialise variables.
        $pk = (!empty($pk)) ? $pk : (int) $this->getState('event.id');

        if ($this->_item === null) {
            $this->_item = array();
        }

        try
        {
            $settings = JemHelper::globalattribs();
            $user     = JemFactory::getUser();
            $levels   = $user->getAuthorisedViewLevels();

            $db    = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true);

            # Event
            $query->select(
                $this->getState('item.select',
                    'a.id, a.id AS did, a.title, a.alias, a.dates, a.enddates, a.times, a.endtimes, a.access, a.attribs, a.metadata, ' .
                    'a.custom1, a.custom2, a.custom3, a.custom4, a.custom5, a.custom6, a.custom7, a.custom8, a.custom9, a.custom10, ' .
                    'a.created, a.created_by, a.published, a.registra, a.registra_from, a.registra_until, a.unregistra, a.unregistra_until, ' .
                    'CASE WHEN a.modified = 0 THEN a.created ELSE a.modified END as modified, a.modified_by, ' .
                    'a.checked_out, a.checked_out_time, a.datimage,  a.version, a.featured, ' .
                    'a.seriesbooking, a.singlebooking, a.meta_keywords, a.meta_description, a.created_by_alias, a.introtext, a.fulltext, a.maxplaces, a.reservedplaces, a.minbookeduser, a.maxbookeduser, a.waitinglist, a.requestanswer, ' .
                    'a.hits, a.language, a.recurrence_type, a.recurrence_first_id' . ($iduser? ', r.waiting, r.places, r.status':'')))    ;
            $query->from('#__jem_events AS a');

            $dateFrom = date('Y-m-d', $datetimeFrom);
            $timeFrom = date('H:i:s', $datetimeFrom);
            $query->where('((a.recurrence_first_id = 0 AND a.id = ' . (int)($pk?$pk:$id) . ') OR a.recurrence_first_id = ' . (int)($pk?$pk:$id) . ')');
            $query->where("(a.dates > '" . $dateFrom . "' OR a.dates = '" . $dateFrom . "' AND dates >= '" . $timeFrom . "')");
            $query->order('a.dates ASC');

            $db->setQuery($query);
            $data = $db->loadObjectList();
        }
        catch (Exception $e)
        {
            $this->setError($e);
            return false;
        }

        return $data;
    }


    /**
     * Get event all data
     *
     * @access public
     * @return object
     */
    public function getEventAllData()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select('*');
        $query->from('#__jem_events');
        $query->where('id = '.$db->Quote($this->eventid));
        $db->setQuery( $query );
        $event = $db->loadObject();

        return $event;
    }


    /**
     * Get categories of event
     *
     * @access public
     * @return string
     */
    public function getEventCats()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);
        $query->select('GROUP_CONCAT(catid) as cats');
        $query->from('#__jem_cats_event_relations');
        $query->where('itemid = '.$db->Quote($this->eventid));
        $db->setQuery( $query );
        $cats = $db->loadResult();

        return $cats;
    }



    /**
     * Method to update cats_event_selections table.
     * Records of previously selected categories will be removed
     * and newly selected categories will be stored.
     * Because user may not have permissions for all categories on frontend
     * records with non-permitted categories will be untouched.
     *
     * @param  int     The event id.
     * @param  array   The categories user has selected.
     * @param  bool    Flag to indicate if we are on frontend
     * @param  bool    Flag to indicate new event
     *
     * @return boolean True on success.
     */
    protected function _storeCategoriesSelected($eventId, $categories, $frontend, $new)
    {
        $user = JemFactory::getUser();
        $db   = Factory::getContainer()->get('DatabaseDriver');

        $eventId = (int)$eventId;
        if (empty($eventId) || !is_array($categories)) {
            return false;
        }

        // get previous entries
        $query = $db->getQuery(true);
        $query->select('catid')
            ->from('#__jem_cats_event_relations')
            ->where('itemid = ' . $eventId)
            ->order('catid');
        $db->setQuery($query);
        $cur_cats = $db->loadColumn();

        if (!is_array($cur_cats)) {
            return false;
        }

        $ret = true;
        $del_cats = array_diff($cur_cats, $categories);
        $add_cats = array_diff($categories, $cur_cats);

        /* Attention!
         *  On frontend user maybe not permitted to see all categories attached.
         *  But these categories must not removed from this event!
         */
        if ($frontend) {
            // Note: JFormFieldCatOptions calls the same function to know which categories user is allowed (un)select.
            $limit_cats = array_keys($user->getJemCategories($new ? array('add') : array('add', 'edit'), 'event'));
            $del_cats = array_intersect($del_cats, $limit_cats);
            $add_cats = array_intersect($add_cats, $limit_cats);
        }

        if (!empty($del_cats)) {
            $query = $db->getQuery(true);
            $query->delete($db->quoteName('#__jem_cats_event_relations'));
            $query->where('itemid = ' . $eventId);
            $query->where('catid IN (' . implode(',', $del_cats) . ')');
            $db->setQuery($query);
            $ret &= ($db->execute() !== false);
        }

        if (!empty($add_cats)) {
            $query = $db->getQuery(true);
            $query->insert($db->quoteName('#__jem_cats_event_relations'))
                ->columns($db->quoteName(array('catid', 'itemid','ordering')));
            foreach ($add_cats as $catid) {
                $query->values((int)$catid . ',' . $eventId.','.'0');
            }
            $db->setQuery($query);
            $ret &= ($db->execute() !== false);
        }

        return $ret;
    }

    /**
     * Method to update cats_event_selections table.
     * Records of previously selected categories will be removed
     * and newly selected categories will be stored.
     * Because user may not have permissions for all categories on frontend
     * records with non-permitted categories will be untouched.
     *
     * @param  int     The event id.
     * @param  mixed   The user ids as array or comma separated string.
     * @param  bool    Flag to indicate if we are on frontend
     * @param  bool    Flag to indicate new event
     *
     * @return boolean True on success.
     */
    protected function _storeUsersInvited($eventId, $users, $frontend, $new)
    {
        $eventId = (int)$eventId;
        if (!is_array($users)) {
            $users = explode(',', $users);
        }
        $users = array_unique($users);
        $users = array_filter($users);

        if (empty($eventId)) {
            return false;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        # Get current registrations
        $query = $db->getQuery(true);
        $query->select(array('reg.id, reg.uid, reg.status, reg.waiting'));
        $query->from('#__jem_register As reg');
        $query->where('reg.event = ' . $eventId);
        $db->setQuery($query);
        $regs = $db->loadObjectList('uid');

        PluginHelper::importPlugin('jem');
        $dispatcher = JemFactory::getDispatcher();

        # Add new records, ignore users already registered
        foreach ($users AS $user)
        {
            if (!array_key_exists($user, $regs)) {
                $query = $db->getQuery(true);
                $query->insert('#__jem_register');
                $query->columns(array('event', 'uid', 'status'));
                $query->values($eventId.','.$user.',0');
                $db->setQuery($query);
                try {
                    $ret = $db->execute();
                } catch (Exception $e) {
                    JemHelper::addLogEntry('Exception: '. $e->getMessage(), __METHOD__, Log::ERROR);
                    $ret = false;
                }

                if ($ret !== false) {
                    $id = $db->insertid();
                    $dispatcher->triggerEvent('onEventUserRegistered', array($id));
                }
            }
        }

        # Remove obsolete invitations
        foreach ($regs as $reg)
        {
            if (($reg->status == 0) && (array_search($reg->uid, $users) === false)) {
                $query = $db->getQuery(true);
                $query->delete('#__jem_register');
                $query->where('id = '.$reg->id);
                $db->setQuery($query);
                try {
                    $ret = $db->execute();
                } catch (Exception $e) {
                    JemHelper::addLogEntry('Exception: '. $e->getMessage(), __METHOD__, Log::ERROR);
                    $ret = false;
                }

                if ($ret !== false) {
                    $dispatcher->triggerEvent('onEventUserUnregistered', array($eventId, $reg));
                }
            }
        }

        $cache = Factory::getCache('com_jem');
        $cache->clean();

        return true;
    }

    /**
     * Method to toggle the featured setting of articles.
     *
     * @param  array   The ids of the items to toggle.
     * @param  int     The value to toggle to.
     *
     * @return boolean True on success.
     */
    public function featured($pks, $value = 0)
    {
        // Sanitize the ids.
        $pks = (array)$pks;
        ArrayHelper::toInteger($pks);

        if (empty($pks)) {
            $this->setError(Text::_('COM_JEM_EVENTS_NO_ITEM_SELECTED'));
            return false;
        }

        try {
            $db = Factory::getContainer()->get('DatabaseDriver');

            $db->setQuery(
                'UPDATE #__jem_events' .
                ' SET featured = '.(int) $value.
                ' WHERE id IN ('.implode(',', $pks).')'
            );
            $db->execute() ;

        } catch (Exception $e) {
            $this->setError($e->getMessage());
            return false;
        }

        $this->cleanCache();

        return true;
    }

    /**
     * Method to update the field in the events table.
     *
     * @param  int     The id of event.
     * @param  string  The field of event table.
     * @param  string  The value of field (to update).
     *
     * @return boolean True on success.
     */
    public function updateField($eventid, $field, $value)
    {
        // Sanitize the ids.
        $eventid = (int)$eventid;

        if (empty($eventid)) {
            $this->setError(Text::_('COM_JEM_EVENTS_NO_ITEM_SELECTED'));
            return false;
        }

        try {
            $db = Factory::getContainer()->get('DatabaseDriver');
            if($field == 'cats'){
                $cats = explode (',', $value);

                // Delete all old categories for id event
                $db->setQuery('DELETE FROM #__jem_cats_event_relations WHERE itemid = ' . $db->quote($eventid) );
                $db->execute();

                // Insert new categories for id event
                foreach($cats as $c){
                    $db->setQuery('INSERT INTO #__jem_cats_event_relations (catid, itemid, ordering) VALUES  (' . (int) $c . ',' . $db->quote($eventid) . ',0)');
                    $db->execute();
                }
            } else {
                // Update the value of field into events table
                $db->setQuery('UPDATE #__jem_events SET ' . $field . ' = ' . ($value!==null ? $db->quote($value) : 'null') . ' WHERE id = ' . $db->quote($eventid));
                $db->execute();
            }

        } catch (Exception $e) {
            $this->setError($e->getMessage());
            return false;
        }

        $this->cleanCache();
        return true;
    }
}
