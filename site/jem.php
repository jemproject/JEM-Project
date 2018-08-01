<?php
/**
 * @version 2.2.2
 * @package JEM
 * @copyright (C) 2013-2017 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

// include files
require_once (JPATH_COMPONENT_SITE.'/factory.php');
require_once (JPATH_COMPONENT_SITE.'/helpers/helper.php');
require_once (JPATH_COMPONENT_SITE.'/helpers/route.php');
require_once (JPATH_COMPONENT_SITE.'/helpers/countries.php');
require_once (JPATH_COMPONENT_SITE.'/classes/config.class.php');
require_once (JPATH_COMPONENT_SITE.'/classes/user.class.php');
require_once (JPATH_COMPONENT_SITE.'/classes/image.class.php');
require_once (JPATH_COMPONENT_SITE.'/classes/output.class.php');
require_once (JPATH_COMPONENT_SITE.'/classes/attachment.class.php');
require_once (JPATH_COMPONENT_SITE.'/classes/categories.class.php');
require_once (JPATH_COMPONENT_SITE.'/classes/calendar.class.php');
require_once (JPATH_COMPONENT_SITE.'/classes/activecalendarweek.php');
require_once (JPATH_COMPONENT_SITE.'/helpers/category.php');

// Set the table directory
JTable::addIncludePath(JPATH_COMPONENT_ADMINISTRATOR.'/tables');

// create JEM's file logger
JemHelper::addFileLogger();

//perform cleanup if it wasn't done today (archive, delete, recurrence)
JemHelper::cleanup();

// import joomla controller library
jimport('joomla.application.component.controller');

// Get an instance of the controller
$controller = JControllerLegacy::getInstance('Jem');

// Perform the Request task
$input = JFactory::getApplication()->input;
$controller->execute($input->getCmd('task'));

// Redirect if set by the controller
$controller->redirect();
