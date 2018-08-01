<?php
/**
 * @version 2.2.2
 * @package JEM
 * @copyright (C) 2013-2017 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;

require JPATH_COMPONENT_SITE . '/classes/view.class.php';

/**
 * Search-View
 */
class JemViewSearch extends JemView
{
	/**
	 * Creates the Simple List View
	 */
	public function display($tpl = null)
	{
		// initialize variables
		$app          = JFactory::getApplication();
		$document     = JFactory::getDocument();
		$jemsettings  = JemHelper::config();
		$settings     = JemHelper::globalattribs();
		$menu         = $app->getMenu();
		$menuitem     = $menu->getActive();
		$params       = $app->getParams();
		$uri          = JFactory::getURI();
		$pathway      = $app->getPathWay();
	//	$user         = JemFactory::getUser();

		// Decide which parameters should take priority
		$useMenuItemParams = ($menuitem && $menuitem->query['option'] == 'com_jem'
		                                && $menuitem->query['view'] == 'search');

		// add javascript
		JHtml::_('behavior.framework');

		// Load css
		JemHelper::loadCss('jem');
		JemHelper::loadCustomCss();
		JemHelper::loadCustomTag();

		// Load Script
		JHtml::_('script', 'com_jem/search.js', false, true);

		$filter_continent = $app->getUserStateFromRequest('com_jem.search.filter_continent', 'filter_continent', '', 'string');
		$filter_country   = $app->getUserStateFromRequest('com_jem.search.filter_country', 'filter_country', '', 'string');
		$filter_city      = $app->getUserStateFromRequest('com_jem.search.filter_city', 'filter_city', '', 'string');
		$filter_date_from = $app->getUserStateFromRequest('com_jem.search.filter_date_from', 'filter_date_from', '', 'string');
		$filter_date_to   = $app->getUserStateFromRequest('com_jem.search.filter_date_to', 'filter_date_to', '', 'string');
		$filter_category  = $app->getUserStateFromRequest('com_jem.search.filter_category', 'filter_category', 0, 'int');
		$task             = $app->input->get('task', '');

		// get data from model
		$rows = $this->get('Data');

		// are events available?
		$noevents = (!$rows) ? 1 : 0;

		// Check to see which parameters should take priority
		if ($useMenuItemParams) {
			// Menu item params take priority
			$pagetitle = $params->def('page_title', $menuitem ? $menuitem->title : JText::_('COM_JEM_SEARCH'));
			$pageheading = $params->def('page_heading', $pagetitle);
			$pathway->setItemName(1, $menuitem->title);
		} else {
			$pagetitle = JText::_('COM_JEM_SEARCH');
			$pageheading = $pagetitle;
			$params->set('introtext', ''); // there is no introtext in that case
			$params->set('showintrotext', 0);
			$pathway->addItem(1, $pagetitle);
		}
		$pageclass_sfx = $params->get('pageclass_sfx');

		if ($task == 'archive') {
			$pathway->addItem(JText::_('COM_JEM_ARCHIVE'), JRoute::_('index.php?option=com_jem&view=search&task=archive'));
			$pagetitle   .= ' - ' . JText::_('COM_JEM_ARCHIVE');
			$pageheading .= ' - ' . JText::_('COM_JEM_ARCHIVE');
		}
		$pageclass_sfx = $params->get('pageclass_sfx');

		$params->set('page_heading', $pageheading);

		// Add site name to title if param is set
		if ($app->getCfg('sitename_pagetitles', 0) == 1) {
			$pagetitle = JText::sprintf('JPAGETITLE', $app->getCfg('sitename'), $pagetitle);
		}
		elseif ($app->getCfg('sitename_pagetitles', 0) == 2) {
			$pagetitle = JText::sprintf('JPAGETITLE', $pagetitle, $app->getCfg('sitename'));
		}

		// Set Page title
		$document->setTitle($pagetitle);
		$document->setMetadata('title' , $pagetitle);

		// No permissions required/useful on this view
		$permissions = new stdClass();

		// create select lists
		$lists	= $this->_buildSortLists();

		if ($lists['filter']) {
			//$uri->setVar('filter', $app->input->getString('filter', ''));
			//$filter		= $app->getUserStateFromRequest('com_jem.jem.filter', 'filter', '', 'string');
			$uri->setVar('filter', $lists['filter']);
			$uri->setVar('filter_type', $app->input->getString('filter_type', ''));
		} else {
			$uri->delVar('filter');
			$uri->delVar('filter_type');
		}

		// Cause of group limits we can't use class here to build the categories tree
		$categories   = $this->get('CategoryTree');
		$catoptions   = array();
		$catoptions[] = JHtml::_('select.option', '1', JText::_('COM_JEM_SELECT_CATEGORY'));
		$catoptions   = array_merge($catoptions, JemCategories::getcatselectoptions($categories));
		$selectedcats = ($filter_category) ? array($filter_category) : array();

		// build selectlists
		$lists['categories'] = JHtml::_('select.genericlist', $catoptions, 'filter_category', array('size'=>'1', 'class'=>'inputbox'), 'value', 'text', $selectedcats);

		// Create the pagination object
		$pagination = $this->get('Pagination');

		// date filter
		$lists['date_from'] = JHtml::_('calendar', $filter_date_from, 'filter_date_from', 'filter_date_from', '%Y-%m-%d', array('class'=>"inputbox", 'showTime' => false));
		$lists['date_to']   = JHtml::_('calendar', $filter_date_to, 'filter_date_to', 'filter_date_to', '%Y-%m-%d', array('class'=>"inputbox", 'showTime' => false));

		// country filter
		$continents = array();
		$continents[] = JHtml::_('select.option', '',   JText::_('COM_JEM_SELECT_CONTINENT'));
		$continents[] = JHtml::_('select.option', 'AF', JText::_('COM_JEM_AFRICA'));
		$continents[] = JHtml::_('select.option', 'AS', JText::_('COM_JEM_ASIA'));
		$continents[] = JHtml::_('select.option', 'EU', JText::_('COM_JEM_EUROPE'));
		$continents[] = JHtml::_('select.option', 'NA', JText::_('COM_JEM_NORTH_AMERICA'));
		$continents[] = JHtml::_('select.option', 'SA', JText::_('COM_JEM_SOUTH_AMERICA'));
		$continents[] = JHtml::_('select.option', 'OC', JText::_('COM_JEM_OCEANIA'));
		$continents[] = JHtml::_('select.option', 'AN', JText::_('COM_JEM_ANTARCTICA'));
		$lists['continents'] = JHtml::_('select.genericlist', $continents, 'filter_continent', array('class'=>'inputbox'), 'value', 'text', $filter_continent);
		unset($continents);

		// country filter
		$countries = array();
		$countries[] = JHtml::_('select.option', '', JText::_('COM_JEM_SELECT_COUNTRY'));
		$countries = array_merge($countries, $this->get('CountryOptions'));
		$lists['countries'] = JHtml::_('select.genericlist', $countries, 'filter_country', array('class'=>'inputbox'), 'value', 'text', $filter_country);
		unset($countries);

		// city filter
		if ($filter_country) {
			$cities = array();
			$cities[] = JHtml::_('select.option', '', JText::_('COM_JEM_SELECT_CITY'));
			$cities = array_merge($cities, $this->get('CityOptions'));
			$lists['cities'] = JHtml::_('select.genericlist', $cities, 'filter_city', array('class'=>'inputbox'), 'value', 'text', $filter_city);
			unset($cities);
		}

		$this->lists            = $lists;
		$this->action           = $uri->toString();
		$this->rows             = $rows;
		$this->task             = $task;
		$this->noevents         = $noevents;
		$this->params           = $params;
		$this->pagination       = $pagination;
		$this->jemsettings      = $jemsettings;
		$this->settings         = $settings;
		$this->permissions      = $permissions;
		$this->pagetitle        = $pagetitle;
		$this->filter_continent = $filter_continent;
		$this->filter_country   = $filter_country;
		$this->document         = $document;
		$this->pageclass_sfx    = htmlspecialchars($pageclass_sfx);

		parent::display($tpl);
	}

	/**
	 * Method to build the sortlists
	 *
	 * @access private
	 * @return array
	 *
	 */
	protected function _buildSortLists()
	{
		$app = JFactory::getApplication();
		$task = $app->input->get('task', '');

		$filter_order = $app->input->getCmd('filter_order', 'a.dates');
		$filter_order_DirDefault = 'ASC';
		// Reverse default order for dates in archive mode
		if ($task == 'archive' && $filter_order == 'a.dates') {
			$filter_order_DirDefault = 'DESC';
		}
		$filter_order_Dir = $app->input->get('filter_order_Dir', $filter_order_DirDefault);
		$filter           = $app->getUserStateFromRequest('com_jem.search.filter_search', 'filter_search', '', 'string');
		$filter_type      = $app->input->getString('filter_type', '');

		$sortselects = array();
		$sortselects[] = JHtml::_('select.option', 'title', JText::_('COM_JEM_TABLE_TITLE'));
		$sortselects[] = JHtml::_('select.option', 'venue', JText::_('COM_JEM_TABLE_LOCATION'));
		$sortselect    = JHtml::_('select.genericlist', $sortselects, 'filter_type', array('size'=>'1','class'=>'inputbox'), 'value', 'text', $filter_type);

		$lists['order_Dir']    = $filter_order_Dir;
		$lists['order']        = $filter_order;
		$lists['filter']       = $filter;
		$lists['filter_types'] = $sortselect;

		return $lists;
	}
}
?>
