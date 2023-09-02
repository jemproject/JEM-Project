<?php
/**
 * @version    4.1.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Access\Access;
use Joomla\CMS\HTML\HTMLHelper;

require_once(JPATH_SITE.'/components/com_jem/factory.php');


// class JemSidebarHelper extends HTMLHelperSidebar
class JemSidebarHelper extends JHtmlSidebar

{
    public static function render()
    {
        /* Do nothing */
    }

    public static function getEntries()
    {
        return array();
    }
}


/**
 * Helper: Backend
 */
class JemHelperBackend
{

	public static $extension = 'com_jem';

	/**
	 * Configure the Linkbar.
	 *
	 * @param	string	The name of the active view.
	 *
	 * @return	void
	 *
	 */
	public static function addSubmenu($vName)
	{
		JemSidebarHelper::addEntry(
			Text::_('COM_JEM_SUBMENU_MAIN'),
			'index.php?option=com_jem&view=main',
			$vName == 'main'
		);

		JemSidebarHelper::addEntry(
			Text::_('COM_JEM_EVENTS'),
			'index.php?option=com_jem&view=events',
			$vName == 'events'
		);

		JemSidebarHelper::addEntry(
			Text::_('COM_JEM_VENUES'),
			'index.php?option=com_jem&view=venues',
			$vName == 'venues'
		);

		JemSidebarHelper::addEntry(
			Text::_('COM_JEM_CATEGORIES'),
			'index.php?option=com_jem&view=categories',
			$vName == 'categories'
		);

		JemSidebarHelper::addEntry(
			Text::_('COM_JEM_GROUPS'),
			'index.php?option=com_jem&view=groups',
			$vName == 'groups'
		);

		if (JemFactory::getUser()->authorise('core.manage', 'com_jem')) {
			JemSidebarHelper::addEntry(
				Text::_('COM_JEM_SETTINGS_TITLE'),
				'index.php?option=com_jem&view=settings',
				$vName == 'settings'
			);

			JemSidebarHelper::addEntry(
				Text::_('COM_JEM_HOUSEKEEPING'),
				'index.php?option=com_jem&amp;view=housekeeping',
				$vName == 'housekeeping'
			);

			JemSidebarHelper::addEntry(
				Text::_('COM_JEM_UPDATECHECK_TITLE'),
				'index.php?option=com_jem&amp;view=updatecheck',
				$vName == 'updatecheck'
			);

			JemSidebarHelper::addEntry(
				Text::_('COM_JEM_IMPORT_DATA'),
				'index.php?option=com_jem&amp;view=import',
				$vName == 'import'
			);

			JemSidebarHelper::addEntry(
				Text::_('COM_JEM_EXPORT_DATA'),
				'index.php?option=com_jem&amp;view=export',
				$vName == 'export'
			);

			JemSidebarHelper::addEntry(
				Text::_('COM_JEM_CSSMANAGER_TITLE'),
				'index.php?option=com_jem&amp;view=cssmanager',
				$vName == 'cssmanager'
			);
		}

		JemSidebarHelper::addEntry(
			Text::_('COM_JEM_HELP'),
			'index.php?option=com_jem&view=help',
			$vName == 'help'
		);
	}

	/**
	 * Gets a list of the actions that can be performed.
	 *
	 * @param	int		The category ID.
	 *
	 * @return	JObject
	 */
	public static function getActions($categoryId = 0)
	{
		$user	= JemFactory::getUser();
		$result	= new JObject;

		if (empty($categoryId)) {
			$assetName = 'com_jem';
			$level = 'component';
		} else {
			$assetName = 'com_jem.category.'.(int) $categoryId;
			$level = 'category';
		}

		// $actions = Access::getActions('com_jem', $level);
		$actions = Access::getActionsFromFile(JPATH_ADMINISTRATOR.'/components/com_jem/access.xml',"/access/section[@name='".$level."']/");

		foreach ($actions as $action) {
			$result->set($action->name,	$user->authorise($action->name, $assetName));
		}

		return $result;
	}

	public static function getCountryOptions()
	{
		$options = array();
		$options = array_merge(JEMHelperCountries::getCountryOptions(),$options);

		array_unshift($options, HTMLHelper::_('select.option', '0', Text::_('COM_JEM_SELECT_COUNTRY')));

		return $options;
	}

}
