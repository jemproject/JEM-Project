<?php
/**
 * @version 2.1.7
 * @package JEM
 * @copyright (C) 2013-2016 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

jimport('joomla.form.formfield');

/**
 * Contact select
 */
class JFormFieldModal_Users extends JFormField
{
	/**
	 * field type
	 * @var string
	 */
	protected $type = 'Modal_Users';


	/**
	 * Method to get the field input markup
	 */
	protected function getInput()
	{
		// Load modal behavior
		JHtml::_('behavior.modal', 'a.flyermodal');

		// Build the script
		$script = array();
		$script[] = '    function jSelectUsers_'.$this->id.'(ids, count, object) {';
		$script[] = '        document.id("'.$this->id.'_ids").value = ids;';
		$script[] = '        document.id("'.$this->id.'_count").value = count;';
		$script[] = '        SqueezeBox.close();';
		$script[] = '    }';

		// Add to document head
		JFactory::getDocument()->addScriptDeclaration(implode("\n", $script));

		// Setup variables for display
		$html = array();
		$eventid = isset($this->element['eventid']) ? (int)$this->element['eventid'] : 0;
		$link = 'index.php?option=com_jem&amp;view=editevent&amp;layout=chooseusers&amp;tmpl=component&amp;function=jSelectUsers_'.$this->id.'&amp;a_id='.$eventid;

		// we expect a list of unique, non-zero numbers
		$ids = explode(',', $this->value);
		array_walk($ids, function(&$v, $k){$v = (int)$v;});
		$ids = array_filter($ids);
		$ids = array_unique($ids);
		$idlist = implode(',', $ids);

		if (!empty($idlist)) {
			$db = JFactory::getDbo();
			$query = $db->getQuery(true);
			$query->select('COUNT(id)');
			$query->from('#__users');
			$query->where('id IN ('.$idlist.')');
			$db->setQuery($query);

			$count = (int)$db->loadResult();

			if ($error = $db->getErrorMsg()) {
				JError::raiseWarning(500, $error);
			}
		} else {
			$count = 0;
		}

	//	if (empty($count)) {
	//		$count = JText::_('COM_JEM_SELECT_USERS');
	//	}
	//	$count = htmlspecialchars($count, ENT_QUOTES, 'UTF-8');

		// The current contact input field
		$html[] = '  <input type="text" id="'.$this->id.'_count" value="'.$count.'" disabled="disabled" size="4" />';

		// The contact select button
		$html[] = '    <a class="flyermodal" title="'.JText::_('COM_JEM_SELECT').'" href="'.$link.'&amp;'.JSession::getFormToken().'=1" rel="{handler: \'iframe\', size: {x:800, y:450}}">'.
					JText::_('COM_JEM_SELECT').'</a>';

		// class='required' for client side validation
		$class = '';
		if ($this->required) {
			$class = ' class="required modal-value"';
		}

		$html[] = '<input type="hidden" id="'.$this->id.'_ids"'.$class.' name="'.$this->name.'" value="'.$idlist.'" />';
		$html[] = '<input type="hidden" id="'.$this->id.'_evid"'.$class.' value="'.$eventid.'" />';

		return implode("\n", $html);
	}
}
?>