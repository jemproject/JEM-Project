<?php
/**
 * @version     1.9.5
 * @package     JEM
 * @copyright   Copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright   Copyright (C) 2005-2009 Christoph Lukes
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('JPATH_BASE') or die;

//JFormHelper::loadFieldClass('list');

jimport('joomla.html.html');
jimport('joomla.form.formfield');

require_once dirname(__FILE__) . '/../../helpers/helper.php';

/**
 * CatOptions Field class.
 *
 *
 */
class JFormFieldCatOptions extends JFormField
{
	/**
	 * The form field type.
	 *
	 */
	protected $type = 'CatOptions';

	public function getInput()
	{
		$attr = '';

		// Initialize some field attributes.
		$attr .= $this->element['class'] ? ' class="'.(string) $this->element['class'].'"' : '';

		// To avoid user's confusion, readonly="true" should imply disabled="true".
		if ((string) $this->element['readonly'] == 'true' || (string) $this->element['disabled'] == 'true') {
			$attr .= ' disabled="disabled"';
		}

		//$attr .= $this->element['size'] ? ' size="'.(int) $this->element['size'].'"' : '';
		$attr .= $this->multiple ? ' multiple="multiple"' : '';

		// Initialize JavaScript field attributes.
		$attr .= $this->element['onchange'] ? ' onchange="'.(string) $this->element['onchange'].'"' : '';

		// Output
		$currentid = JFactory::getApplication()->input->getInt('a_id');
		
		//$categories = JEMCategories::getCategoriesTree();
		$categories = self::getCategories($currentid);
		
		$db		= JFactory::getDbo();
		$query	= $db->getQuery(true);
		$query = 'SELECT DISTINCT catid FROM #__jem_cats_event_relations WHERE itemid = '. $db->quote($currentid);

		$db->setQuery($query);
		$selectedcats = $db->loadColumn();

		return JEMCategories::buildcatselect($categories, 'cid[]', $selectedcats, 0, trim($attr));
	}
	
	
	/**
	 * logic to get the categories
	 *
	 * @access public
	 * @return void
	 */
	function getCategories($id)
	{
		$user		= JFactory::getUser();
		$jemsettings = JEMHelper::config();
		$userid		= (int) $user->get('id');
		$superuser	= JEMUser::superuser();
	
		// Support Joomla access levels instead of single group id
		$levels = $user->getAuthorisedViewLevels();
			
		$where = ' WHERE c.published = 1 AND c.access IN (' . implode(',', $levels) . ')';
	
			//get the ids of the categories the user maintaines
			$db		= JFactory::getDbo();
			$query	= $db->getQuery(true);
			$query = 'SELECT g.group_id'
					. ' FROM #__jem_groupmembers AS g'
					. ' WHERE g.member = '.$userid
					;
			$db->setQuery($query);
			$catids = $db->loadColumn();
			
			
			$query = 'SELECT gr.id' 
					. ' FROM #__jem_groups AS gr'
					. ' LEFT JOIN #__jem_groupmembers AS g ON g.group_id = gr.id'
					. ' WHERE g.member = ' . (int) $user->get('id')
					. ' AND ' .$db->quoteName('gr.addevent') . ' = 1 '
					. ' AND g.member NOT LIKE 0';
			$db->setQuery($query);
			$groupnumber = $db->loadColumn();	
			$categories = implode(' OR c.groupid = ', $groupnumber);
	
			//build ids query
			if ($categories) {
				//check if user is allowed to submit events in general, if yes allow to submit into categories
				//which aren't assigned to a group. Otherwise restrict submission into maintained categories only
				if (JEMUser::validate_user($jemsettings->evdelrec, $jemsettings->delivereventsyes)) {
					$where .= ' AND c.groupid = 0 OR c.groupid = '.$categories;
				} else {
					$where .= ' AND c.groupid = '.$categories;
						}
				} else {
					$where .= ' AND c.groupid = 0';
				}
	
		//administrators or superadministrators have access to all categories, also maintained ones
		if($superuser) {
			$where = ' WHERE c.published = 1';
		}
	
		//get the maintained categories and the categories whithout any group
		//or just get all if somebody have edit rights
		$db		= JFactory::getDbo();
		$query	= $db->getQuery(true);
		$query = 'SELECT c.*'
				. ' FROM #__jem_categories AS c'
				. $where
				. ' ORDER BY c.ordering'
				;
		$db->setQuery($query);
	
		//	$this->_category = array();
		//	$this->_category[] = JHTML::_('select.option', '0', JText::_( 'COM_JEM_SELECT_CATEGORY' ) );
		//	$this->_categories = array_merge( $this->_category, $this->_db->loadObjectList() );
	
		$mitems = $db->loadObjectList();

		// Check for a database error.
		if ($db->getErrorNum())
		{
			JError::raiseNotice(500, $db->getErrorMsg());
		}

		if (!$mitems)
		{
			$mitems = array();
			$children = array();

			$parentid = $mitems;
		}
		else
		{
			$mitems_temp = $mitems;

			$children = array();
			// First pass - collect children
			foreach ($mitems as $v)
			{
				$pt = $v->parent_id;
				$list = @$children[$pt] ? $children[$pt] : array();
				array_push($list, $v);
				$children[$pt] = $list;
			}

			$parentid = intval($mitems[0]->parent_id);
		}

		//get list of the items
		$list = JEMCategories::treerecurse($parentid, '', array(), $children, 9999, 0, 0);

		return $list;
	}	
}