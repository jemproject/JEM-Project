<?php
/**
 * @version    4.2.2
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Form\FormField;

/**
 * Contact select
 */
class JFormFieldModal_Contact extends FormField
{
	/**
	 * field type
	 */
	protected $type = 'Modal_Contact';


	/**
	 * Method to get the field input markup
	 */
	protected function getInput()
	{
		// Build the script
		$script = array();
		$script[] = '    function jSelectContact_'.$this->id.'(id, name, object) {';
		$script[] = '        document.getElementById("'.$this->id.'_id").value = id;';
		$script[] = '        document.getElementById("'.$this->id.'_name").value = name;';
		// $script[] = '        SqueezeBox.close();';
		$script[] = '        $("#contact-modal").modal("hide");';
		$script[] = '    }';

		// Add to document head
		Factory::getApplication()->getDocument()->addScriptDeclaration(implode("\n", $script));

		// Setup variables for display
		$html = array();
		$link = 'index.php?option=com_jem&amp;view=contactelement&amp;tmpl=component&amp;function=jSelectContact_'.$this->id;

		$db = Factory::getContainer()->get('DatabaseDriver');
		$query = $db->getQuery(true);
		$query->select('name');
		$query->from('#__contact_details');
		$query->where(array('id='.(int)$this->value));
		

		// if ($error = $db->getErrorMsg()) {
		// 	Factory::getApplication()->enqueueMessage($error, 'warning');
		// }
		try
		{
			$db->setQuery($query);

		$contact = $db->loadResult();
		}
		catch (RuntimeException $e)
		{			
			Factory::getApplication()->enqueueMessage($e->getMessage(), 'notice');
		}

		if (empty($contact)) {
			$contact = Text::_('COM_JEM_SELECTCONTACT');
		}
		$contact = htmlspecialchars($contact, ENT_QUOTES, 'UTF-8');

		// The current contact input field
		$html[] = '<div class="fltlft">';
		$html[] = '  <input type="text" id="'.$this->id.'_name" value="'.$contact.'" disabled="disabled" size="35" class="form-control valid form-control-success" />';
		$html[] = '</div>';

		// The contact select button
		$html[] = '<div class="button2-left">';
		$html[] = '  <div class="blank">';
		// $html[] = '    <a class="modal" title="'.Text::_('COM_JEM_SELECT').'" href="'.$link.'&amp;'.Session::getFormToken().'=1" rel="{handler: \'iframe\', size: {x:800, y:450}}">'.
		// 			Text::_('COM_JEM_SELECT').'</a>';
		$html[] = HTMLHelper::_(
			'bootstrap.renderModal',
			'contact-modal',
			array(		
				'url'    => $link.'&amp;'.Session::getFormToken().'=1',
				'title'  => Text::_('COM_JEM_SELECT'),
				'width'  => '800px',
				'height' => '450px',
				'footer' => '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' . Text::_('COM_JEM_CLOSE') . '</button>'
			)
		);
		$html[] ='<button type="button" class="btn btn-link btn-primary"  data-bs-toggle="modal" data-bs-target="#contact-modal">'.Text::_('COM_JEM_SELECT').'
		</button>';
		$html[] = '  </div>';
		$html[] = '</div>';

		// The active contact id field
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
