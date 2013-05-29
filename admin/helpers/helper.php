<?php
/**
 * @copyright	Copyright (C) 2005 - 2013 Open Source Matters, Inc. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

/**
 *  component helper.
 *
 * @package		Joomla.Administrator
 * @subpackage	com_jem
 * @since		1.6
 */
class JEMHelperBackend
{
	/**
	 * Configure the Linkbar.
	 *
	 * @param	string	The name of the active view.
	 *
	 * @return	void
	 * @since	1.6
	 */
	public static function addSubmenu($vName)
	{
		JSubMenuHelper::addEntry(
			JText::_('COM_JEM_JEM'),
			'index.php?option=com_jem&view=jem',
			$vName == 'jem'
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
		JText::_('COM_JEM_ARCHIVESCREEN'),
		'index.php?option=com_jem&view=archive',
		$vName == 'archive'
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
		
		if (JFactory::getUser()->authorise('core.manage', 'com_jem')) {
			JSubMenuHelper::addEntry(
			JText::_('COM_JEM_SETTINGS'),
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
	 * @since	1.6
	 * 
	 */
	public static function getActions($categoryId = 0)
	{
		
		/* @todo sort out the getActions function*/
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


}
