<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Form\Field\FileField;

require_once JPATH_SITE . '/components/com_jem/classes/imagecamera.class.php';

/**
 * File upload field enhanced with the shared JEM camera popup.
 */
class JFormFieldJemimagefile extends FileField
{
    protected $type = 'Jemimagefile';

    protected function getInput()
    {
        $profile = (string) ($this->element['imageprofile'] ?? 'event_intro');
        $clearSelectId = (string) ($this->element['clearselect'] ?? '');
        $removeFieldId = (string) ($this->element['removefield'] ?? '');
        $resolutionFieldId = (string) ($this->element['resolutionfield'] ?? '');
        $settings = JemHelper::config();
        $input = preg_replace('/<br\s*\/?>.*$/is', '', parent::getInput());

        return '<div class="jem-image-source-controls">'
            . '<div class="jem-image-file-input">' . $input . '</div>'
            . JemImageCamera::button(
                (string) $this->id,
                $profile,
                $settings,
                $clearSelectId,
                $removeFieldId,
                '',
                $resolutionFieldId
            )
            . '</div>';
    }
}
