<?php
/**
 * @version    4.2.1
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
 * Categories-View
 */
class JemViewCategories extends JemView
{
	/**
	 * Creates the Categories-View
	 */
	public function display($tpl = null)
	{
		$app = Factory::getApplication();

		$document    = $app->getDocument();
		$jemsettings = JemHelper::config();
		$user        = JemFactory::getUser();
		$print       = $app->input->getBool('print', false);
		$task        = $app->input->getCmd('task', '');
		$id          = $app->input->getInt('id', 1);
		$model       = $this->getModel();
		$uri         = Uri::getInstance();
		$rows        = $this->get('Data');
		$pagination  = $this->get('Pagination');

		// Load css
		JemHelper::loadCss('jem');
		JemHelper::loadCustomCss();
		JemHelper::loadCustomTag();

		if ($print) {
			JemHelper::loadCss('print');
			$document->setMetaData('robots', 'noindex, nofollow');
		}

		// get menu information
		$menu          = $app->getMenu();
		$menuitem      = $menu->getActive();
		$params        = $app->getParams('com_jem');

		$pagetitle     = $params->def('page_title', $menuitem->title);
		$pageheading   = $params->def('page_heading', $params->get('page_title'));
		$pageclass_sfx = $params->get('pageclass_sfx');

		// pathway
		$pathway = $app->getPathWay();
		if ($menuitem) {
			$pathwayKeys = array_keys($pathway->getPathway());
			$lastPathwayEntryIndex = end($pathwayKeys);
			$pathway->setItemName($lastPathwayEntryIndex, $menuitem->title);
			//$pathway->setItemName(1, $menuitem->title);
		}

		if ($task == 'archive') {
			$pathway->addItem(Text::_('COM_JEM_ARCHIVE'), Route::_('index.php?option=com_jem&view=categories&id='.$id.'&task=archive'));
			$print_link = Route::_('index.php?option=com_jem&view=categories&id='.$id.'&task=archive&print=1&tmpl=component');
			$pagetitle   .= ' - ' . Text::_('COM_JEM_ARCHIVE');
			$pageheading .= ' - ' . Text::_('COM_JEM_ARCHIVE');
			$archive_link = Route::_('index.php?option=com_jem&view=categories');
			$params->set('page_heading', $pageheading);
		} else {
			$print_link = Route::_('index.php?option=com_jem&view=categories&id='.$id.'&print=1&tmpl=component');
			$archive_link = $uri->toString();
		}

		// Add site name to title if param is set
		if ($app->get('sitename_pagetitles', 0) == 1) {
			$pagetitle = Text::sprintf('JPAGETITLE', $app->get('sitename'), $pagetitle);
		}
		elseif ($app->get('sitename_pagetitles', 0) == 2) {
			$pagetitle = Text::sprintf('JPAGETITLE', $pagetitle, $app->get('sitename'));
		}

		// Set Page title
		$document->setTitle($pagetitle);
		$document->setMetaData('title' , $pagetitle);

		// Check if the user has permission to add things
		$permissions = new stdClass();
		$permissions->canAddEvent = $user->can('add', 'event');
		$permissions->canAddVenue = $user->can('add', 'venue');

		// Get events if requested
		if (!empty($rows) && $params->get('detcat_nr', 0) > 0) {
			foreach($rows as $row) {
				$row->events = $model->getEventdata($row->id);
			}
		}

		$this->rows          = $rows;
		$this->task          = $task;
		$this->params        = $params;
		$this->dellink       = $permissions->canAddEvent; // deprecated
		$this->pagination    = $pagination;
		$this->item          = $menuitem;
		$this->jemsettings   = $jemsettings;
		$this->pagetitle     = $pagetitle;
		$this->print_link    = $print_link;
		$this->archive_link  = $archive_link;
		$this->model         = $model;
		$this->id            = $id;
		$this->pageclass_sfx = $pageclass_sfx ? htmlspecialchars($pageclass_sfx) : $pageclass_sfx;
		$this->permissions   = $permissions;

		parent::display($tpl);
	}
}
?>
