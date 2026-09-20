<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$form = $displayData['form'];
$configuration = $displayData['configuration'];
$legacyFields = $displayData['legacyFields'] ?? array();
$joomlaFields = $displayData['joomlaFields'] ?? array();
$joomlaGroups = $displayData['joomlaGroups'] ?? array();
$selectedJemIds = $configuration['mode'] === JemCategoryCustomFields::MODE_GLOBAL
    ? array_map(static function ($field) {
        return (int) $field->id;
    }, $legacyFields)
    : $configuration['jem_field_ids'];
$selectedJemIds = array_flip(array_map('intval', $selectedJemIds));
$selectedJoomlaIds = array_flip(array_map('intval', $configuration['joomla_field_ids']));
$selectedGroupIds = array_flip(array_map('intval', $configuration['joomla_group_ids']));
$ungroupedFields = array();
$fieldsByGroup = array();

foreach ($joomlaFields as $field) {
    $groupId = max(0, (int) ($field->group_id ?? 0));

    if ($groupId === 0) {
        $ungroupedFields[] = $field;
        continue;
    }

    if (!isset($fieldsByGroup[$groupId])) {
        $fieldsByGroup[$groupId] = array();
    }

    $fieldsByGroup[$groupId][] = $field;
}

foreach ($fieldsByGroup as $groupId => $groupFields) {
    if (isset($joomlaGroups[$groupId])) {
        continue;
    }

    $fallbackTitle = (string) ($groupFields[0]->group_title ?? Text::_('COM_JEM_CUSTOMFIELDS'));
    $joomlaGroups[$groupId] = (object) array(
        'id'          => $groupId,
        'title'       => $fallbackTitle,
        'description' => '',
    );
}
?>

<div class="jem-category-custom-fields" data-jem-category-custom-fields-editor>
    <?php echo $form->getInput('custom_fields'); ?>

    <p class="text-muted">
        <?php echo Text::_('COM_JEM_CATEGORY_CUSTOM_FIELDS_DESC'); ?>
    </p>

    <section class="card border-secondary shadow-sm mb-3">
        <div class="card-header bg-light">
            <strong><?php echo Text::_('COM_JEM_CATEGORY_LEGACY_CUSTOM_FIELDS'); ?></strong>
        </div>
        <div class="card-body">
            <p class="text-muted"><?php echo Text::_('COM_JEM_CATEGORY_LEGACY_CUSTOM_FIELDS_SELECT_DESC'); ?></p>
            <?php if ($legacyFields) : ?>
                <div class="row g-3">
                    <?php foreach ($legacyFields as $field) : ?>
                        <div class="col-12 col-lg-6">
                            <label class="form-check">
                                <input
                                    type="checkbox"
                                    class="form-check-input"
                                    value="<?php echo (int) $field->id; ?>"
                                    data-jem-legacy-field-id
                                    <?php echo isset($selectedJemIds[(int) $field->id]) ? 'checked' : ''; ?>
                                >
                                <span class="form-check-label">
                                    <strong><?php echo htmlspecialchars((string) $field->label, ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <?php if ($field->description !== '') : ?>
                                        <span class="d-block text-muted"><?php echo htmlspecialchars((string) $field->description, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endif; ?>
                                </span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <div class="alert alert-info mb-0"><?php echo Text::_('COM_JEM_CATEGORY_LEGACY_CUSTOM_FIELDS_EMPTY'); ?></div>
            <?php endif; ?>
        </div>
    </section>

    <section class="card border-secondary shadow-sm mb-3">
        <div class="card-header bg-light">
            <strong><?php echo Text::_('COM_JEM_CATEGORY_JOOMLA_UNGROUPED_FIELDS'); ?></strong>
        </div>
        <div class="card-body">
            <p class="text-muted"><?php echo Text::_('COM_JEM_CATEGORY_JOOMLA_UNGROUPED_FIELDS_DESC'); ?></p>
            <?php if ($ungroupedFields) : ?>
                <div class="row g-3">
                    <?php foreach ($ungroupedFields as $field) : ?>
                        <div class="col-12 col-lg-6">
                            <label class="form-check">
                                <input
                                    type="checkbox"
                                    class="form-check-input"
                                    value="<?php echo (int) $field->id; ?>"
                                    data-jem-joomla-field-id
                                    <?php echo isset($selectedJoomlaIds[(int) $field->id]) ? 'checked' : ''; ?>
                                >
                                <span class="form-check-label">
                                    <strong><?php echo htmlspecialchars(Text::_($field->label), ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <span class="d-block text-muted">
                                        <?php echo Text::sprintf(
                                            'COM_JEM_CATEGORY_JOOMLA_CUSTOM_FIELD_META',
                                            (int) $field->id,
                                            htmlspecialchars((string) $field->type, ENT_QUOTES, 'UTF-8')
                                        ); ?>
                                    </span>
                                </span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <div class="alert alert-info mb-0"><?php echo Text::_('COM_JEM_CATEGORY_JOOMLA_UNGROUPED_FIELDS_EMPTY'); ?></div>
            <?php endif; ?>
        </div>
    </section>

    <div class="jem-category-field-groups">
        <?php foreach ($joomlaGroups as $groupId => $group) : ?>
            <?php if (empty($fieldsByGroup[(int) $groupId])) : ?>
                <?php continue; ?>
            <?php endif; ?>
            <section class="card border-primary shadow-sm mb-3" data-jem-category-field-group="<?php echo (int) $groupId; ?>">
                <div class="card-header bg-light">
                    <label class="form-check mb-0">
                        <input
                            type="checkbox"
                            class="form-check-input"
                            value="<?php echo (int) $groupId; ?>"
                            data-jem-joomla-group-id
                            <?php echo isset($selectedGroupIds[(int) $groupId]) ? 'checked' : ''; ?>
                        >
                        <span class="form-check-label">
                            <strong><?php echo htmlspecialchars(Text::_($group->title), ENT_QUOTES, 'UTF-8'); ?></strong>
                            <span class="d-block text-muted"><?php echo Text::_('COM_JEM_CATEGORY_JOOMLA_GROUP_INCLUDE_DESC'); ?></span>
                        </span>
                    </label>
                </div>
                <div class="card-body">
                    <?php if (!empty($group->description)) : ?>
                        <p class="text-muted"><?php echo htmlspecialchars(strip_tags((string) $group->description), ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endif; ?>
                    <div class="row g-3">
                        <?php foreach ($fieldsByGroup[(int) $groupId] as $field) : ?>
                            <div class="col-12 col-lg-6">
                                <label class="form-check">
                                    <input
                                        type="checkbox"
                                        class="form-check-input"
                                        value="<?php echo (int) $field->id; ?>"
                                        data-jem-joomla-field-id
                                        data-jem-joomla-field-group="<?php echo (int) $groupId; ?>"
                                        <?php echo isset($selectedJoomlaIds[(int) $field->id]) ? 'checked' : ''; ?>
                                    >
                                    <span class="form-check-label">
                                        <strong><?php echo htmlspecialchars(Text::_($field->label), ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <span class="d-block text-muted">
                                            <?php echo Text::sprintf(
                                                'COM_JEM_CATEGORY_JOOMLA_CUSTOM_FIELD_META',
                                                (int) $field->id,
                                                htmlspecialchars((string) $field->type, ENT_QUOTES, 'UTF-8')
                                            ); ?>
                                        </span>
                                    </span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        <?php endforeach; ?>
    </div>

    <?php if (!$joomlaFields) : ?>
        <div class="alert alert-info mb-0"><?php echo Text::_('COM_JEM_CATEGORY_JOOMLA_CUSTOM_FIELDS_EMPTY'); ?></div>
    <?php endif; ?>
</div>
