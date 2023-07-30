<?php
/**
 * @version 4.0.1-dev1
 * @package JEM
 * @copyright (C) 2013-2023 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

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
		$app      = Factory::getApplication();
		$document = $app->getDocument();

		// Build the script.
		$script = array();
		$script[] = '    function jSelectCategory_'.$this->id.'(id, category, object) {';
		$script[] = '		document.getElementById("'.$this->id.'_id").value = id;';
		$script[] = '		document.getElementById("'.$this->id.'_name").value = category;';
		// $script[] = '		SqueezeBox.close();';
		$script[] = '        $("#categories-modal").modal("hide");';
		
		$script[] = '	};';

		// Add the script to the document head.
		$document->addScriptDeclaration(implode("\n", $script));

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
			$app->enqueueMessage($e->getMessage(), 'warning');
		}
		// if ($error = $db->getErrorMsg()) {
		// 	Factory::getApplication()->enqueueMessage($error, 'warning');
		// }

		if (empty($category)) {
			$category = Text::_('COM_JEM_SELECT_CATEGORY');
		}
		$category = htmlspecialchars($category, ENT_QUOTES, 'UTF-8');

		// The current user display field.
		$html[] = '<div class="fltlft">';
		$html[] = '  <input type="text" id="'.$this->id.'_name" value="'.$category.'" disabled="disabled" size="35" class="form-control valid form-control-success" />';
		$html[] = '</div>';

		// The user select button.
		$html[] = '<div class="button2-left">';
		$html[] = '  <div class="blank">';
		$html[] = JHtml::_(
			'bootstrap.renderModal',
			'categories-modal',
			array(		
				'url'    => $link.'&amp;'.JSession::getFormToken().'=1',
				'title'  => Text::_('COM_JEM_SELECT_CATEGORY'),
				'width'  => '800px',
				'height' => '450px',
				'footer' => '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' . Text::_('COM_JEM_CLOSE') . '</button>'
			)
		);
		$html[] ='<button type="button" class="btn btn-link" data-bs-toggle="modal" data-bs-target="#categories-modal">'.Text::_('COM_JEM_SELECT_CATEGORY').'
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
