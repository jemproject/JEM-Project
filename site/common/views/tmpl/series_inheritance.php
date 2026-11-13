<?php
/**
 * @package JEM
 * @copyright (C) 2013-2026 joomlaeventmanager.net
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

require_once JPATH_SITE . '/components/com_jem/classes/seriesinheritance.class.php';
$db = Factory::getContainer()->get('DatabaseDriver');
$childId = (int) ($this->item->id ?? 0);
$stored = $childId ? $db->setQuery('SELECT * FROM #__jem_events WHERE id = ' . $childId)->loadAssoc() : null;
$parentId = (int) ($stored['recurrence_first_id'] ?? 0);
if (!$parentId && !empty($stored['series_id'])) {
    $parentId = (int) $db->setQuery('SELECT root_event_id FROM #__jem_event_series WHERE id = ' . (int) $stored['series_id'])->loadResult();
}
if (!$parentId || $parentId === $childId) {
    return;
}
$parent = $db->setQuery('SELECT * FROM #__jem_events WHERE id = ' . $parentId)->loadAssoc();
if (!$parent) {
    return;
}
$stored['cats'] = $db->setQuery('SELECT catid FROM #__jem_cats_event_relations WHERE itemid = ' . $childId)->loadColumn();
$parent['cats'] = $db->setQuery('SELECT catid FROM #__jem_cats_event_relations WHERE itemid = ' . $parentId)->loadColumn();
$policy = JemSeriesInheritance::policy($stored, $parent);
Factory::getApplication()->getDocument()->getWebAssetManager()->registerAndUseScript(
    'com_jem.series-inheritance', 'media/com_jem/js/series-inheritance.js', ['version' => 'auto'], ['defer' => true]
);
?>
<fieldset class="mb-3 jem-series-inheritance">
    <legend><?php echo Text::_('COM_JEM_SERIES_INHERITANCE'); ?></legend>
    <input type="hidden" name="jform[series_inheritance_present]" value="1">
    <input type="hidden" name="jform[series_inherit]" value="0">
    <label><input type="checkbox" id="jem-series-inherit" name="jform[series_inherit]" value="1" <?php echo $policy['inherit'] ? 'checked' : ''; ?>>
        <?php echo Text::_('COM_JEM_SERIES_INHERIT'); ?></label>
    <p class="form-text"><?php echo Text::_('COM_JEM_SERIES_INHERIT_DESC'); ?></p>
    <p><?php echo Text::_('COM_JEM_SERIES_OVERRIDE_DESC'); ?></p>
    <?php foreach (array_keys(JemSeriesInheritance::GROUPS) as $group) : ?>
        <label class="d-block"><input type="checkbox" name="jform[series_inherit_fields][]" value="<?php echo $group; ?>"
            <?php echo !in_array($group, $policy['overrides'], true) ? 'checked' : ''; ?>>
            <?php echo Text::sprintf('COM_JEM_SERIES_INHERIT_FIELD', Text::_('COM_JEM_SERIES_OVERRIDE_' . strtoupper($group))); ?></label>
    <?php endforeach; ?>
</fieldset>
