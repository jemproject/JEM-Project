<?php
/**
 * @version    4.2.1
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Session\Session;

/**
 * Contact select
 */
class JFormFieldModal_Users extends FormField
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
		// HTMLHelper::_('behavior.modal', 'a.flyermodal');

		// Build the script
		$script = array();
		$script[] = '    function jSelectUsers_'.$this->id.'(ids, count, object) {';
		$script[] = '        document.getElementById("'.$this->id.'_ids").value = ids;';
		$script[] = '        document.getElementById("'.$this->id.'_count").value = count;';
		// $script[] = '        SqueezeBox.close();';
		$script[] = '        $("#user-modal").modal("hide");';
		$script[] = '    }';

		// Add to document head
		Factory::getDocument()->addScriptDeclaration(implode("\n", $script));

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
			$db = Factory::getContainer()->get('DatabaseDriver');
			$query = $db->getQuery(true);
			$query->select('COUNT(id)');
			$query->from('#__users');
			$query->where('id IN ('.$idlist.')');
			$db->setQuery($query);

			

			// if ($error = $db->getErrorMsg()) {
			// 	\Joomla\CMS\Factory::getApplication()->enqueueMessage($error, 'warning');
			// }
			try
			{
				$count = (int)$db->loadResult();
			}
			catch (RuntimeException $e)
			{			
				\Joomla\CMS\Factory::getApplication()->enqueueMessage($e->getMessage(), 'warning');
			}
		} else {
			$count = 0;
		}

	//	if (empty($count)) {
	//		$count = Text::_('COM_JEM_SELECT_USERS');
	//	}
	//	$count = htmlspecialchars($count, ENT_QUOTES, 'UTF-8');

		// The current contact input field
		$html[] = '  <input type="text" id="'.$this->id.'_count" value="'.$count.'" disabled="disabled" size="4" />';

		// The contact select button
		// $html[] = '    <a class="flyermodal" title="'.Text::_('COM_JEM_SELECT').'" href="'.$link.'&amp;'.Session::getFormToken().'=1" rel="{handler: \'iframe\', size: {x:800, y:450}}">'.
		// 			Text::_('COM_JEM_SELECT').'</a>';
		$html[] = HTMLHelper::_(
			'bootstrap.renderModal',
			'user-modal',
			array(		
				'url'    => $link.'&amp;'.Session::getFormToken().'=1',
				'title'  => Text::_('COM_JEM_SELECT'),
				'width'  => '800px',
				'height' => '450px',
				'footer' => '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' . Text::_('COM_JEM_CLOSE') . '</button>'
			)
		);
		$html[] ='<button type="button" class="btn btn-link" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#user-modal">'.Text::_('COM_JEM_SELECT').'
		</button>';

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
