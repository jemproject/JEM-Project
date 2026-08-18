<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ModalSelectField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

/**
 * Venue selector using Joomla's native modal content-select field.
 */
class JFormFieldVenue extends ModalSelectField
{
    protected $type = 'Venue';

    protected function getInput()
    {
        $function = 'jSelectVenue_' . preg_replace('/[^A-Za-z0-9_]/', '_', $this->id);

        $this->select      = true;
        $this->clear       = false;
        $this->urlSelect   = rtrim(Uri::root(true), '/') . '/administrator/index.php?option=com_jem&view=venueelement&tmpl=component&function=' . $function;
        $this->titleSelect = 'COM_JEM_SELECT_VENUE';
        $this->iconSelect  = 'icon-location';

        Factory::getApplication()->getDocument()->getWebAssetManager()->addInlineScript(
            'window.' . $function . ' = function (id, venue) {' . "\n"
            . '    var value = document.getElementById(' . json_encode($this->id . '_id') . ');' . "\n"
            . '    var title = document.getElementById(' . json_encode($this->id) . ') || document.getElementById(' . json_encode($this->id . '_name') . ');' . "\n"
            . '    if (value) { value.value = id; value.dispatchEvent(new CustomEvent("change", {bubbles: true})); }' . "\n"
            . '    if (title) { title.value = venue; }' . "\n"
            . '    var dialog = document.querySelector("joomla-dialog.joomla-dialog-content-select-field");' . "\n"
            . '    if (dialog && typeof dialog.close === "function") { dialog.close(); }' . "\n"
            . '};'
        );

        return parent::getInput();
    }

    protected function getValueTitle()
    {
        if (!$this->value) {
            return Text::_('COM_JEM_SELECT_VENUE');
        }

        try {
            $db    = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true)
                ->select($db->quoteName('venue'))
                ->from($db->quoteName('#__jem_venues'))
                ->where($db->quoteName('id') . ' = ' . (int) $this->value);
            $db->setQuery($query);

            return $db->loadResult() ?: Text::_('COM_JEM_SELECT_VENUE');
        } catch (\Throwable $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'warning');

            return Text::_('COM_JEM_SELECT_VENUE');
        }
    }
}
