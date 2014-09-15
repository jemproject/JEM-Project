<?php
/**
 * @version     2.0.0
 * @package     JEM
 * @copyright   Copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright   Copyright (C) 2005-2009 Christoph Lukes
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die();

jimport('joomla.form.formfield');
JFormHelper::loadFieldClass('list');

/**
 * Category select
 *
 * @package JEM
 *
 */
class JFormFieldCategories extends JFormFieldList
{
	protected $type = 'Categories';

	/**
	 * Method to get the field input markup.
	 *
	 * @return	string	The field input markup.
	 *
	 */
	protected function getInput()
	{
		// Load the modal behavior script.
		JHtml::_('behavior.modal', 'a.modal');

		// Build the script.
		$script = array();
		$script[] = '    function jSelectCategory_'.$this->id.'(id, category, object) {';
		$script[] = '		document.id("'.$this->id.'_id").value = id;';
		$script[] = '		document.id("'.$this->id.'_name").value = category;';
		$script[] = '		SqueezeBox.close();';
		$script[] = '	};';

		// Add the script to the document head.
		JFactory::getDocument()->addScriptDeclaration(implode("\n", $script));

		// Setup variables for display.
		$html = array();
		$link = 'index.php?option=com_jem&amp;view=categoryelement&amp;tmpl=component&amp;function=jSelectCategory_'.$this->id;

		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('catname');
		$query->from('#__jem_categories');
		$query->where('id='.(int)$this->value);
		$db->setQuery($query);

		$category = $db->loadResult();

		if ($error = $db->getErrorMsg()) {
			JError::raiseWarning(500, $error);
		}

		if (empty($category)) {
			$category = JText::_('COM_JEM_SELECT_CATEGORY');
		}
		$category = htmlspecialchars($category, ENT_QUOTES, 'UTF-8');

		// The current user display field.
		$html[] = '<div class="fltlft">';
		$html[] = '  <input type="text" id="'.$this->id.'_name" value="'.$category.'" disabled="disabled" size="35" />';
		$html[] = '</div>';

		// The user select button.
		$html[] = '<div class="button2-left">';
		$html[] = '  <div class="blank">';
		$html[] = '	<a class="modal" title="'.JText::_('COM_JEM_SELECT_CATEGORY').'"  href="'.$link.'&amp;'.JSession::getFormToken().'=1" rel="{handler: \'iframe\', size: {x: 800, y: 450}}">'.
						JText::_('COM_JEM_SELECT_CATEGORY').'</a>';
		$html[] = '  </div>';
		$html[] = '</div>';

		// The active category-id field.
		if (0 == (int)$this->value) {
			$value = '';
		} else {
			$value = (int)$this->value;
		}

		// class='required' for client side validation
		$class = '';
		if ($this->required) {
			$class = ' class="required modal-value"';
		}

		$html[] = '<input type="hidden" id="'.$this->id.'_id"'.$class.' name="'.$this->name.'" value="'.$value.'" />';

		return implode("\n", $html);
	}
}
?>