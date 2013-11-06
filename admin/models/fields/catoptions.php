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
		$currentid = JFactory::getApplication()->input->getInt('id');
		$categories = JEMCategories::getCategoriesTree();


		$db		= JFactory::getDbo();
		$query	= $db->getQuery(true);
		$query = 'SELECT DISTINCT catid FROM #__jem_cats_event_relations WHERE itemid = '. $db->quote($currentid);

		$db->setQuery($query);
		$selectedcats = $db->loadColumn();

		return JEMCategories::buildcatselect($categories, 'cid[]', $selectedcats, 0, trim($attr));
	}
}
