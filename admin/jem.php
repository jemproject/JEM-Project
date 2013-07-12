<?php
/**
 * @version 1.9
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

//Require classes
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

// Require specific controller if requested
if( $controller = JRequest::getWord('controller') ) {
	$path = JPATH_COMPONENT.'/controllers/'.$controller.'.php';
	if (file_exists($path)) {
		require_once $path;
	} else {
		$controller = '';
	}
}

//Create the controller
$classname  = 'JEMController'.$controller;
$controller = new $classname( );

// Perform the Request task
$controller->execute( JRequest::getWord('task'));
$controller->redirect();
?>