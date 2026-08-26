<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;

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
        $app      = Factory::getApplication();
        $document = $app->getDocument();
        $wa       = $document->getWebAssetManager();
        $modalId  = preg_replace('/[^A-Za-z0-9_-]/', '_', $this->id) . '_users_modal';

        // Build the script
        $script = array();
        $script[] = '    function jSelectUsers_'.$this->id.'(ids, count, object) {';
        $script[] = '        document.getElementById("'.$this->id.'_ids").value = ids;';
        $script[] = '        document.getElementById("'.$this->id.'_count").value = count;';
        $script[] = '        var modal = document.getElementById(' . json_encode($modalId) . ');';
        $script[] = '        if (modal && window.bootstrap && bootstrap.Modal) {';
        $script[] = '            var instance = bootstrap.Modal.getInstance(modal);';
        $script[] = '            if (instance) { instance.hide(); }';
        $script[] = '        }';
        $script[] = '    }';

        // Add to document head
        $wa->addInlineScript(implode("\n", $script));

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
            //     Factory::getApplication()->enqueueMessage($error, 'warning');
            // }
            try
            {
                $count = (int)$db->loadResult();
            }
            catch (RuntimeException $e)
            {
                Factory::getApplication()->enqueueMessage($e->getMessage(), 'warning');
            }
        } else {
            $count = 0;
        }

    //    if (empty($count)) {
    //        $count = Text::_('COM_JEM_SELECT_USERS');
    //    }
    //    $count = htmlspecialchars($count, ENT_QUOTES, 'UTF-8');

        $html[] = HTMLHelper::_(
            'bootstrap.renderModal',
            $modalId,
            array(
                'url'    => $link,
                'title'  => Text::_('COM_JEM_SELECT'),
                'width'  => '800px',
                'height' => '450px',
                'footer' => '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' . Text::_('COM_JEM_CLOSE') . '</button>'
            )
        );
        $html[] = '<div class="input-group">';
        $html[] = '  <input type="text" id="'.$this->id.'_count" value="'.$count.'" disabled="disabled" size="4" class="form-control" />';
        $html[] = '  <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#'.$modalId.'"><span class="icon-users" aria-hidden="true"></span> '.Text::_('COM_JEM_SELECT').'</button>';
        $html[] = '</div>';

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
