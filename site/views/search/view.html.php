<?php
/**
 * @version    4.2.2
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
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
		$app          = Factory::getApplication();
		$document     = $app->getDocument();
		$jemsettings  = JemHelper::config();
		$settings     = JemHelper::globalattribs();
		$menu         = $app->getMenu();
		$menuitem     = $menu->getActive();
		$params       = $app->getParams();
		$uri          = Uri::getInstance();
		$pathway      = $app->getPathWay();
		$url 			= Uri::root();
	//	$user         = JemFactory::getUser();

		// Decide which parameters should take priority
		$useMenuItemParams = ($menuitem && $menuitem->query['option'] == 'com_jem'
		                                && $menuitem->query['view'] == 'search');

		// Load css
		JemHelper::loadCss('jem');
		JemHelper::loadCustomCss();
		JemHelper::loadCustomTag();

		// Load Script
		// HTMLHelper::_('script', 'com_jem/search.js', false, true);
		$document->addScript($url.'media/com_jem/js/search.js');

		$filter_continent = $app->getUserStateFromRequest('com_jem.search.filter_continent', 'filter_continent', '', 'string');
		$filter_country   = $app->getUserStateFromRequest('com_jem.search.filter_country', 'filter_country', '', 'string');
		$filter_city      = $app->getUserStateFromRequest('com_jem.search.filter_city', 'filter_city', '', 'string');
		$filter_date_from = $app->getUserStateFromRequest('com_jem.search.filter_date_from', 'filter_date_from', '', 'string');
		$filter_date_to   = $app->getUserStateFromRequest('com_jem.search.filter_date_to', 'filter_date_to', '', 'string');
		$filter_category  = $app->getUserStateFromRequest('com_jem.search.filter_category', 'filter_category', 0, 'int');
		$task             = $app->input->getCmd('task', '');

		// get data from model
		$rows = $this->get('Data');

		// are events available?
		$noevents = (!$rows) ? 1 : 0;

		// Check to see which parameters should take priority
		if ($useMenuItemParams) {
			// Menu item params take priority
			$pagetitle = $params->def('page_title', $menuitem ? $menuitem->title : Text::_('COM_JEM_SEARCH'));
			$pageheading = $params->def('page_heading', $pagetitle);
      $pathwayKeys = array_keys($pathway->getPathway());
      $lastPathwayEntryIndex = end($pathwayKeys);
      $pathway->setItemName($lastPathwayEntryIndex, $menuitem->title);
      //$pathway->setItemName(1, $menuitem->title);
		} else {
			$pagetitle = Text::_('COM_JEM_SEARCH');
			$pageheading = $pagetitle;
			$params->set('introtext', ''); // there is no introtext in that case
			$params->set('showintrotext', 0);
			$pathway->addItem(1, $pagetitle);
		}
		$pageclass_sfx = $params->get('pageclass_sfx');

		if ($task == 'archive') {
			$pathway->addItem(Text::_('COM_JEM_ARCHIVE'), Route::_('index.php?option=com_jem&view=search&task=archive'));
			$pagetitle   .= ' - ' . Text::_('COM_JEM_ARCHIVE');
			$pageheading .= ' - ' . Text::_('COM_JEM_ARCHIVE');
		}
		$pageclass_sfx = $params->get('pageclass_sfx');

		$params->set('page_heading', $pageheading);

		// Add site name to title if param is set
		if ($app->get('sitename_pagetitles', 0) == 1) {
			$pagetitle = Text::sprintf('JPAGETITLE', $app->get('sitename'), $pagetitle);
		}
		elseif ($app->get('sitename_pagetitles', 0) == 2) {
			$pagetitle = Text::sprintf('JPAGETITLE', $pagetitle, $app->get('sitename'));
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
		$catoptions[] = HTMLHelper::_('select.option', '1', Text::_('COM_JEM_SELECT_CATEGORY'));
		$catoptions   = array_merge($catoptions, JemCategories::getcatselectoptions($categories));
		$selectedcats = ($filter_category) ? array($filter_category) : array();

		// build selectlists
		$lists['categories'] = HTMLHelper::_('select.genericlist', $catoptions, 'filter_category', array('size'=>'1', 'class'=>'inputbox'), 'value', 'text', $selectedcats);

		// Create the pagination object
		$pagination = $this->get('Pagination');

		// date filter
		$lists['date_from'] = HTMLHelper::_('calendar', $filter_date_from, 'filter_date_from', 'filter_date_from', '%Y-%m-%d', array('class'=>"inputbox", 'showTime' => false));
		$lists['date_to']   = HTMLHelper::_('calendar', $filter_date_to, 'filter_date_to', 'filter_date_to', '%Y-%m-%d', array('class'=>"inputbox", 'showTime' => false));

		// country filter
		$continents = array();
		$continents[] = HTMLHelper::_('select.option', '',   Text::_('COM_JEM_SELECT_CONTINENT'));
		$continents[] = HTMLHelper::_('select.option', 'AF', Text::_('COM_JEM_AFRICA'));
		$continents[] = HTMLHelper::_('select.option', 'AS', Text::_('COM_JEM_ASIA'));
		$continents[] = HTMLHelper::_('select.option', 'EU', Text::_('COM_JEM_EUROPE'));
		$continents[] = HTMLHelper::_('select.option', 'NA', Text::_('COM_JEM_NORTH_AMERICA'));
		$continents[] = HTMLHelper::_('select.option', 'SA', Text::_('COM_JEM_SOUTH_AMERICA'));
		$continents[] = HTMLHelper::_('select.option', 'OC', Text::_('COM_JEM_OCEANIA'));
		$continents[] = HTMLHelper::_('select.option', 'AN', Text::_('COM_JEM_ANTARCTICA'));
		$lists['continents'] = HTMLHelper::_('select.genericlist', $continents, 'filter_continent', array('class'=>'inputbox'), 'value', 'text', $filter_continent);
		unset($continents);

		// country filter
		$countries = array();
		$countries[] = HTMLHelper::_('select.option', '', Text::_('COM_JEM_SELECT_COUNTRY'));
		$countries = array_merge($countries, $this->get('CountryOptions'));
		$lists['countries'] = HTMLHelper::_('select.genericlist', $countries, 'filter_country', array('class'=>'inputbox'), 'value', 'text', $filter_country);
		unset($countries);

		// city filter
		if ($filter_country) {
			$cities = array();
			$cities[] = HTMLHelper::_('select.option', '', Text::_('COM_JEM_SELECT_CITY'));
			$cities = array_merge($cities, $this->get('CityOptions'));
			$lists['cities'] = HTMLHelper::_('select.genericlist', $cities, 'filter_city', array('class'=>'inputbox'), 'value', 'text', $filter_city);
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
		$this->pageclass_sfx    =$pageclass_sfx ? htmlspecialchars($pageclass_sfx) : $pageclass_sfx;

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
		$app = Factory::getApplication();
		$task = $app->input->getCmd('task', '');

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
		$sortselects[] = HTMLHelper::_('select.option', 'title', Text::_('COM_JEM_TABLE_TITLE'));
		$sortselects[] = HTMLHelper::_('select.option', 'venue', Text::_('COM_JEM_TABLE_LOCATION'));
		$sortselect    = HTMLHelper::_('select.genericlist', $sortselects, 'filter_type', array('size'=>'1','class'=>'inputbox'), 'value', 'text', $filter_type);

		$lists['order_Dir']    = $filter_order_Dir;
		$lists['order']        = $filter_order;
		$lists['filter']       = $filter;
		$lists['filter_types'] = $sortselect;

		return $lists;
	}
}
?>
