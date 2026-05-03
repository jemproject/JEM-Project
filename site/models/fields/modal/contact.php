<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Form\FormField;

/**
 * Contact modal field for the front area.
 */
class JFormFieldModal_Contact extends FormField
{
    /**
     * field type
     * @var string
     */
    protected $type = 'Modal_Contact';

    /**
     * Method to get the field input markup
     * @return string The HTML markup
     */
    protected function getInput()
    {
        $app      = Factory::getApplication();
        $document = $app->getDocument();
        $wa       = $document->getWebAssetManager();

        // Unique ID for the modal based on the field ID
        $modalId = 'modal_' . $this->id;

        // Build the script
        $script = array();
        $script[] = '    function jSelectContact_'.$this->id.'(id, name, object) {';
        $script[] = '        document.getElementById("'.$this->id.'_id").value = id;';
        $script[] = '        document.getElementById("'.$this->id.'_name").value = name;';
        $script[] = '        bootstrap.Modal.getInstance(document.getElementById("' . $modalId . '")).hide();';
        $script[] = '    }';

        $wa->addInlineScript(implode("\n", $script));

        $currentValues = $this->value ? $this->value : '';
        $link = 'index.php?option=com_jem&view=editevent&layout=choosecontact&tmpl=component'
            . '&function=jSelectContact_' . $this->id
            . '&selected=' . $currentValues;

        $db = Factory::getContainer()->get('DatabaseDriver');
        $contactNames = array();

        if (!empty($this->value)) {
            // Clean IDs for the SQL query
            $ids = explode(',', $this->value);
            $ids = array_map('intval', $ids);

            $query = $db->getQuery(true)
                ->select($db->quoteName('name'))
                ->from($db->quoteName('#__contact_details'))
                ->where($db->quoteName('id') . ' IN (' . implode(',', $ids) . ')');

            try {
                $db->setQuery($query);
                $contactNames = $db->loadColumn();
            }
            catch (RuntimeException $e) {
                $app->enqueueMessage($e->getMessage(), 'warning');
            }
        }

        $displayText = !empty($contactNames) ? implode(', ', $contactNames) : Text::_('COM_JEM_SELECT_CONTACT');
        $displayText = htmlspecialchars($displayText, ENT_QUOTES, 'UTF-8');

        $html = array();
        $html[] = '<div class="input-group" style="width: auto; flex-grow: 1;">';
        $html[] = '  <input type="text" id="'.$this->id.'_name" class="form-control readonly" disabled="disabled" value="'.$displayText.'" readonly size="35" />';
        $html[] = '  <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#' . $modalId . '">';
        $html[] = '    <i class="icon-user"></i> ' . Text::_('COM_JEM_SELECT');
        $html[] = '  </button>';
        $html[] = '</div>';
        $html[] = HTMLHelper::_(
            'bootstrap.renderModal',
            $modalId,
            array(
                'url'    => $link . '&' . Session::getFormToken() . '=1',
                'title'  => Text::_('COM_JEM_SELECT'),
                'width'  => '800px',
                'height' => '450px',
                'footer' => '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' . Text::_('COM_JEM_CLOSE') . '</button>'
            )
        );

        // Input Hidden (El que realmente guarda los IDs en la base de datos)
        $class = $this->required ? ' class="required modal-value"' : '';
        $html[] = '<input type="hidden" id="' . $this->id . '_id"' . $class . ' name="' . $this->name . '" value="' . htmlspecialchars($currentValues, ENT_QUOTES, 'UTF-8') . '" />';

        return implode("\n", $html);
    }
}
?>
