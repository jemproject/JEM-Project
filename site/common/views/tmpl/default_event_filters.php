<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$filterData = $this->eventFilters ?? array();
$rows = $filterData['rows'] ?? array();
$selectedContacts = array_map('intval', $filterData['contact_ids'] ?? array());
?>

<?php if (!empty($filterData['has_visible'])) : ?>
    <div class="jem-event-filters" data-jem-event-filters role="group" aria-label="<?php echo $this->escape(Text::_('COM_JEM_EVENT_FILTERS_FRONTEND')); ?>">
        <div class="jem-event-filter-fields">
            <?php foreach ($rows as $row) : ?>
                <?php
                $isCategory = $row['key'] === JemEventFilterConfig::CONTACT_CATEGORY;
                $isContact = $row['key'] === JemEventFilterConfig::CONTACT;
                $isCustom = JemEventFilterConfig::isCustomKey($row['key']);
                $label = $row['label'];
                $inputId = $isCustom ? 'filter_' . $row['key'] : '';
                $readonlyInputId = 'filter_' . $row['key'] . '_readonly';
                ?>
                <?php if ($row['editable']) : ?>
                    <div class="jem-event-filter-field jem-event-filter-field-<?php echo $this->escape($row['key']); ?>">
                        <?php if ($isCategory) : ?>
                            <label for="filter_contact_category"><?php echo $this->escape($label); ?></label>
                            <select name="filter_contact_category" id="filter_contact_category" class="form-select" data-jem-contact-category-filter>
                                <option value="0"><?php echo $this->escape(Text::_('COM_JEM_EVENT_FILTER_ALL_CONTACT_CATEGORIES')); ?></option>
                                <?php foreach ($filterData['category_options'] as $option) : ?>
                                    <?php $prefix = str_repeat('- ', max(0, (int) $option->level - 1)); ?>
                                    <option value="<?php echo (int) $option->id; ?>"<?php echo (int) $filterData['category_id'] === (int) $option->id ? ' selected' : ''; ?>>
                                        <?php echo $this->escape($prefix . $option->title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ($isContact) : ?>
                            <label for="filter_contacts"><?php echo $this->escape($label); ?></label>
                            <select name="filter_contacts[]" id="filter_contacts" class="form-select"
                                multiple size="5" aria-describedby="filter_contacts_hint">
                                <option value=""<?php echo empty($selectedContacts) ? ' selected' : ''; ?>><?php echo $this->escape(Text::_('COM_JEM_EVENT_FILTER_ALL_CONTACTS')); ?></option>
                                <?php foreach ($filterData['contact_options'] as $option) : ?>
                                    <option value="<?php echo (int) $option->id; ?>"<?php echo in_array((int) $option->id, $selectedContacts, true) ? ' selected' : ''; ?>>
                                        <?php echo $this->escape($option->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div id="filter_contacts_hint" class="form-text"><?php echo $this->escape(Text::_('COM_JEM_EVENT_FILTER_CONTACT_MULTIPLE_HINT')); ?></div>
                        <?php elseif ($isCustom) : ?>
                            <label for="<?php echo $this->escape($inputId); ?>"><?php echo $this->escape($label); ?></label>
                            <?php if ($row['custom_type'] === JemCustomFields::TYPE_LIST) : ?>
                                <select name="filter_custom[<?php echo $this->escape($row['key']); ?>]" id="<?php echo $this->escape($inputId); ?>" class="form-select" data-jem-custom-filter>
                                    <option value=""><?php echo $this->escape(Text::_('JOPTION_SELECT')); ?></option>
                                    <?php foreach ($row['custom_options'] as $optionValue => $optionLabel) : ?>
                                        <option value="<?php echo $this->escape((string) $optionValue); ?>"<?php echo (string) $row['effective_value'] === (string) $optionValue ? ' selected' : ''; ?>>
                                            <?php echo $this->escape((string) $optionLabel); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else : ?>
                                <input type="text" name="filter_custom[<?php echo $this->escape($row['key']); ?>]" id="<?php echo $this->escape($inputId); ?>" class="form-control" maxlength="200" value="<?php echo $this->escape((string) $row['effective_value']); ?>" data-jem-custom-filter>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php elseif ($row['active'] && $row['display_value'] !== '') : ?>
                    <div class="jem-event-filter-field jem-event-filter-field-<?php echo $this->escape($row['key']); ?> jem-event-filter-field--readonly" role="note">
                        <label for="<?php echo $this->escape($readonlyInputId); ?>"><?php echo $this->escape($label); ?></label>
                        <input type="text" id="<?php echo $this->escape($readonlyInputId); ?>" class="form-control jem-event-filter-readonly" value="<?php echo $this->escape($row['display_value']); ?>" readonly aria-readonly="true">
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

    </div>
<?php endif; ?>
