<?php
/**
 * @version     2.1.5
 * @package     JEM
 * @copyright   Copyright (C) 2013-2015 joomlaeventmanager.net
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
		$db          = JFactory::getDbo();
		$user        = JemFactory::getUser();
		$userid      = (int) $user->get('id');

		if (empty($id)) {
			// for new events only show useable categories
			$mitems = $user->getJemCategories('add', 'event');
		} else {
			$query = $db->getQuery(true);
			$query = 'SELECT COUNT(*)'
				. ' FROM #__jem_events AS e'
				. ' WHERE e.id = ' . $db->quote($id)
				. '   AND e.created_by = ' . $db->quote($userid);
			$db->setQuery($query);
			$owner = $db->loadResult();

			// on edit show all categories user is allowed to see, disable non-useable categories
			$mitems = $user->getJemCategories(array('add', 'edit'), 'event', array('use_disable' => true, 'owner' => $owner));
		}

		if (!$mitems)
		{
			$mitems = array();
			$children = array();

			$parentid = 0;
		}
		else
		{
			$children = array();
			// First pass - collect children
			foreach ($mitems as $v)
			{
				$pt = $v->parent_id;
				$list = @$children[$pt] ? $children[$pt] : array();
				array_push($list, $v);
				$children[$pt] = $list;
			}

			// list childs of "root" which has no parent and normally id 1
			$parentid = intval(@isset($children[0][0]->id) ? $children[0][0]->id : 1);
		}

		//get list of the items
		$list = JEMCategories::treerecurse($parentid, '', array(), $children, 9999, 0, 0);

		// append orphaned categories
		if (count($mitems) > count($list)) {
			foreach ($children as $k => $v) {
				if (($k > 1) && !array_key_exists($k, $list)) {
					$list = JEMCategories::treerecurse($k, '?&nbsp;', $list, $children, 999, 0, 0);
				}
			}
		}

		return $list;
	}
}