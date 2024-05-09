<?php
/**
 * @version    4.2.2
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
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
	Factory::getApplication()->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'warning');
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

// Require the frontend base controller
require_once (JPATH_BASE.'/components/com_jem/controller.php');

// Get an instance of the controller
$controller = BaseController::getInstance('Jem');

// Perform the Request task
$input = Factory::getApplication()->input;
$controller->execute($input->getCmd('task'));
HTMLHelper::_('bootstrap.tooltip','.hasTooltip');

// Redirect if set by the controller
$controller->redirect();
?>
