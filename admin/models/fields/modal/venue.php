<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2025 joomlaeventmanager.net
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
		$script[] = '        $("#venue-modal-1").modal("hide");';
		$script[] = '    }';

		// Add to document head
		Factory::getApplication()->getDocument()->getWebAssetManager()->addInlineScript(implode("\n", $script));

		// Setup variables for display
		$html = array();
		$link = 'index.php?option=com_jem&amp;view=venueelement&amp;tmpl=component&amp;function=jSelectVenue_'.$this->id;

		$db = Factory::getContainer()->get('DatabaseDriver');
		$query = $db->getQuery(true);
		$query->select('venue');
		$query->from('#__jem_venues');
		$query->where(array('id='.(int)$this->value));
		

		// if ($error = $db->getErrorMsg()) {
		//  Factory::getApplication()->enqueueMessage($error, 'warning');
		// }
		try
		{
			$db->setQuery($query);
			$venue = $db->loadResult();
		}
		catch (RuntimeException $e)
		{			
			Factory::getApplication()->enqueueMessage($e->getMessage(), 'notice');
		}

		if (empty($venue)) {
			$venue = Text::_('COM_JEM_SELECTVENUE');
		}
		$venue = htmlspecialchars($venue, ENT_QUOTES, 'UTF-8');

		// The current venue input field
		$html[] = '<div class="fltlft">';
		$html[] = '  <input type="text" id="'.$this->id.'_name" value="'.$venue.'" disabled="disabled" size="35" class="form-control valid form-control-success" />';
		$html[] = '</div>';

		// The venue select button
		$html[] = '<div class="button2-left">';
		$html[] = '  <div class="blank">';
		// $html[] = '    <a class="modal" title="'.Text::_('COM_JEM_SELECT').'" href="'.$link.'&amp;'.Session::getFormToken().'=1" rel="{handler: \'iframe\', size: {x:800, y:450}}">'.
		// 			Text::_('COM_JEM_SELECT').'</a>';
		$html[] = HTMLHelper::_(
			'bootstrap.renderModal',
			'venue-modal-1',
			array(		
				'url'    => $link.'&amp;'.Session::getFormToken().'=1',
				'title'  => Text::_('COM_JEM_SELECT'),
				'width'  => '800px',
				'height' => '450px',
				'footer' => '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' . Text::_('COM_JEM_CLOSE') . '</button>'
			)
		);
		$html[] ='<button type="button" class="btn btn-link btn-primary" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#venue-modal-1">'.Text::_('COM_JEM_SELECT').'
		</button>';
		$html[] = '  </div>';
		$html[] = '</div>';

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
