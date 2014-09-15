<?php
/**
 * @version 2.0.0
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('JPATH_BASE') or die;

//JFormHelper::loadFieldClass('list');

jimport('joomla.html.html');
jimport('joomla.form.formfield');



/**
 * CountryOptions Field class.
 *
 *
 */
class JFormFieldCatOptions2 extends JFormField
{
	/**
	 * The form field type.
	 *
	 */
	protected $type = 'CatOptions2';

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


		//$attr .= $this->element['required'] ? ' class="required modal-value"' : "";

// 		if ($this->required) {
// 			$class = ' class="required modal-value"';
// 		}

		// Output

		//$categories = JEMCategories::getCategoriesTree(0);
		//$Lists['parent_id'] 		= JEMCategories::buildcatselect($categories, 'parent_id', $row->parent_id, 1);

		$currentid = JFactory::getApplication()->input->getInt('id');
		$categories = JEMCategories::getCategoriesTree(0);

		$db		= JFactory::getDbo();
		$query	= $db->getQuery(true);
		$query = 'SELECT DISTINCT parent_id FROM #__jem_categories WHERE id = '. $db->quote($currentid);

		$db->setQuery($query);
		$currentparent_id = $db->loadColumn();

		return JEMCategories::buildcatselect($categories, 'parent_id', $currentparent_id, 1);
	}
}
