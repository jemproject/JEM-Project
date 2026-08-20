<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;

// Access check.
require_once (JPATH_COMPONENT_SITE.'/factory.php');


if (!Factory::getApplication()->getIdentity()->authorise('core.manage', 'com_jem')) {
    throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
}

// Require classes
require_once (JPATH_SITE.'/components/com_jem/helpers/helper.php');
require_once (JPATH_SITE.'/components/com_jem/helpers/countries.php');
require_once (JPATH_SITE.'/components/com_jem/classes/config.class.php');
require_once (JPATH_SITE.'/components/com_jem/classes/image.class.php');
require_once (JPATH_SITE.'/components/com_jem/classes/output.class.php');
require_once (JPATH_SITE.'/components/com_jem/classes/user.class.php');
require_once (JPATH_SITE.'/components/com_jem/classes/attachment.class.php');
require_once (JPATH_SITE.'/components/com_jem/classes/categories.class.php');
require_once (JPATH_ADMINISTRATOR.'/components/com_jem/classes/admin.class.php');
require_once (JPATH_ADMINISTRATOR.'/components/com_jem/classes/admin.view.class.php');
require_once (JPATH_ADMINISTRATOR.'/components/com_jem/helpers/helper.php');
require_once (JPATH_ADMINISTRATOR.'/components/com_jem/helpers/html/jemhtml.php');

// Set the table directory
Table::addIncludePath(JPATH_BASE.'/components/com_jem/tables');

// create JEM's file logger
JemHelper::addFileLogger();

// Load the selected backend stylesheet once for every administrator view.
JemHelper::loadCss('backend');

// Require the frontend base controller
require_once (JPATH_BASE.'/components/com_jem/controller.php');

// Get an instance of the controller
$controller = BaseController::getInstance('Jem');

// Perform the Request task
$input = Factory::getApplication()->input;
$task = $input->getCmd('task', '');
$view = $input->getCmd('view', 'main');
$taskController = strtolower(strtok($task, '.') ?: '');
$notificationViews = array(
    'notificationcontent', 'notificationhistory', 'notifications',
    'notificationtemplate', 'notificationtemplates', 'reminder', 'reminders',
);
$notificationControllers = array('notification', 'notificationcontent', 'notificationtemplate', 'reminder', 'reminders');
if (!JemFeaturePolicy::current()->allows(JemFeaturePolicy::FEATURE_NOTIFICATION_AUTOMATION)
    && (in_array($view, $notificationViews, true) || in_array($taskController, $notificationControllers, true))) {
    throw new \Exception(Text::_('COM_JEM_EVENT_FEATURE_NOTIFICATION_DISABLED'), 403);
}
$controller->execute($task);
HTMLHelper::_('bootstrap.tooltip','.hasTooltip');

// Redirect if set by the controller
$controller->redirect();
?>
