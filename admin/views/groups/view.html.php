<?php
/**
 * @version    4.2.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Factory;

/**
 * View class for the JEM Groups screen
 *
 * @package Joomla
 * @subpackage JEM
 *
 */

class JemViewGroups extends JemAdminView
{
	protected $items;
	protected $pagination;
	protected $state;

	public function display($tpl = null)
	{
		$user        = JemFactory::getUser();
		$jemsettings = JEMAdmin::config();

		// Initialise variables.
		$this->items      = $this->get('Items');
		$this->pagination = $this->get('Pagination');
		$this->state      = $this->get('State');

		// loading Mootools
		// HTMLHelper::_('behavior.framework');

		// Load css
		// HTMLHelper::_('stylesheet', 'com_jem/backend.css', array(), true);
		$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
	
		$wa->registerStyle('jem.backend', 'com_jem/backend.css')->useStyle('jem.backend');
		// add style to description of the tooltip (hastip)
		// HTMLHelper::_('behavior.tooltip');

		// assign data to template
		$this->user			= $user;
		$this->jemsettings  = $jemsettings;

		// add toolbar
		$this->addToolbar();

		parent::display($tpl);
		}


	/**
	 * Add Toolbar
	 */
	protected function addToolbar()
	{
		ToolbarHelper::title(Text::_('COM_JEM_GROUPS'), 'groups');

		/* retrieving the allowed actions for the user */
		$canDo = JEMHelperBackend::getActions(0);

		/* create */
		if (($canDo->get('core.create'))) {
			ToolbarHelper::addNew('group.add');
		}

		/* edit */
		if (($canDo->get('core.edit'))) {
			ToolbarHelper::editList('group.edit');
			ToolbarHelper::divider();
		}

		if ($canDo->get('core.edit.state')) {
			ToolbarHelper::checkin('groups.checkin');
		}

		ToolbarHelper::deleteList('COM_JEM_CONFIRM_DELETE', 'groups.remove', 'JACTION_DELETE');

		ToolbarHelper::divider();
		ToolBarHelper::help('listgroups', true, 'https://www.joomlaeventmanager.net/documentation/manual/backend/groups');
	}
}
?>
