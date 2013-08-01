<?php
/**
 * @copyright	Copyright (C) 2005 - 2013 Open Source Matters, Inc. All rights reserved.
 * @license		GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

/**
 *  component helper.
 *
 * @subpackage	com_jem
 * @since		1.6
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
		
		if (JFactory::getUser()->authorise('core.manage')) {
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
		// alternative way
		
		/*	$options = array();
	
		$db		= JFactory::getDbo();
		$query	= $db->getQuery(true);
	
		$query->select('iso2 As value, name As text');
		$query->from('#__jem_countries AS a');
		$query->order('a.name');

		
		// Get the options.
		$db->setQuery($query);
	
		$options = $db->loadObjectList();
	
		// Check for a database error.
		if ($db->getErrorNum()) {
			JError::raiseWarning(500, $db->getErrorMsg());
		}
		*/
	
		// Merge any additional options in the XML definition.
		//$options = array_merge(parent::getOptions(), $options);
		
		
		$options = array();
		$options = array_merge(JEMHelper::getCountryOptions(),$options);
		
		array_unshift($options, JHtml::_('select.option', '0', JText::_('COM_JEM_SELECT_COUNTRY')));
	
		return $options;
	}
	
	
	
	
	
	public static function getCatOptions()
	{
		
		$db		= JFactory::getDbo();
		$query	= $db->getQuery(true);
		//$query = 'SELECT DISTINCT catid FROM #__jem_cats_event_relations WHERE itemid = ' . (int)$this->_id;
		$query = 'SELECT DISTINCT catid FROM #__jem_cats_event_relations';
		
		$db->setQuery($query);
		$catselected = $db->loadColumn();
		
	
//		$categories = JEMCategories::getCategoriesTree(1);
//		$selectedcats = $this->get( 'Catsselected' );
	
//		$Lists = array();
//		$Lists['category'] = JEMCategories::buildcatselect($categories, 'cid[]', $selectedcats, 0, 'multiple="multiple" size="8"');
		
		// alternative way
	
		/*	$options = array();
	
		$db		= JFactory::getDbo();
		$query	= $db->getQuery(true);
	
		$query->select('iso2 As value, name As text');
		$query->from('#__jem_countries AS a');
		$query->order('a.name');
	
	
		// Get the options.
		$db->setQuery($query);
	
		$options = $db->loadObjectList();
	
		// Check for a database error.
		if ($db->getErrorNum()) {
		JError::raiseWarning(500, $db->getErrorMsg());
		}
		*/
	
		// Merge any additional options in the XML definition.
		//$options = array_merge(parent::getOptions(), $options);
	
	
		//$options = array();
		//$options = array_merge(JEMHelper::getCountryOptions(),$options);
	
		//array_unshift($options, JEMCategories::buildcatselect($categories, 'cid[]', $selectedcats, 0, 'multiple="multiple" size="8"'));
	
	$options = 'foobar';
		return $options;
	}
	

}
