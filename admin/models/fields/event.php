<?php
/**
 * @version    4.2.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

jimport('joomla.form.formfield');
jimport('joomla.html.parameter.element');
jimport('joomla.form.helper');

JFormHelper::loadFieldClass('list');

/**
 * Renders an event element
 *
 * @package JEM
 *
 */
class JFormFieldEvent extends JFormFieldList
{
	protected $type = 'title';

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
		$script[] = '	function elSelectEvent(id, title, object) {';
		$script[] = '		document.getElementById("'.$this->id.'_id").value = id;';
		$script[] = '		document.getElementById("'.$this->id.'_name").value = title;';
		// $script[] = '		SqueezeBox.close();';
		$script[] = '        $("#event-modal").modal("hide");';
		$script[] = '	}';

		// Add the script to the document head.
		Factory::getApplication()->getDocument()->addScriptDeclaration(implode("\n", $script));

		// Setup variables for display.
		$html = array();
		$link = 'index.php?option=com_jem&amp;view=eventelement&amp;tmpl=component&amp;object='.$this->id;

		$db = Factory::getContainer()->get('DatabaseDriver');
		$db->setQuery(
			'SELECT title' .
			' FROM #__jem_events' .
			' WHERE id = '.(int) $this->value
		);		

		try
		{
			$title = $db->loadResult();
		}
		catch (RuntimeException $e)
		{			
			Factory::getApplication()->enqueueMessage($e->getMessage(), 'warning');
		}

		if (empty($title)) {
			$title = Text::_('COM_JEM_SELECT_EVENT');
		}
		$title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

		// The current user display field.
		$html[] = '<div class="fltlft">';
		$html[] = '  <input type="text" id="'.$this->id.'_name" value="'.$title.'" disabled="disabled" size="35" class="form-control valid form-control-success" />';
		$html[] = '</div>';

		// The user select button.
		$html[] = '<div class="button2-left">';
		$html[] = '  <div class="blank">';
		$html[] = JHtml::_(
			'bootstrap.renderModal',
			'event-modal',
			array(		
				'url'    => $link.'&amp;'.JSession::getFormToken().'=1',
				'title'  => Text::_('COM_JEM_SELECT_EVENT'),
				'width'  => '800px',
				'height' => '450px',
				'footer' => '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' . Text::_('COM_JEM_CLOSE') . '</button>'
			)
		);
		$html[] ='<button type="button" class="btn btn-link" data-bs-toggle="modal"  data-bs-target="#event-modal">'.Text::_('COM_JEM_SELECT_EVENT').'
</button>';
		$html[] = '  </div>';
		$html[] = '</div>';

		// The active event-id field.
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
