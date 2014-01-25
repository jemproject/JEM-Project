<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.model');

/**
 * JEM Component Venue Model
 *
 * @package JEM
 *
*/
class JEMModelVenue extends JModelLegacy
{
	/**
	 * Events data array
	 *
	 * @var array
	 */
	var $_data = null;

	/**
	 * venue data array
	 *
	 * @var array
	 */
	var $_venue = null;

	/**
	 * Events total
	 *
	 * @var integer
	 */
	var $_total = null;

	/**
	 * Pagination object
	 *
	 * @var object
	 */
	var $_pagination = null;

	/**
	 * Constructor
	 *
	 */
	function __construct()
	{
		parent::__construct();

		$app = JFactory::getApplication();
		$jemsettings = JEMHelper::config();
		$jinput = JFactory::getApplication()->input;
		$params = $app->getParams();

		$this->setdate(time());

		if ($jinput->get('id',null,'int')) {
			$id = $jinput->get('id',null,'int');
		} else {
			$id = $params->get('id');
		}

		$this->setId((int)$id);

		//get the number of events from database
		$limit		= $app->getUserStateFromRequest('com_jem.venue.limit', 'limit', $jemsettings->display_num, 'int');
		$limitstart = $app->getUserStateFromRequest('com_jem.venue.limitstart', 'limitstart', 0, 'int');

		$this->setState('limit', $limit);
		$this->setState('limitstart', $limitstart);
	}


	function setdate($date)
	{
		$this->_date = $date;
	}

	/**
	 * Method to set the venue id
	 *
	 * @access	public
	 * @param	int	venue ID number
	 */
	function setId($id)
	{
		// Set new venue ID and wipe data
		$this->_id			= $id;
		$this->_data		= null;
	}

	/**
	 * set limit
	 * @param int value
	 */
	function setLimit($value)
	{
		$this->setState('limit', (int) $value);
	}

	/**
	 * set limitstart
	 * @param int value
	 */
	function setLimitStart($value)
	{
		$this->setState('limitstart', (int) $value);
	}

	/**
	 * Method to get the events
	 *
	 * @access public
	 * @return array
	 */
	function &getData()
	{
		$jinput = JFactory::getApplication()->input;
		$layout = $jinput->get('layout', null, 'word');

		$pop = JRequest::getBool('pop');

		// Lets load the content if it doesn't already exist
		if (empty($this->_data)) {
			$query = $this->_buildQuery();

			if ($pop) {
				$this->_data = $this->_getList($query);
			} else {
				$pagination = $this->getPagination();
				$this->_data = $this->_getList($query, $pagination->limitstart, $pagination->limit);
			}
		}

		$count = count($this->_data);
		for($i = 0; $i < $count; $i++) {
			$item = $this->_data[$i];
			$item->categories = $this->getCategories($item->id);

			//remove events without categories (users have no access to them)
			if (empty($item->categories)) {
				unset($this->_data[$i]);
			}
		}

		return $this->_data;
	}

	/**
	 * Total nr of events
	 *
	 * @access public
	 * @return integer
	 */
	function getTotal()
	{
		// Lets load the total nr if it doesn't already exist
		if (empty($this->_total)) {
			$query = $this->_buildQuery();
			$this->_total = $this->_getListCount($query);
		}

		return $this->_total;
	}

	/**
	 * Method to get a pagination object for the events
	 *
	 * @access public
	 * @return integer
	 */
	function getPagination()
	{
		// Lets load the content if it doesn't already exist
		if (empty($this->_pagination)) {
			jimport('joomla.html.pagination');
			$this->_pagination = new JPagination($this->getTotal(), $this->getState('limitstart'), $this->getState('limit'));
		}

		return $this->_pagination;
	}

	/**
	 * Build the query
	 *
	 * @access private
	 * @return string
	 */
	function _buildQuery()
	{
		// Get the WHERE and ORDER BY clauses for the query
		$where		= $this->_buildWhere();
		$orderby	= $this->_buildOrderBy();

		//Get Events from Database
		$query = 'SELECT DISTINCT a.id, a.dates, a.enddates, a.times, a.endtimes, a.title, a.locid, a.created, a.fulltext, a.featured, '
				. ' l.venue, l.published AS published, l.city, l.state, l.url, l.street, l.custom1, l.custom2, l.custom3, l.custom4, l.custom5, l.custom6, l.custom7, l.custom8, l.custom9, l.custom10, c.catname, ct.name AS countryname, '
				. ' CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END as slug,'
				. ' CASE WHEN CHAR_LENGTH(l.alias) THEN CONCAT_WS(\':\', a.locid, l.alias) ELSE a.locid END as venueslug'
				. ' FROM #__jem_events AS a'
				. ' LEFT JOIN #__jem_venues AS l ON l.id = a.locid'
				. ' LEFT JOIN #__jem_cats_event_relations AS rel ON rel.itemid = a.id'
				. ' LEFT JOIN #__jem_categories AS c ON c.id = rel.catid '
				. ' LEFT JOIN #__jem_countries AS ct ON ct.iso2 = l.country '
				. $where
				. ' GROUP BY a.id'
				. $orderby
				;

		return $query;
	}

	/**
	 * Build the order clause
	 *
	 * @access private
	 * @return string
	 */
	function _buildOrderBy()
	{
		$app = JFactory::getApplication();

		$filter_order		= $app->getUserStateFromRequest('com_jem.venue.filter_order', 'filter_order', 'a.dates', 'cmd');
		$filter_order_Dir	= $app->getUserStateFromRequest('com_jem.venue.filter_order_Dir', 'filter_order_Dir', 'ASC', 'word');

		$filter_order		= JFilterInput::getInstance()->clean($filter_order, 'cmd');
		$filter_order_Dir	= JFilterInput::getInstance()->clean($filter_order_Dir, 'word');

		if ($filter_order == 'a.dates') {
			$orderby = ' ORDER BY a.dates, a.times ' . $filter_order_Dir;
		} else {
			$orderby = ' ORDER BY ' . $filter_order . ' ' . $filter_order_Dir;
		}

		return $orderby;
	}

	/**
	 * Method to build the WHERE clause
	 *
	 * @access private
	 * @return array
	 */
	function _buildWhere()
	{
		$app 			= JFactory::getApplication();
		$task 			= JRequest::getWord('task');
		$settings	 	= JEMHelper::globalattribs();
		$user 			= JFactory::getUser();
		// Support Joomla access levels instead of single group id
		$levels 		= $user->getAuthorisedViewLevels();

		$filter 		= $app->getUserStateFromRequest('com_jem.venue.filter', 'filter', '', 'int');
		$search 		= $app->getUserStateFromRequest('com_jem.venue.filter_search', 'filter_search', '', 'string');
		$search 		= $this->_db->escape(trim(JString::strtolower($search)));

		$where = array();

		// First thing we need to do is to select only needed events
		if ($task == 'archive') {
			$where[] = ' a.published = 2 && a.locid = '.$this->_id;
		} else {
			$where[] = ' a.published = 1 && a.locid = '.$this->_id;
		}
		$where[] = ' c.published = 1';
		$where[] = ' c.access IN (' . implode(',', $levels) . ')';

		/* get excluded categories
		 $excluded_cats = trim($params->get('excluded_cats', ''));

		if ($excluded_cats != '') {
		$cats_excluded = explode(',', $excluded_cats);
		$where [] = '  (c.id!=' . implode(' AND c.id!=', $cats_excluded) . ')';
		}
		// === END Excluded categories add === //
		*/

		if ($settings->get('global_show_filter') && $search) {
			switch($filter) {
				case 1:
					$where[] = ' LOWER(a.title) LIKE \'%'.$search.'%\' ';
					break;
				case 2:
					$where[] = ' LOWER(l.venue) LIKE \'%'.$search.'%\' ';
					break;
				case 3:
					$where[] = ' LOWER(l.city) LIKE \'%'.$search.'%\' ';
					break;
				case 4:
					$where[] = ' LOWER(c.catname) LIKE \'%'.$search.'%\' ';
					break;
				case 5:
				default:
					$where[] = ' LOWER(l.state) LIKE \'%'.$search.'%\' ';
			}
		}

		$where = (count($where) ? ' WHERE ' . implode(' AND ', $where) : '');

		return $where;
	}

	/**
	 * Method to get the Venue
	 *
	 * @access public
	 * @return array
	 */
	function getVenue()
	{
		$user  = JFactory::getUser();

		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);

		$query->select('id, venue, published, city, state, url, street, custom1, custom2, custom3, custom4, custom5, '.
				' custom6, custom7, custom8, custom9, custom10, locimage, meta_keywords, meta_description, '.
				' created, locdescription, country, map, latitude, longitude, postalCode, '.
				' CASE WHEN CHAR_LENGTH(alias) THEN CONCAT_WS(\':\', id, alias) ELSE id END as slug');
		$query->from($db->quoteName('#__jem_venues'));
		$query->where('id = '.$this->_id);

		$db->setQuery($query);

		$_venue = $db->loadObject();
		$_venue->attachments = JEMAttachment::getAttachments('venue'.$_venue->id);
		return $_venue;
	}


	function getCategories($id)
	{
		$user = JFactory::getUser();
		// Support Joomla access levels instead of single group id
		$levels = $user->getAuthorisedViewLevels();

		$db = JFactory::getDbo();
		$query = $db->getQuery(true);

		$query->select('DISTINCT c.id, c.catname, c.access, c.color, c.checked_out AS cchecked_out,'
				. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as catslug');
		$query->from('#__jem_categories AS c');
		$query->join('LEFT', '#__jem_cats_event_relations AS rel ON rel.catid = c.id');
		$query->where('rel.itemid = ' . (int) $id);
		$query->where('c.published = 1');
		$query->where('c.access IN (' . implode(',', $levels) . ')');
		$query->group('c.id');

		$db->setQuery($query);

		$this->_cats = $db->loadObjectList();

		$count = count($this->_cats);

		for($i = 0; $i < $count; $i++) {
			$item = $this->_cats[$i];
			$cats = new JEMCategories($item->id);
			$item->parentcats = $cats->getParentlist();
		}

		return $this->_cats;
	}
}
?>
