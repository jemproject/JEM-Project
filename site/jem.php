<?php
/**
 * @version 2.3.12
 * @package JEM
 * @copyright (C) 2013-2021 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;

// include files
require_once (JPATH_COMPONENT_SITE.'/factory.php');
require_once (JPATH_COMPONENT_SITE.'/helpers/helper.php');
require_once (JPATH_COMPONENT_SITE.'/helpers/mailtohelper.php');
require_once (JPATH_COMPONENT_SITE.'/helpers/route.php');
require_once (JPATH_COMPONENT_SITE.'/helpers/countries.php');
require_once (JPATH_COMPONENT_SITE.'/classes/config.class.php');
require_once (JPATH_COMPONENT_SITE.'/classes/user.class.php');
require_once (JPATH_COMPONENT_SITE.'/classes/image.class.php');
require_once (JPATH_COMPONENT_SITE.'/classes/output.class.php');
require_once (JPATH_COMPONENT_SITE.'/classes/view.class.php');
require_once (JPATH_COMPONENT_SITE.'/classes/attachment.class.php');
require_once (JPATH_COMPONENT_SITE.'/classes/categories.class.php');
require_once (JPATH_COMPONENT_SITE.'/classes/calendar.class.php');
require_once (JPATH_COMPONENT_SITE.'/classes/activecalendarweek.php');
require_once (JPATH_COMPONENT_SITE.'/helpers/category.php');

// Set the table directory
JTable::addIncludePath(JPATH_COMPONENT_ADMINISTRATOR.'/tables');
$app = Factory::getApplication();
$wa = $app->getDocument()->getWebAssetManager();
$wa->useScript('jquery');
// create JEM's file logger
JemHelper::addFileLogger();

//perform cleanup if it wasn't done today (archive, delete, recurrence)
JemHelper::cleanup();

// import joomla controller library
jimport('joomla.application.component.controller');

// Get an instance of the controller
$controller = JControllerLegacy::getInstance('Jem');

// Perform the Request task
$input = $app->input;
$controller->execute($input->getCmd('task'));

// Redirect if set by the controller
$controller->redirect();
HTMLHelper::_('bootstrap.framework');
HTMLHelper::_('bootstrap.tooltip','.hasTooltip');
$document = $app->getDocument();

// $document->addScriptDeclaration('
//     jQuery(document).ready(function(){
//         var tooltipTriggerList = [].slice.call(document.querySelectorAll(\'[data-bs-toggle="tooltip"]\'));
//         var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
//             return new bootstrap.Tooltip(tooltipTriggerEl,{
//                 html:true
//             })
//         });
    
//     });
// ');

