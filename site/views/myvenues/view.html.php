<?php
/**
 * @version 4.0.1-dev1
 * @package JEM
 * @copyright (C) 2013-2023 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;

/**
 * MyVenues-View
 */
class JemViewMyvenues extends JemView
{
	/**
	 * Creates the Myvenues View
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
		$user         = JemFactory::getUser();
		$userId       = $user->get('id');
		$pathway      = $app->getPathWay();
		$jinput       = $app->input;
		$print        = $jinput->getBool('print', false);
		$task         = $jinput->getCmd('task', '');

		// redirect if not logged in
		$this->needLoginFirst = 0;
		if (!$user->get('id')) {
			$app->enqueueMessage(Text::_('COM_JEM_NEED_LOGGED_IN'), 'error');
			$this->needLoginFirst=1;
		}else {
			// Decide which parameters should take priority
			$useMenuItemParams = ($menuitem && $menuitem->query['option'] == 'com_jem'
				&& $menuitem->query['view'] == 'myvenues');

			// Load css
			JemHelper::loadCss('jem');
			JemHelper::loadCustomCss();
			JemHelper::loadCustomTag();

			if ($print) {
				JemHelper::loadCss('print');
				$document->setMetaData('robots', 'noindex, nofollow');
			}

			$venues = $this->get('Venues');
			$venues_pagination = $this->get('VenuesPagination');

			// are no venues available?
			$novenues = (!$venues) ? 1 : 0;

			// get variables
			$filter_order = $app->getUserStateFromRequest('com_jem.myvenues.filter_order', 'filter_order', 'l.venue', 'cmd');
			$filter_order_Dir = $app->getUserStateFromRequest('com_jem.myvenues.filter_order_Dir', 'filter_order_Dir', '', 'word');
			// $filter_state     = $app->getUserStateFromRequest('com_jem.myvenues.filter_state', 'filter_state', 	'*', 'word');
			$filter = $app->getUserStateFromRequest('com_jem.myvenues.filter', 'filter', 0, 'int');
			$search = $app->getUserStateFromRequest('com_jem.myvenues.filter_search', 'filter_search', '', 'string');

			// search filter
			$filters = array();

			// Workaround issue #557: Show venue name always.
			$jemsettings->showlocate = 1;

			//if ($jemsettings->showtitle == 1) {
			//	$filters[] = HTMLHelper::_('select.option', '1', Text::_('COM_JEM_TITLE'));
			//}
			if ($jemsettings->showlocate == 1) {
				$filters[] = HTMLHelper::_('select.option', '2', Text::_('COM_JEM_VENUE'));
			}
			if ($jemsettings->showcity == 1) {
				$filters[] = HTMLHelper::_('select.option', '3', Text::_('COM_JEM_CITY'));
			}
			//if ($jemsettings->showcat == 1) {
			//	$filters[] = HTMLHelper::_('select.option', '4', Text::_('COM_JEM_CATEGORY'));
			//}
			if ($jemsettings->showstate == 1) {
				$filters[] = HTMLHelper::_('select.option', '5', Text::_('COM_JEM_STATE'));
			}
			$lists['filter'] = HTMLHelper::_('select.genericlist', $filters, 'filter', array('size' => '1', 'class' => 'inputbox'), 'value', 'text', $filter);

			// search filter
			$lists['search'] = $search;

			// table ordering
			$lists['order_Dir'] = $filter_order_Dir;
			$lists['order'] = $filter_order;

			// pathway
			if ($menuitem) {
				$pathwayKeys = array_keys($pathway->getPathway());
				$lastPathwayEntryIndex = end($pathwayKeys);
				$pathway->setItemName($lastPathwayEntryIndex, $menuitem->title);
				//$pathway->setItemName(1, $menuitem->title);
			}

			// Set Page title
			$pagetitle = Text::_('COM_JEM_MY_VENUES');
			$pageheading = $pagetitle;
			$pageclass_sfx = '';

			// Check to see which parameters should take priority
			if ($useMenuItemParams) {
				// Menu item params take priority
				$params->def('page_title', $menuitem->title);
				$pagetitle = $params->get('page_title', Text::_('COM_JEM_MY_VENUES'));
				$pageheading = $params->get('page_heading', $pagetitle);
				$pageclass_sfx = $params->get('pageclass_sfx');
			}

			// ($task == 'archive') useless
			$print_link = Route::_(JemHelperRoute::getMyVenuesRoute() . '&print=1&tmpl=component');

			$params->set('page_heading', $pageheading);

			// Add site name to title if param is set
			if ($app->get('sitename_pagetitles', 0) == 1) {
				$pagetitle = Text::sprintf('JPAGETITLE', $app->get('sitename'), $pagetitle);
			} elseif ($app->get('sitename_pagetitles', 0) == 2) {
				$pagetitle = Text::sprintf('JPAGETITLE', $pagetitle, $app->get('sitename'));
			}

			$document->setTitle($pagetitle);
			$document->setMetaData('title', $pagetitle);

			// Should we show publish buttons?
			$canPublishVenue = false;
			foreach ($venues as $venue) {
				$canPublishVenue |= $venue->params->get('access-change');
				if ($canPublishVenue) break;
			}

			// Set the user permissions
			$permissions = new stdClass();
			$permissions->canAddEvent = $user->can('add', 'event');
			$permissions->canAddVenue = $user->can('add', 'venue');
			$permissions->canPublishVenue = $canPublishVenue;

			$this->action = $uri->toString();
			$this->venues = $venues;
			$this->task = $task;
			$this->print = $print;
			$this->params = $params;
			$this->venues_pagination = $venues_pagination;
			$this->jemsettings = $jemsettings;
			$this->settings = $settings;
			$this->permissions = $permissions;
			$this->pagetitle = $pagetitle;
			$this->lists = $lists;
			$this->novenues = $novenues;
			$this->print_link = $print_link;
			$this->pageclass_sfx = $pageclass_sfx ? htmlspecialchars($pageclass_sfx) : $pageclass_sfx;
		}
		parent::display($tpl);
	}
}
?>
