<?php
/**
 * @version    4.2.2
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Form\FormField;

/**
 * Venue Select
 */
class JFormFieldModal_Venue extends FormField
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
		// Build the script
		$script = array();
		$script[] = '    function jSelectVenue_'.$this->id.'(id, venue, object) {';
		$script[] = '        document.getElementById("'.$this->id.'_id").value = id;';
		$script[] = '        document.getElementById("'.$this->id.'_name").value = venue;';
		// $script[] = '        SqueezeBox.close();';
		$script[] = '        $("#venue-modal").modal("hide");';
		$script[] = '    }';

		// Add to document head
		Factory::getDocument()->addScriptDeclaration(implode("\n", $script));

		// Setup variables for display
		$html = array();
		$link = 'index.php?option=com_jem&amp;view=editevent&amp;layout=choosevenue&amp;tmpl=component&amp;function=jSelectVenue_'.$this->id;

		$db = Factory::getContainer()->get('DatabaseDriver');
		$query = $db->getQuery(true);
		$query->select('venue');
		$query->from('#__jem_venues');
		$query->where(array('id='.(int)$this->value));
		$db->setQuery($query);

		

		// if ($error = $db->getErrorMsg()) {
		// 	\Joomla\CMS\Factory::getApplication()->enqueueMessage($error, 'warning');
		// }
		try
		{
			$venue = $db->loadResult();
		}
		catch (RuntimeException $e)
		{			
			\Joomla\CMS\Factory::getApplication()->enqueueMessage($e->getMessage(), 'warning');
		}

		if (empty($venue)) {
			$venue = Text::_('COM_JEM_SELECT_VENUE');
		}
		$venue = htmlspecialchars($venue, ENT_QUOTES, 'UTF-8');

		// The current venue input field
		$html[] = '  <input type="text" id="'.$this->id.'_name" value="'.$venue.'" disabled="disabled" size="35" />';

		// The venue select button
		// $html[] = '    <a class="flyermodal" title="'.Text::_('COM_JEM_SELECT').'" href="'.$link.'&amp;'.Session::getFormToken().'=1" rel="{handler: \'iframe\', size: {x:800, y:450}}">'.
		// 			Text::_('COM_JEM_SELECT').'</a>';

		$html[] = HTMLHelper::_(
			'bootstrap.renderModal',
			'venue-modal',
			array(		
				'url'    => $link.'&amp;'.Session::getFormToken().'=1',
				'title'  => Text::_('COM_JEM_SELECT'),
				'width'  => '800px',
				'height' => '450px',
				'footer' => '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' . Text::_('COM_JEM_CLOSE') . '</button>'
			)
		);
		$html[] ='<button type="button" class="btn btn-link" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#venue-modal">'.Text::_('COM_JEM_SELECT').'
		</button>';

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
