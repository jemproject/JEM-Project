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
use Joomla\CMS\Filter\InputFilter;

/**
 * Model-Venueslist
 */
class JemModelVenueslist extends JModelList
{
	var $_venues = null;
	var $_total_venues = null;

	/**
	 * Constructor
	 */
	public function __construct($config = array())
	{
		parent::__construct();

		$app = Factory::getApplication();
		$jemsettings = JEMHelper::config();

		parent::__construct($config);
	}
	
	/**
	 * Method to auto-populate the model state.
	 */
	protected function populateState($ordering = null, $direction = null)
	{
		$app         = Factory::getApplication();
		$jemsettings = JemHelper::config();
		$jinput      = $app->input;
		$task        = $jinput->getCmd('task');
		$itemid      = $jinput->getInt('id', 0) . ':' . $jinput->getInt('Itemid', 0);
		
		/* in J! 3.3.6 limitstart is removed from request - but we need it! */
		if ($app->input->getInt('limitstart', null) === null) {
			$app->setUserState('com_jem.venueslist.limitstart', 0);
		}
		
		$limit       = $app->getUserStateFromRequest('com_jem.venueslist.'.$itemid.'.limit', 'limit', $jemsettings->display_num, 'int');
		$this->setState('list.limit', $limit);
		$limitstart  = $app->getUserStateFromRequest('com_jem.venueslist.'.$itemid.'.limitstart', 'limitstart', 0, 'int');
		// correct start value if required
		$limitstart  = $limit ? (int)(floor($limitstart / $limit) * $limit) : 0;
		$this->setState('list.start', $limitstart);
		
		# Search - variables
		$search      = $app->getUserStateFromRequest('com_jem.venueslist.'.$itemid.'.filter_search', 'filter_search', '', 'string');
		$this->setState('filter.filter_search', $search); // must be escaped later
		
		$filtertype  = $app->getUserStateFromRequest('com_jem.venueslist.'.$itemid.'.filter_type', 'filter_type', '', 'int');
		$this->setState('filter.filter_type', $filtertype);
		
		###########
		## ORDER ##
		###########
		
		$filter_order		= $app->getUserStateFromRequest('com_jem.venueslist.'.$itemid.'.filter_order', 'filter_order', 'a.city', 'cmd');
		$filter_order_Dir	= $app->getUserStateFromRequest('com_jem.venueslist.'.$itemid.'.filter_order_Dir', 'filter_order_Dir', 'ASC', 'word');
		$filter_order		= InputFilter::getInstance()->clean($filter_order, 'cmd');
		$filter_order_Dir	= InputFilter::getInstance()->clean($filter_order_Dir, 'word');
		
		$orderby = $filter_order . ' ' . $filter_order_Dir;
	
		$this->setState('filter.orderby',$orderby);
		
		 parent::populateState('a.venue', 'ASC');
	}
	
	
	/**
	 * Method to get a store id based on model configuration state.
	 */
	protected function getStoreId($id = '')
	{
		
		$id .= ':' . $this->getState('list.start');
		$id .= ':' . $this->getState('list.limit');
		$id .= ':' . $this->getState('filter.filter_search');
		$id .= ':' . $this->getState('filter.filter_type');
		$id .= ':' . serialize($this->getState('filter.orderby'));
		
		return parent::getStoreId($id);
	}
	

	/**
	 * Build the query
	 */
	protected function getListQuery()
	{
		$app       = Factory::getApplication();
		$jinput    = Factory::getApplication()->input;
		$task      = $jinput->getCmd('task', '');
		$itemid    = $jinput->getInt('id', 0) . ':' . $jinput->getInt('Itemid', 0);
	
		$params    = $app->getParams();
		$settings  = JemHelper::globalattribs();
		$user      = JemFactory::getUser();
		
		# Query
        $db = Factory::getContainer()->get('DatabaseDriver');
		$query = $db->getQuery(true);
		
		$case_when_l = ' CASE WHEN ';
		$case_when_l .= $query->charLength('a.alias','!=', '0');
		$case_when_l .= ' THEN ';
		$id_l = $query->castAsChar('a.id');
		$case_when_l .= $query->concatenate(array($id_l, 'a.alias'), ':');
		$case_when_l .= ' ELSE ';
		$case_when_l .= $id_l.' END as venueslug';
		
		# venue
		$query->select(
			$this->getState(
				'list.select',
				'a.*'
			)
		);
		$query->from('#__jem_venues as a');
		
		$query->select(array($case_when_l));
		
		###################
		## FILTER-SEARCH ##
		###################
		
		# define variables
		$filter = $this->getState('filter.filter_type');
		$search = $this->getState('filter.filter_search'); // not escaped
		
		if (!empty($search)) {
			if (stripos($search, 'id:') === 0) {
				$query->where('a.id = '.(int) substr($search, 3));
			} else {
				$search = $db->Quote('%'.$db->escape($search, true).'%', false); // escape once
		
				if ($search && $settings->get('global_show_filter')) {
					switch ($filter) {
						# case 4 is category, so it is omitted
						
						# there is no title so omit case 1
						#case 1:
						#	$query->where('a.title LIKE '.$search);
						#	break;
						case 2:
							$query->where('a.venue LIKE '.$search);
							break;
						case 3:
							$query->where('a.city LIKE '.$search);
							break;
						case 5:
							$query->where('a.state LIKE '.$search);
							break;
					}
				}
			}
		}
		
		# ordering
		$orderby = $this->getState('filter.orderby');
		
		if ($orderby) {
			$query->order($orderby);
		}
		
		return $query;
	}
}
