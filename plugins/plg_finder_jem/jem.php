<?php
/**
 * @version    4.1.0
 * @package    JEM
 * @subpackage JEM Finder Plugin
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

jimport('joomla.application.component.helper');

// Load the base adapter.
require_once JPATH_ADMINISTRATOR . '/components/com_finder/helpers/indexer/adapter.php';

/**
 * Finder adapter for com_jem.
 *
 * @package    Joomla
 * @subpackage Finder.jem
 *
 */

use Joomla\CMS\Component\ComponentHelper;
use Joomla\Component\Finder\Administrator\Indexer\Adapter;
use Joomla\Component\Finder\Administrator\Indexer\Helper;
use Joomla\Component\Finder\Administrator\Indexer\Result;
use Joomla\Registry\Registry;

class plgFinderJEM extends Adapter
{
    /**
     * The plugin identifier.
     *
     * @var    string
     *
     */
    protected $context = 'JEM';

    /**
     * The extension name.
     *
     * @var    string
     *
     */
    protected $extension = 'com_jem';

    /**
     * The sublayout to use when rendering the results.
     *
     * @var    string
     *
     */
    protected $layout = 'event';

    /**
     * The type of content that the adapter indexes.
     *
     * @var    string
     *
     */
    protected $type_title = 'Event';

    /**
     * The table name.
     *
     * @var    string
     *
     */
    protected $table = '#__jem_events';

    /**
     * The state field.
     *
     * @var    string
     *
     */
    protected $state_field = 'published';

    /**
     * Indicates Joomla! version (2, 3, or 0).
     *
     * @var    integer
     *
     */
    protected $jVer = 0;

    /**
     * Constructor
     *
     * @param   object  &$subject  The object to observe
     * @param   array    $config   An array that holds the plugin configuration
     *
     */
    public function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);
        $this->loadLanguage(); // we don't use $this->autoloadLanguage available since 3.1

        if (empty($this->jVer)) {
            $this->jVer = (!empty($this->indexer) && method_exists($this->indexer, 'index')) ? 3 : 2;
        }
    }

    /**
     * Method to update the item link information when the item category is
     * changed. This is fired when the item category is published or unpublished
     * from the list view.
     *
     * @param   string   $extension  The extension whose category has been updated.
     * @param   array    $pks        A list of primary key ids of the content that has changed state.
     * @param   integer  $value      The value of the state that the content has been changed to.
     *
     * @return  void
     *
     */
    public function onFinderCategoryChangeState($extension, $pks, $value)
    {
        // Make sure we're handling com_jem categories
        if ($extension == 'com_jem') {
            $this->categoryStateChange($pks, $value);
        }
    }

    /**
     * Method to remove the link information for items that have been deleted.
     *
     * @param   string  $context  The context of the action being performed.
     * @param   JTable  $table    A JTable object containing the record to be deleted
     *
     * @return  boolean  True on success.
     *
     * @throws  Exception on database error.
     * @since   2.5
     */
    public function onFinderAfterDelete($context, $table)
    {
        if ($context == 'com_jem.event') {
            $id = $table->id;
        } elseif ($context == 'com_finder.index') {
            $id = $table->link_id;
        } else {
            return true;
        }

        // Remove item from the index.
        return $this->remove($id);
    }

    /**
     * Method to determine if the access level of an item changed.
     *
     * @param   string   $context  The context of the content passed to the plugin.
     * @param   JTable   $row      A JTable object
     * @param   boolean  $isNew    If the content has just been created
     *
     * @return  boolean  True on success.
     *
     * @throws  Exception on database error.
     */
    public function onFinderAfterSave($context, $row, $isNew)
    {
        // We only want to handle events here
        if ($context == 'com_jem.event' || $context == 'com_jem.editevent') {
            // Check if the access levels are different
            if (!$isNew && $this->old_access != $row->access) {
                // Process the change.
                $this->itemAccessChange($row);
            }

            // Reindex the item
            $this->reindex($row->id);
        }

        // Check for access changes in the category
        if ($context == 'com_jem.category') {
            // Check if the access levels are different
            if (!$isNew && $this->old_cataccess != $row->access) {
                $this->categoryAccessChange($row);
            }
        }

        return true;
    }

    /**
     * Method to reindex the link information for an item that has been saved.
     * This event is fired before the data is actually saved so we are going
     * to queue the item to be indexed later.
     *
     * @param   string   $context  The context of the content passed to the plugin.
     * @param   JTable   $row      A JTable object
     * @param   boolean  $isNew    If the content is just about to be created
     *
     * @return  boolean  True on success.
     *
     * @throws  Exception on database error.
     */
    public function onFinderBeforeSave($context, $row, $isNew)
    {
        // We only want to handle articles here
        if ($context == 'com_jem.event' || $context == 'com_jem.editevent') {
            // Query the database for the old access level if the item isn't new
            if (!$isNew) {
                $this->checkItemAccess($row);
            }
        }
        // Check for access levels from the category
        if ($context == 'com_jem.category') {
            // Query the database for the old access level if the item isn't new
            if (!$isNew) {
                $this->checkCategoryAccess($row);
            }
        }

        return true;
    }

    /**
     * Method to update the link information for items that have been changed
     * from outside the edit screen. This is fired when the item is published,
     * unpublished, archived, or unarchived from the list view.
     *
     * @param   string   $context  The context for the content passed to the plugin.
     * @param   array    $pks      A list of primary key ids of the content that has changed state.
     * @param   integer  $value    The value of the state that the content has been changed to.
     *
     * @return  void
     *
     */
    public function onFinderChangeState($context, $pks, $value)
    {
        // We only want to handle articles here
        if ($context == 'com_jem.event' || $context == 'com_jem.editevent') {
            $this->itemStateChange($pks, $value);
        }
        // Handle when the plugin is disabled
        if ($context == 'com_plugins.plugin' && $value === 0) {
            $this->pluginDisable($pks);
        }
    }

    /**
     * Method to index an item. The item must be a FinderIndexerResult object.
     *
     * @param   FinderIndexerResult  $item    The item to index as an FinderIndexerResult object.
     * @param   string               $format  The item format
     *
     * @return  void
     *
     * @throws  Exception on database error.
     */
    // protected function index(FinderIndexerResult $item, $format = 'html')
    protected function index(Result $item)
    {
        // Check if the extension is enabled
        if (ComponentHelper::isEnabled($this->extension) == false) {
            return;
        }

        $item->setLanguage();

        // Initialize the item parameters.
        $registry = new JRegistry;
        $registry->loadString($item->params);
        $item->params = ComponentHelper::getParams('com_jem', true);
        $item->params->merge($registry);

        $registry = new Registry;
        $registry->loadString($item->metadata);
        $item->metadata = $registry;

        // Trigger the onContentPrepare event.
        $item->summary = Helper::prepareContent($item->summary, $item->params);
        $item->body    = Helper::prepareContent($item->fulltext, $item->params);

        // Build the necessary route and path information.
        $item->url   = $this->getURL($item->id, $this->extension, $this->layout);
        $item->route = JEMHelperRoute::getEventRoute($item->slug, $item->catslug);
        // $item->path = Helper::getContentPath($item->route);

        // Get the menu title if it exists.
        $title = $this->getItemMenuTitle($item->url);

        // Adjust the title if necessary.
        if (!empty($title) && $this->params->get('use_menu_title', true)) {
            $item->title = $title;
        }
        $item->metaauthor = !isset($item->metaauthor) ? '' : $item->metaauthor;
        // Add the meta-author.
        $item->metaauthor = $item->metadata->get('author');

        // Add the meta-data processing instructions.
        // TODO:
// 		$item->addInstruction(FinderIndexer::META_CONTEXT, 'meta_description');

        // Translate the state. Articles should only be published if the category is published.
        $item->state = $this->translateState($item->state, $item->cat_state);

        // Add the type taxonomy data.
        $item->addTaxonomy('Type', 'Event');

        // Add the author taxonomy data.
        if (!empty($item->author) || !empty($item->created_by_alias)) {
            $item->addTaxonomy('Author', !empty($item->created_by_alias) ? $item->created_by_alias : $item->author);
        }

        if (!$item->Category) {
            return true;
        }

        // Add the category taxonomy data.
        $item->addTaxonomy('Category', $item->category, $item->cat_state, $item->cat_access);

        // Add the language taxonomy data.
        $item->addTaxonomy('Language', $item->language);

        // Add the venue taxonomy data.
        if (!empty($item->venue)) {
            $item->addTaxonomy('Venue', $item->venue, $item->loc_published);
        }

        // Get content extras.
        Helper::getContentExtras($item);

        // Index the item.

        $this->indexer->index($item);
    }

    /**
     * Method to setup the indexer to be run.
     *
     * @return  boolean  True on success.
     *
     */
    protected function setup()
    {
        // Load dependent classes.
        include_once JPATH_SITE . '/components/com_jem/helpers/route.php';

        return true;
    }

    /**
     * Method to get the SQL query used to retrieve the list of events.
     *
     * @param   mixed  $sql  A JDatabaseQuery object or null.
     *
     * @return  JDatabaseQuery  A database object.
     *
     */
    protected function getListQuery($sql = null)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        // Check if we can use the supplied SQL query.
        $sql = $sql instanceof JDatabaseQuery ? $sql : $db->getQuery(true);

// 		$sql->select('a.id, a.title, a.alias, a.introtext AS summary, a.fulltext AS body');
// 		$sql->select('a.state, a.catid, a.created AS start_date, a.created_by');
// 		$sql->select('a.created_by_alias, a.modified, a.modified_by, a.attribs AS params');
// 		$sql->select('a.metakey, a.metadesc, a.metadata, a.language, a.access, a.version, a.ordering');
// 		$sql->select('a.publish_up AS publish_start_date, a.publish_down AS publish_end_date');
// 		$sql->select('c.title AS category, c.published AS cat_state, c.access AS cat_access');

        $sql->select('a.id, a.access, a.title, a.alias, a.dates, a.enddates, a.times, a.endtimes, a.datimage');
        $sql->select('a.created AS publish_start_date, a.dates AS start_date, a.enddates AS end_date');
        $sql->select('a.created_by, a.modified, a.version, a.published AS state');
        $sql->select('a.fulltext AS body, a.introtext AS summary');
        $sql->select('l.venue, l.city, l.state as loc_state, l.url, l.street');
        $sql->select('l.published AS loc_published');
        $sql->select('ct.name AS countryname');
        $sql->select('c.catname AS category, c.published AS cat_state, c.access AS cat_access');

        // Handle the alias CASE WHEN portion of the query
        $case_when_item_alias = ' CASE WHEN ';
        $case_when_item_alias .= $sql->charLength('a.alias');
        $case_when_item_alias .= ' THEN ';
        $a_id                 = $sql->castAsChar('a.id');
        $case_when_item_alias .= $sql->concatenate(array($a_id, 'a.alias'), ':');
        $case_when_item_alias .= ' ELSE ';
        $case_when_item_alias .= $a_id . ' END as slug';
        $sql->select($case_when_item_alias);

        $case_when_category_alias = ' CASE WHEN ';
        $case_when_category_alias .= $sql->charLength('c.alias');
        $case_when_category_alias .= ' THEN ';
        $c_id                     = $sql->castAsChar('c.id');
        $case_when_category_alias .= $sql->concatenate(array($c_id, 'c.alias'), ':');
        $case_when_category_alias .= ' ELSE ';
        $case_when_category_alias .= $c_id . ' END as catslug';
        $sql->select($case_when_category_alias);

        $case_when_venue_alias = ' CASE WHEN ';
        $case_when_venue_alias .= $sql->charLength('l.alias');
        $case_when_venue_alias .= ' THEN ';
        $l_id                  = $sql->castAsChar('l.id');
        $case_when_venue_alias .= $sql->concatenate(array($l_id, 'l.alias'), ':');
        $case_when_venue_alias .= ' ELSE ';
        $case_when_venue_alias .= $l_id . ' END as venueslug';
        $sql->select($case_when_venue_alias);

        $sql->from($this->table . ' AS a');
        $sql->join('LEFT', '#__jem_venues AS l ON l.id = a.locid');
        $sql->join('LEFT', '#__jem_countries AS ct ON ct.iso2 = l.country');
        $sql->join('LEFT', '#__jem_cats_event_relations AS cer ON cer.itemid = a.id');
        $sql->join('LEFT', '#__jem_categories AS c ON cer.catid = c.id');

        return $sql;
    }

    protected function getStateQuery()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        // Check if we can use the supplied SQL query.
        $sql = $db->getQuery(true);

        // Item ID
        $sql->select('a.id');
        // Item and category published state
        $sql->select($db->quoteName('a.' . $this->state_field, 'state'));
        $sql->select('c.published AS cat_state');
        // Item and category access levels
        $sql->select('1 AS access, c.access AS cat_access');
        $sql->from($db->quoteName($this->table, 'a'));
        $sql->join('LEFT', '#__jem_cats_event_relations AS cer ON cer.itemid = a.id');
        $sql->join('LEFT', '#__jem_categories AS c ON cer.catid = c.id');

        return $sql;
    }

    /**
     * Method to check the existing access level for categories
     *
     * @param   JTable  $row  A JTable object
     *
     * @return  void
     *
     */
    protected function checkCategoryAccess($row)
    {
        $query = $this->db->getQuery(true);
        $query->select($this->db->quoteName('access'));
        $query->from($this->db->quoteName('#__jem_categories'));
        $query->where($this->db->quoteName('id') . ' = ' . (int)$row->id);
        $this->db->setQuery($query);

        // Store the access level to determine if it changes
        $this->old_cataccess = $this->db->loadResult();
    }
}