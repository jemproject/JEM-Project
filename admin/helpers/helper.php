<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

/**
 *  component helper.
 *
 * @subpackage	com_jem
 *
 */
class JEMHelperBackend
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
		JSubMenuHelper::addEntry(
			JText::_('COM_JEM_SUBMENU_MAIN'),
			'index.php?option=com_jem&view=main',
			$vName == 'main'
		);

		JSubMenuHelper::addEntry(
			JText::_('COM_JEM_EVENTS'),
			'index.php?option=com_jem&view=events',
			$vName == 'events'
		);

		JSubMenuHelper::addEntry(
			JText::_('COM_JEM_VENUES'),
			'index.php?option=com_jem&view=venues',
			$vName == 'venues'
		);

		JSubMenuHelper::addEntry(
			JText::_('COM_JEM_CATEGORIES'),
			'index.php?option=com_jem&view=categories',
			$vName == 'categories'
		);

		JSubMenuHelper::addEntry(
		JText::_('COM_JEM_GROUPS'),
		'index.php?option=com_jem&view=groups',
		$vName == 'groups'
				);

		JSubMenuHelper::addEntry(
		JText::_('COM_JEM_HELP'),
		'index.php?option=com_jem&view=help',
		$vName == 'help'
				);

		if (JFactory::getUser()->authorise('core.manage')) {
			JSubMenuHelper::addEntry(
			JText::_('COM_JEM_SETTINGS_TITLE'),
			'index.php?option=com_jem&view=settings',
			$vName == 'settings'
					);
		}

	}

	/**
	 * Gets a list of the actions that can be performed.
	 *
	 * @param	int		The category ID.
	 *
	 * @return	JObject
	 *
	 *
	 */
	public static function getActions($categoryId = 0)
	{

		$user	= JFactory::getUser();
		$result	= new JObject;

		if (empty($categoryId)) {
			$assetName = 'com_jem';
			$level = 'component';
		} else {
			$assetName = 'com_jem.category.'.(int) $categoryId;
			$level = 'category';
		}

		$actions = JAccess::getActions('com_jem', $level);

		foreach ($actions as $action) {
			$result->set($action->name,	$user->authorise($action->name, $assetName));
		}

		return $result;
	}



	public static function getCountryOptions()
	{

		$options = array();
		$options = array_merge(JEMHelper::getCountryOptions(),$options);

		array_unshift($options, JHtml::_('select.option', '0', JText::_('COM_JEM_SELECT_COUNTRY')));

		return $options;
	}

}