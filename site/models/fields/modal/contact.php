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
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Uri\Uri;

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
     * Check whether Contact has selectable published contacts.
     *
     * @return  bool
     */
    public function hasAvailableContacts()
    {
        if (!ComponentHelper::isEnabled('com_contact')) {
            return false;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__contact_details'))
            ->where($db->quoteName('published') . ' = 1');

        try {
            $db->setQuery($query);

            return (int) $db->loadResult() > 0;
        } catch (RuntimeException $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'warning');

            return false;
        }
    }

    /**
     * Method to get the field input markup
     * @return string The HTML markup
     */
    protected function getInput()
    {
        $app      = Factory::getApplication();
        $currentValues = $this->value ? $this->value : '';

        if (!$this->hasAvailableContacts()) {
            return '<input type="hidden" id="' . $this->id . '_id" name="' . $this->name . '" value="' . htmlspecialchars($currentValues, ENT_QUOTES, 'UTF-8') . '" />';
        }

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

        $eventId = $app->input->getInt('a_id', 0);
        $link = Uri::base() . 'index.php?option=com_jem&view=editevent&layout=choosecontact&tmpl=component'
            . '&function=jSelectContact_' . $this->id
            . '&selected=' . $currentValues
            . ($eventId > 0 ? '&a_id=' . $eventId : '');

        $db = Factory::getContainer()->get('DatabaseDriver');
        $contactNames = array();

        if (!empty($this->value)) {
            // Clean IDs for the SQL query
            $ids = explode(',', $this->value);
            $ids = array_map('intval', $ids);

            $levels = array_map('intval', JemFactory::getUser()->getAuthorisedViewLevels());
            $query = $db->getQuery(true)
                ->select($db->quoteName('con.name'))
                ->from($db->quoteName('#__contact_details', 'con'))
                ->join(
                    'INNER',
                    $db->quoteName('#__categories', 'cat')
                    . ' ON ' . $db->quoteName('cat.id') . ' = ' . $db->quoteName('con.catid')
                )
                ->where($db->quoteName('con.id') . ' IN (' . implode(',', $ids) . ')')
                ->where($db->quoteName('con.published') . ' = 1')
                ->where($db->quoteName('con.access') . ' IN (' . implode(',', $levels) . ')')
                ->where($db->quoteName('cat.published') . ' = 1')
                ->where($db->quoteName('cat.access') . ' IN (' . implode(',', $levels) . ')');

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
                'url'    => $link,
                'title'  => Text::_('COM_JEM_SELECT_CONTACT'),
                'width'  => '800px',
                'height' => '450px',
                'footer' => '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' . Text::_('COM_JEM_CLOSE') . '</button>'
            )
        );

        // Hidden input that stores the IDs in the database.
        $class = $this->required ? ' class="required modal-value"' : '';
        $html[] = '<input type="hidden" id="' . $this->id . '_id"' . $class . ' name="' . $this->name . '" value="' . htmlspecialchars($currentValues, ENT_QUOTES, 'UTF-8') . '" />';

        return implode("\n", $html);
    }
}
?>
