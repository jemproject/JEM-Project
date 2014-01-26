<?php
/**
 * @version 1.9.6
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.view');
require JPATH_COMPONENT_SITE.'/classes/view.class.php';

/**
 * HTML View class for the Categoriesdetailed View
 *
 * @package JEM
 *
 */
class JEMViewCategoriesdetailed extends JEMView
{
	/**
	 * Creates the Categoriesdetailed View
	 *
	 *
	 */
	function display($tpl = null)
	{
		$app = JFactory::getApplication();

		//initialise variables
		$document 		= JFactory::getDocument();
		$jemsettings 	= JEMHelper::config();
		$model 			= $this->getModel();
		$menu			= $app->getMenu();
		$menuitem		= $menu->getActive();
		$params 		= $app->getParams();
		$user			= JFactory::getUser();
		$pathway 		= $app->getPathWay();
		$task 			= JRequest::getWord('task');
		$print			= JRequest::getBool('print');

		//Get data from the model
		$categories		= $this->get('Data');

		// Create the pagination object
		$pagination = $this->get('Pagination');

		// Load css
		JHtml::_('stylesheet', 'com_jem/jem.css', array(), true);
		$document->addCustomTag('<!--[if IE]><style type="text/css">.floattext{zoom:1;}, * html #jem dd { height: 1%; }</style><![endif]-->');
		if ($print) {
			JHtml::_('stylesheet', 'com_jem/print.css', array(), true);
			$document->setMetaData('robots', 'noindex, nofollow');
		}

		$pagetitle   = $params->def('page_title', $menuitem->title);
		$pageheading = $params->def('page_heading', $params->get('page_title'));

		//pathway
		if($menuitem) {
			$pathway->setItemName(1, $menuitem->title);
		}

		if ($task == 'archive') {
			$pathway->addItem(JText::_('COM_JEM_ARCHIVE'), JRoute::_('index.php?view=categoriesdetailed&task=archive'));
			$print_link = JRoute::_('index.php?option=com_jem&view=categoriesdetailed&task=archive&print=1&tmpl=component');
			$pagetitle   .= ' - '.JText::_('COM_JEM_ARCHIVE');
			$pageheading .= ' - '.JText::_('COM_JEM_ARCHIVE');
		} else {
			$print_link = JRoute::_('index.php?option=com_jem&view=categoriesdetailed&print=1&tmpl=component');
		}

		// Add site name to title if param is set
		if ($app->getCfg('sitename_pagetitles', 0) == 1) {
			$pagetitle = JText::sprintf('JPAGETITLE', $app->getCfg('sitename'), $pagetitle);
		}
		elseif ($app->getCfg('sitename_pagetitles', 0) == 2) {
			$pagetitle = JText::sprintf('JPAGETITLE', $pagetitle, $app->getCfg('sitename'));
		}

		//set Page title
		$document->setTitle($pagetitle);
		$document->setMetadata('title' , $pagetitle);
		$document->setMetadata('keywords' , $pagetitle);

		//Check if the user has access to the form
		$maintainer = JEMUser::ismaintainer('add');
		$genaccess 	= JEMUser::validate_user($jemsettings->evdelrec, $jemsettings->delivereventsyes);

		if ($maintainer || $genaccess || $user->authorise('core.create','com_jem')) {
			$dellink = 1;
		} else {
			$dellink = 0;
		}

		//add alternate feed link
		$link    = 'index.php?option=com_jem&view=eventslist&format=feed';
		$attribs = array('type' => 'application/rss+xml', 'title' => 'RSS 2.0');
		$document->addHeadLink(JRoute::_($link.'&type=rss'), 'alternate', 'rel', $attribs);
		$attribs = array('type' => 'application/atom+xml', 'title' => 'Atom 1.0');
		$document->addHeadLink(JRoute::_($link.'&type=atom'), 'alternate', 'rel', $attribs);

		// Create the pagination object
		jimport('joomla.html.pagination');

		$this->categories		= $categories;
		$this->print_link		= $print_link;
		$this->params			= $params;
		$this->dellink			= $dellink;
		$this->item				= $menuitem;
		$this->model			= $model;
		$this->pagination		= $pagination;
		$this->jemsettings		= $jemsettings;
		$this->task				= $task;
		$this->pagetitle		= $pagetitle;
		$this->pageheading	= $pageheading;

		parent::display($tpl);
	}
}
?>