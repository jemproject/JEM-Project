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
 * Venue selector using Joomla's native modal content-select field.
 */
class JFormFieldModal_Venue extends ModalSelectField
{
    protected $type = 'Modal_Venue';

    protected function getInput()
    {
        $function = 'jSelectVenue_' . preg_replace('/[^A-Za-z0-9_]/', '_', $this->id);

        $this->select      = true;
        $this->clear       = false;
        $parentVenueId = $this->getSelectedParentVenueId();
        $this->urlSelect   = 'index.php?option=com_jem&view=venueelement&tmpl=component&function=' . $function
            . ($parentVenueId > 0 ? '&parent_venue_id=' . $parentVenueId : '');
        $this->titleSelect = 'COM_JEM_SELECTVENUE';
        $this->iconSelect  = 'icon-location';

        // Compatibility for third-party chooser overrides that still call the legacy callback.
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

        $eventVenueMap = $this->getRootEventVenueMap();
        $baseSelectUrl = 'index.php?option=com_jem&view=venueelement&tmpl=component&function=' . $function;
        Factory::getApplication()->getDocument()->getWebAssetManager()->addInlineScript(
            'document.addEventListener("DOMContentLoaded", function () {' . "\n"
            . '    var parent = document.getElementById("jform_parent_event_id");' . "\n"
            . '    var venueValue = document.getElementById(' . json_encode($this->id . '_id') . ');' . "\n"
            . '    var venueTitle = document.getElementById(' . json_encode($this->id) . ');' . "\n"
            . '    var wrapper = venueValue ? venueValue.closest(".js-modal-content-select-field") : null;' . "\n"
            . '    var selectButton = wrapper ? wrapper.querySelector("[data-button-action=select]") : null;' . "\n"
            . '    var map = ' . json_encode($eventVenueMap) . ';' . "\n"
            . '    var baseUrl = ' . json_encode($baseSelectUrl) . ';' . "\n"
            . '    if (!parent || !selectButton) { return; }' . "\n"
            . '    parent.addEventListener("change", function () {' . "\n"
            . '        var parentVenue = Number(map[parent.value] || 0);' . "\n"
            . '        var config = JSON.parse(selectButton.dataset.modalConfig || "{}");' . "\n"
            . '        config.src = baseUrl + (parentVenue > 0 ? "&parent_venue_id=" + parentVenue : "");' . "\n"
            . '        selectButton.dataset.modalConfig = JSON.stringify(config);' . "\n"
            . '        if (venueValue) { venueValue.value = ""; venueValue.dispatchEvent(new CustomEvent("change", {bubbles: true})); }' . "\n"
            . '        if (venueTitle) { venueTitle.value = ' . json_encode(Text::_('COM_JEM_SELECTVENUE')) . '; }' . "\n"
            . '    });' . "\n"
            . '});'
        );

        return parent::getInput();
    }

    private function getSelectedParentVenueId(): int
    {
        $parentEventId = $this->form ? (int) $this->form->getValue('parent_event_id') : 0;
        if ($parentEventId < 1) {
            return 0;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName('locid'))
                ->from($db->quoteName('#__jem_events'))
                ->where($db->quoteName('id') . ' = ' . $parentEventId)
        );

        return (int) $db->loadResult();
    }

    private function getRootEventVenueMap(): array
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select(array($db->quoteName('id'), $db->quoteName('locid')))
            ->from($db->quoteName('#__jem_events'))
            ->where('(' . $db->quoteName('parent_event_id') . ' IS NULL OR ' . $db->quoteName('parent_event_id') . ' = 0)');
        $db->setQuery($query);

        return array_map('intval', (array) $db->loadAssocList('id', 'locid'));
    }

    protected function getValueTitle()
    {
        if (!$this->value) {
            return Text::_('COM_JEM_SELECTVENUE');
        }

        try {
            $db    = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true)
                ->select($db->quoteName('venue'))
                ->from($db->quoteName('#__jem_venues'))
                ->where($db->quoteName('id') . ' = ' . (int) $this->value);
            $db->setQuery($query);

            return $db->loadResult() ?: Text::_('COM_JEM_SELECTVENUE');
        } catch (\Throwable $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'warning');

            return Text::_('COM_JEM_SELECTVENUE');
        }
    }
}
