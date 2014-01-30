<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.application.component.view');

/**
 * HTML View class for the Categories View
 *
 * @package JEM
 *
 */
class JEMViewCategories extends JViewLegacy
{
	function display($tpl=null)
	{
		$app = JFactory::getApplication();

		$document 		= JFactory::getDocument();
		$jemsettings 	= JEMHelper::config();
		$user			= JFactory::getUser();
		$print			= JRequest::getBool('print');

		$rows = $this->get('Data');
		$pagination = $this->get('Pagination');

		// Load css
		JHtml::_('stylesheet', 'com_jem/jem.css', array(), true);
		$document->addCustomTag('<!--[if IE]><style type="text/css">.floattext{zoom:1;}, * html #jem dd { height: 1%; }</style><![endif]-->');
		if ($print) {
			JHtml::_('stylesheet', 'com_jem/print.css', array(), true);
			$document->setMetaData('robots', 'noindex, nofollow');
		}

		//get menu information
		$menu		= $app->getMenu();
		$item		= $menu->getActive();
		$params 	= $app->getParams('com_jem');

		// Request variables
		$task		= JRequest::getWord('task');

		$params->def('page_title', $item->title);

		//pathway
		$pathway = $app->getPathWay();
		if($item) {
			$pathway->setItemName(1, $item->title);
		}

		if ($task == 'archive') {
			$pathway->addItem(JText::_('COM_JEM_ARCHIVE'), JRoute::_('index.php?view=categories&task=archive'));
			$print_link = JRoute::_('index.php?option=com_jem&view=categories&task=archive&print=1&tmpl=component');
			$pagetitle = $params->get('page_title').' - '.JText::_('COM_JEM_ARCHIVE');
		} else {
			$print_link = JRoute::_('index.php?option=com_jem&view=categories&print=1&tmpl=component');
			$pagetitle = $params->get('page_title');
		}

		//Set Page title
		$document->setTitle($pagetitle);
		$document->setMetaData('title' , $pagetitle);

		//add alternate feed link
		$link    = 'index.php?option=com_jem&view=eventslist&format=feed';
		$attribs = array('type' => 'application/rss+xml', 'title' => 'RSS 2.0');
		$document->addHeadLink(JRoute::_($link.'&type=rss'), 'alternate', 'rel', $attribs);
		$attribs = array('type' => 'application/atom+xml', 'title' => 'Atom 1.0');
		$document->addHeadLink(JRoute::_($link.'&type=atom'), 'alternate', 'rel', $attribs);

		//Check if the user has access to the form
		$maintainer = JEMUser::ismaintainer('add');
		$genaccess 	= JEMUser::validate_user($jemsettings->evdelrec, $jemsettings->delivereventsyes);

		if ($maintainer || $genaccess || $user->authorise('core.create','com_jem')) {
			$dellink = 1;
		} else {
			$dellink = 0;
		}

		$this->rows				= $rows;
		$this->task				= $task;
		$this->params			= $params;
		$this->dellink			= $dellink;
		$this->pagination		= $pagination;
		$this->item				= $item;
		$this->jemsettings		= $jemsettings;
		$this->pagetitle		= $pagetitle;
		$this->print_link		= $print_link;

		parent::display($tpl);
	}
}
?>