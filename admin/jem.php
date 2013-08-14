<?php
/**
 * @version 1.9.1
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;



// Access check.
if (!JFactory::getUser()->authorise('core.manage', 'com_jem')) {
	return JError::raiseWarning(404, JText::_('JERROR_ALERTNOAUTHOR'));
}


// Require classes
require_once (JPATH_COMPONENT_SITE.'/helpers/helper.php');
require_once (JPATH_COMPONENT_SITE.'/helpers/countries.php');
require_once (JPATH_COMPONENT_SITE.'/classes/image.class.php');
require_once (JPATH_COMPONENT_SITE.'/classes/output.class.php');
require_once (JPATH_COMPONENT_SITE.'/classes/attachment.class.php');
require_once (JPATH_COMPONENT_SITE.'/classes/categories.class.php');
require_once (JPATH_COMPONENT_ADMINISTRATOR.'/classes/admin.class.php');

// Set the table directory
JTable::addIncludePath(JPATH_COMPONENT.'/tables');

// Require the base controller
require_once (JPATH_COMPONENT.'/controller.php');

// specify the prefix of the controllers
$controller = JControllerLegacy::getInstance('Jem');

// controller is activated upon a task
$controller->execute(JRequest::getCmd('task'));
$controller->redirect();
?>