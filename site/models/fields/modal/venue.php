<?php
/**
 * @version 2.0.0
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.form.formfield');

/**
 * Venue Select
 */
class JFormFieldModal_Venue extends JFormField
{
	/**
	 * field type
	 * @var string
	 */
	protected $type = 'Modal_Venue';


	/**
	 * Method to get the field input markup
	 */
	protected function getInput()
	{
		// Load modal behavior
		JHtml::_('behavior.modal', 'a.flyermodal');

		// Build the script
		$script = array();
		$script[] = '    function jSelectVenue_'.$this->id.'(id, venue, object) {';
		$script[] = '        document.id("'.$this->id.'_id").value = id;';
		$script[] = '        document.id("'.$this->id.'_name").value = venue;';
		$script[] = '        SqueezeBox.close();';
		$script[] = '    }';

		// Add to document head
		JFactory::getDocument()->addScriptDeclaration(implode("\n", $script));

		// Setup variables for display
		$html = array();
		$link = 'index.php?option=com_jem&amp;view=editevent&amp;layout=choosevenue&amp;tmpl=component&amp;function=jSelectVenue_'.$this->id;

		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('venue');
		$query->from('#__jem_venues');
		$query->where(array('id='.(int)$this->value));
		$db->setQuery($query);

		$venue = $db->loadResult();

		if ($error = $db->getErrorMsg()) {
			JError::raiseWarning(500, $error);
		}

		if (empty($venue)) {
			$venue = JText::_('COM_JEM_SELECT_VENUE');
		}
		$venue = htmlspecialchars($venue, ENT_QUOTES, 'UTF-8');

		// The current venue input field
		$html[] = '  <input type="text" id="'.$this->id.'_name" value="'.$venue.'" disabled="disabled" size="35" />';

		// The venue select button
		$html[] = '    <a class="flyermodal" title="'.JText::_('COM_JEM_SELECT').'" href="'.$link.'&amp;'.JSession::getFormToken().'=1" rel="{handler: \'iframe\', size: {x:800, y:450}}">'.
					JText::_('COM_JEM_SELECT').'</a>';

		// The active venue id field
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