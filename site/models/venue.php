<?php
/**
 * @version    4.2.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

require_once __DIR__ . '/eventslist.php';

/**
 * Model: venue
 */
class JemModelVenue extends JemModelEventslist
{
	/**
	 * Venue id
	 *
	 * @var int
	 */
	protected $_id = null;


	public function __construct()
	{
		$app    = Factory::getApplication();
		$jinput = $app->input;
		$params = $app->getParams();

		# determing the id to load
		if ($jinput->get('id',null,'int')) {
			$id = $jinput->get('id',null,'int');
		} else {
			$id = $params->get('id');
		}
		$this->setId((int)$id);

		parent::__construct();
	}

	/**
	 * Method to auto-populate the model state.
	 */
	protected function populateState($ordering = null, $direction = null)
	{
		$app         = Factory::getApplication();
		$jemsettings = JemHelper::config();
		$params      = $app->getParams();
		$jinput      = $app->input;
		$task        = $jinput->getCmd('task','');
		$itemid      = $jinput->getInt('id', 0) . ':' . $jinput->getInt('Itemid', 0);
		$user        = JemFactory::getUser();
		$format      = $jinput->getCmd('format',false);

		// List state information

		if (empty($format) || ($format == 'html')) {
			/* in J! 3.3.6 limitstart is removed from request - but we need it! */
			if ($app->input->getInt('limitstart', null) === null) {
				$app->setUserState('com_jem.venue.'.$itemid.'.limitstart', 0);
			}

			$limit = $app->getUserStateFromRequest('com_jem.venue.'.$itemid.'.limit', 'limit', $jemsettings->display_num, 'int');
			$this->setState('list.limit', $limit);

			$limitstart = $app->getUserStateFromRequest('com_jem.venue.'.$itemid.'.limitstart', 'limitstart', 0, 'int');
			// correct start value if required
			$limitstart = $limit ? (int)(floor($limitstart / $limit) * $limit) : 0;
			$this->setState('list.start', $limitstart);
		}

		# Search
		$search = $app->getUserStateFromRequest('com_jem.venue.'.$itemid.'.filter_search', 'filter_search', '', 'string');
		$this->setState('filter.filter_search', $search);

		# FilterType
		$filtertype = $app->getUserStateFromRequest('com_jem.venue.'.$itemid.'.filter_type', 'filter_type', 0, 'int');
		$this->setState('filter.filter_type', $filtertype);

		# filter_order
		$orderCol = $app->getUserStateFromRequest('com_jem.venue.'.$itemid.'.filter_order', 'filter_order', 'a.dates', 'cmd');
		$this->setState('filter.filter_ordering', $orderCol);

		# filter_direction
		$listOrder = $app->getUserStateFromRequest('com_jem.venue.'.$itemid.'.filter_order_Dir', 'filter_order_Dir', 'ASC', 'word');
		$this->setState('filter.filter_direction', $listOrder);

		# show open date events
		# (there is no menu item option yet so show all events)
		$this->setState('filter.opendates', 1);

		$defaultOrder = ($task == 'archive') ? 'DESC' : 'ASC';
		if ($orderCol == 'a.dates') {
			$orderby = array('a.dates ' . $listOrder, 'a.times ' . $listOrder, 'a.created ' . $listOrder);
		} else {
			$orderby = array($orderCol . ' ' . $listOrder,
			                 'a.dates ' . $defaultOrder, 'a.times ' . $defaultOrder, 'a.created ' . $defaultOrder);
		}
		$this->setState('filter.orderby', $orderby);

		# params
		$this->setState('params', $params);

		# publish state
		$this->_populatePublishState($task);

		$this->setState('filter.groupby',array('a.id'));
	}

	/**
	 * Method to get a list of events.
	 */
	public function getItems()
	{
		$items = parent::getItems();
		/* no additional things to do yet - place holder */
		if ($items) {
			return $items;
		}

		return array();
	}

	/**
	 * @return	JDatabaseQuery
	 */
	protected function getListQuery()
	{
		// Create a new query object.
		$query = parent::getListQuery();

		// here we can extend the query of the Eventslist model
		$query->where('a.locid = '.(int)$this->_id);

		return $query;
	}

	/**
	 * Method to set the venue id
	 *
	 * The venue-id can be set by a menu-parameter
	 */
	public function setId($id)
	{
		// Set new venue ID and wipe data
		$this->_id   = $id;
		//$this->_data = null;
	}

	/**
	 * set limit
	 * @param int value
	 */
	public function setLimit($value)
	{
		$this->setState('limit', (int) $value);
	}

	/**
	 * set limitstart
	 * @param int value
	 */
	public function setLimitStart($value)
	{
		$this->setState('limitstart', (int) $value);
	}

	/**
	 * Method to get a specific Venue
	 *
	 * @access public
	 * @return array
	 */
	public function getVenue()
	{
		$user   = JemFactory::getUser();

		$db = Factory::getContainer()->get('DatabaseDriver');
		$query  = $db->getQuery(true);

		$query->select('id, venue, published, city, state, url, street, custom1, custom2, custom3, custom4, custom5, '.
		               ' custom6, custom7, custom8, custom9, custom10, locimage, meta_keywords, meta_description, '.
		               ' created, created_by, locdescription, country, map, latitude, longitude, postalCode, checked_out AS vChecked_out, checked_out_time AS vChecked_out_time, '.
		               ' CASE WHEN CHAR_LENGTH(alias) THEN CONCAT_WS(\':\', id, alias) ELSE id END as slug');
		$query->from($db->quoteName('#__jem_venues'));
		$query->where('id = '.(int)$this->_id);

		// all together: if published or the user is creator of the venue or allowed to edit or publish venues
		if (empty($user->id)) {
			$query->where('published = 1');
		}
		// no limit if user can publish or edit foreign venues
		elseif ($user->can(array('edit', 'publish'), 'venue')) {
			$query->where('published IN (0,1)');
		}
		// user maybe creator
		else {
			$query->where('(published = 1 OR (published = 0 AND created_by = ' . $this->_db->Quote($user->id) . '))');
		}

		$db->setQuery($query);
		$_venue = $db->loadObject();

		if (empty($_venue)) {
			$this->setError(Text::_('COM_JEM_VENUE_ERROR_VENUE_NOT_FOUND'));
			return false;
		}

		$_venue->attachments = JemAttachment::getAttachments('venue'.$_venue->id);

		return $_venue;
	}
}
?>
