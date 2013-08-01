<?php
/**
 * @version 1.9
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('JPATH_BASE') or die;

//JFormHelper::loadFieldClass('list');

jimport('joomla.html.html');
jimport('joomla.form.formfield');

require_once dirname(__FILE__) . '/../../helpers/helper.php';

/**
 * CountryOptions Field class.
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
	
	
		//$categories = JEMCategories::getCategoriesTree(1);
		//$selectedcats = $this->get( 'Catsselected' );
	
		//build selectlists
		//$Lists = array();
		//$Lists['category'] = JEMCategories::buildcatselect($categories, 'cid[]', $selectedcats, 0, 'multiple="multiple" size="8"');
	
		//$query = 'SELECT DISTINCT catid FROM #__jem_cats_event_relations WHERE itemid = ' . (int)$this->_id;
		//$this->_db->setQuery($query);
		//$used = $this->_db->loadColumn();
		//return $used;
	
		// static function buildcatselect($list, $name, $selected, $top, $class = 'class="inputbox"')
		// {
		//	$catlist = array();
		//
		// if ($top) {
		//		$catlist[] = JHTML::_('select.option', '0', JText::_('COM_JEM_TOPLEVEL'));
		//	}
		//
		//	$catlist = array_merge($catlist, JEMCategories::getcatselectoptions($list));
		//
		//	return JHTML::_('select.genericlist', $catlist, $name, $class, 'value', 'text', $selected);
		// }
	
	
		$options = array();
	
		$attr = '';
	
		// Initialize some field attributes.
		$attr .= $this->element['class'] ? ' class="'.(string) $this->element['class'].'"' : '';
	
		// To avoid user's confusion, readonly="true" should imply disabled="true".
		if ( (string) $this->element['readonly'] == 'true' || (string) $this->element['disabled'] == 'true') {
			$attr .= ' disabled="disabled"';
		}
	
		//$attr .= $this->element['size'] ? ' size="'.(int) $this->element['size'].'"' : '';
		$attr .= $this->multiple ? ' multiple="multiple"' : '';
	
		// Initialize JavaScript field attributes.
		$attr .= $this->element['onchange'] ? ' onchange="'.(string) $this->element['onchange'].'"' : '';
	
	
		
	
		// Output
		$currentid = JFactory::getApplication()->input->getInt('id');
		
		
		$categories = JEMCategories::getCategoriesTree(1);
		
		$db		= JFactory::getDbo();
		$query	= $db->getQuery(true);
		//$query = 'SELECT DISTINCT catid FROM #__jem_cats_event_relations WHERE itemid = ' . (int)$this->_id;
		$query = 'SELECT DISTINCT catid FROM #__jem_cats_event_relations WHERE itemid = '. $currentid;
		
		$db->setQuery($query);
		$selectedcats = $db->loadColumn();
		

		
		//var_dump($var2);exit;
		
		//var_dump($selectedcats);exit;
		//var_dump($_POST);exit;
		//var_dump($attr);exit;
		
		
	
		return JEMCategories::buildcatselect($categories, 'cid[]', $selectedcats, 0, trim($attr));
	
	
	}
	
}
