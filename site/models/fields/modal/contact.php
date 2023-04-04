<?php
/**
 * @version 2.3.17
 * @package JEM
 * @copyright (C) 2013-2023 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.form.formfield');
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
/**
 * Contact select
 */
class JFormFieldModal_Contact extends JFormField
{
	/**
	 * field type
	 * @var string
	 */
	protected $type = 'Modal_Contact';


	/**
	 * Method to get the field input markup
	 */
	protected function getInput()
	{
		// Load modal behavior
		// HTMLHelper::_('behavior.modal', 'a.flyermodal');

		// Build the script
		$script = array();
		$script[] = '    function jSelectContact_'.$this->id.'(id, name, object) {';
		$script[] = '        document.getElementById("'.$this->id.'_id").value = id;';
		$script[] = '        document.getElementById("'.$this->id.'_name").value = name;';
		// $script[] = '        SqueezeBox.close();';
		$script[] = '        $("#contact-modal").modal("hide");';
		$script[] = '    }';

		

		// Add to document head
		Factory::getDocument()->addScriptDeclaration(implode("\n", $script));

		// Setup variables for display
		$html = array();
		$link = 'index.php?option=com_jem&amp;view=editevent&amp;layout=choosecontact&amp;tmpl=component&amp;function=jSelectContact_'.$this->id;

		$db = Factory::getContainer()->get('DatabaseDriver');
		$query = $db->getQuery(true);
		$query->select('name');
		$query->from('#__contact_details');
		$query->where(array('id='.(int)$this->value));
		$db->setQuery($query);

		// $contact = $db->loadResult();

		// if ($error = $db->getErrorMsg()) {
		// 	\Joomla\CMS\Factory::getApplication()->enqueueMessage($error, 'warning');
		// }

		try
		{
			$contact = $db->loadResult();
		}
		catch (RuntimeException $e)
		{			
			\Joomla\CMS\Factory::getApplication()->enqueueMessage($e->getMessage(), 'warning');
		}

		if (empty($contact)) {
			$contact = Text::_('COM_JEM_SELECT_CONTACT');
		}
		$contact = htmlspecialchars($contact, ENT_QUOTES, 'UTF-8');

		// The current contact input field
		$html[] = '  <input type="text" id="'.$this->id.'_name" value="'.$contact.'" disabled="disabled" size="35" />';

		// The contact select button
		// $html[] = '    <a class="flyermodal" title="'.Text::_('COM_JEM_SELECT').'" href="'.$link.'&amp;'.Session::getFormToken().'=1" rel="{handler: \'iframe\', size: {x:800, y:450}}">'.
		// 			Text::_('COM_JEM_SELECT').'</a>';

		$html[] = HTMLHelper::_(
			'bootstrap.renderModal',
			'contact-modal',
			array(		
				'url'    => $link.'&amp;'.Session::getFormToken().'=1',
				'title'  => Text::_('COM_JEM_SELECT'),
				'width'  => '800px',
				'height' => '450px',
				'footer' => '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>'
			)
		);
		$html[] ='<button type="button" class="btn btn-link"  data-bs-toggle="modal" data-bs-target="#contact-modal">'.Text::_('COM_JEM_SELECT').'
		</button>';


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
