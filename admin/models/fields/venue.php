<?php
/**
 * @version    4.2.1
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\Form\FormHelper;
use Joomla\CMS\HTML\HTMLHelper;

jimport('joomla.form.formfield');
jimport('joomla.html.parameter.element');

FormHelper::loadFieldClass('list');

/**
 * Renders an venue element
 *
 * @package JEM
 *
 */
class JFormFieldVenue extends ListField
{
	protected $type = 'Venue';

	/**
	 * Method to get the field input markup.
	 *
	 * @return	string	The field input markup.
	 *
	 */
	protected function getInput()
	{
		// Load the modal behavior script.
		// HTMLHelper::_('behavior.modal', 'a.modal');

		// Build the script.
		$script = array();
		$script[] = '	function elSelectVenue(id, venue, object) {';
		$script[] = '		document.getElementById("'.$this->id.'_id").value = id;';
		$script[] = '		document.getElementById("'.$this->id.'_name").value = venue;';
		// $script[] = '		SqueezeBox.close();';
		$script[] = '        $("#venue-modal").modal("hide");';
		$script[] = '	}';

		// Add the script to the document head.
		Factory::getApplication()->getDocument()->addScriptDeclaration(implode("\n", $script));

		// Setup variables for display.
		$html = array();
		$link = 'index.php?option=com_jem&amp;view=venueelement&amp;tmpl=component&amp;object='.$this->id;

		$db = Factory::getContainer()->get('DatabaseDriver');
		$db->setQuery(
			'SELECT venue' .
			' FROM #__jem_venues' .
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
			$title = Text::_('COM_JEM_SELECT_VENUE');
		}
		$title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

		// The current user display field.
		$html[] = '<div class="fltlft">';
		$html[] = '  <input type="text" id="'.$this->id.'_name" value="'.$title.'" disabled="disabled" size="35" class="form-control valid form-control-success" />';
		$html[] = '</div>';

		//
		$html[] = '<div class="button2-left">';
		$html[] = '  <div class="blank">';
		// $html[] = '	<a class="modal" title="'.Text::_('COM_JEM_SELECT_VENUE').'"  href="'.$link.'&amp;'.JSession::getFormToken().'=1" rel="{handler: \'iframe\', size: {x: 800, y: 450}}">'.Text::_('COM_JEM_SELECT_VENUE').'</a>';

		$html[] = HTMLHelper::_(
			'bootstrap.renderModal',
			'venue-modal',
			array(		
				'url'    => $link.'&amp;'.JSession::getFormToken().'=1',
				'title'  => Text::_('COM_JEM_SELECT_VENUE'),
				'width'  => '800px',
				'height' => '450px',
				'footer' => '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' . Text::_('COM_JEM_CLOSE') . '</button>'
			)
		);
		$html[] ='<button type="button" class="btn btn-link" data-bs-toggle="modal"  data-bs-target="#venue-modal">'.Text::_('COM_JEM_SELECT_VENUE').'
		</button>';
		$html[] = '  </div>';
		$html[] = '</div>';

		// The active venue-id field.
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
