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

/**
 * Event selector using Joomla's native modal content-select field.
 */
class JFormFieldEvent extends ModalSelectField
{
    protected $type = 'Event';

    protected function getInput()
    {
        $function = 'jSelectEvent_' . preg_replace('/[^A-Za-z0-9_]/', '_', $this->id);

        $this->select      = true;
        $this->clear       = false;
        $this->urlSelect   = 'index.php?option=com_jem&view=eventelement&tmpl=component&function=' . $function;
        $this->titleSelect = 'COM_JEM_SELECT_EVENT';
        $this->iconSelect  = 'icon-calendar';

        $this->addLegacyCallback($function);

        return parent::getInput();
    }

    protected function getValueTitle()
    {
        return $this->loadTitle('#__jem_events', 'title', 'COM_JEM_SELECT_EVENT');
    }

    private function addLegacyCallback(string $function): void
    {
        Factory::getApplication()->getDocument()->getWebAssetManager()->addInlineScript(
            'window.' . $function . ' = function (id, title) {' . "\n"
            . '    var value = document.getElementById(' . json_encode($this->id . '_id') . ');' . "\n"
            . '    var label = document.getElementById(' . json_encode($this->id) . ') || document.getElementById(' . json_encode($this->id . '_name') . ');' . "\n"
            . '    if (value) { value.value = id; value.dispatchEvent(new CustomEvent("change", {bubbles: true})); }' . "\n"
            . '    if (label) { label.value = title; }' . "\n"
            . '    var dialog = document.querySelector("joomla-dialog.joomla-dialog-content-select-field");' . "\n"
            . '    if (dialog && typeof dialog.close === "function") { dialog.close(); }' . "\n"
            . '};'
        );
    }

    private function loadTitle(string $table, string $column, string $fallback): string
    {
        if (!$this->value) {
            return Text::_($fallback);
        }

        try {
            $db    = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true)
                ->select($db->quoteName($column))
                ->from($db->quoteName($table))
                ->where($db->quoteName('id') . ' = ' . (int) $this->value);
            $db->setQuery($query);

            return $db->loadResult() ?: Text::_($fallback);
        } catch (\Throwable $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'warning');

            return Text::_($fallback);
        }
    }
}
