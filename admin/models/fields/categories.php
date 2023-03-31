<?php
/**
 * @version     2.3.15
 * @package     JEM
 * @copyright   Copyright (C) 2013-2023 joomlaeventmanager.net
 * @copyright   Copyright (C) 2005-2009 Christoph Lukes
 * @license     https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

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
		// JHtml::_('behavior.modal', 'a.modal');

		// Build the script.
		$script = array();
		$script[] = '    function jSelectCategory_'.$this->id.'(id, category, object) {';
		$script[] = '		document.getElementById("'.$this->id.'_id").value = id;';
		$script[] = '		document.getElementById("'.$this->id.'_name").value = category;';
		// $script[] = '		SqueezeBox.close();';
		$script[] = '        $("#categories-modal").modal("hide");';
		
		$script[] = '	};';

		// Add the script to the document head.
		JFactory::getDocument()->addScriptDeclaration(implode("\n", $script));

		// Setup variables for display.
		$html = array();
		$link = 'index.php?option=com_jem&amp;view=categoryelement&amp;tmpl=component&amp;function=jSelectCategory_'.$this->id;

		$db = Factory::getContainer()->get('DatabaseDriver');
		$query = $db->getQuery(true);
		$query->select('catname');
		$query->from('#__jem_categories');
		$query->where('id='.(int)$this->value);
		

		try
		{
			$db->setQuery($query);
			$category = $db->loadResult();
		}
		catch (RuntimeException $e)
		{			
			\Joomla\CMS\Factory::getApplication()->enqueueMessage($e->getMessage(), 'warning');
		}
		// if ($error = $db->getErrorMsg()) {
		// 	\Joomla\CMS\Factory::getApplication()->enqueueMessage($error, 'warning');
		// }

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
		// $html[] = '	<a class="modal" title="'.JText::_('COM_JEM_SELECT_CATEGORY').'"  href="'.$link.'&amp;'.JSession::getFormToken().'=1" rel="{handler: \'iframe\', size: {x: 800, y: 450}}">'.
		// 				JText::_('COM_JEM_SELECT_CATEGORY').'</a>';
		$html[] = JHtml::_(
			'bootstrap.renderModal',
			'categories-modal',
			array(		
				'url'    => $link.'&amp;'.JSession::getFormToken().'=1',
				'title'  => JText::_('COM_JEM_SELECT_CATEGORY'),
				'width'  => '800px',
				'height' => '450px',
				'footer' => '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>'
			)
		);
		$html[] ='<button type="button" class="btn btn-link" data-bs-toggle="modal" data-bs-target="#categories-modal">'.JText::_('COM_JEM_SELECT_CATEGORY').'
</button>';
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
