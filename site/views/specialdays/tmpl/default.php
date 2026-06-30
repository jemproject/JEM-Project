<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn = $this->escape($this->state->get('list.direction'));
$limitBox = str_replace('class="', 'class="form-select form-select-sm ', $this->pagination->getLimitBox());

$weekdayLabels = array(
    0 => Text::_('SUN'),
    1 => Text::_('MON'),
    2 => Text::_('TUE'),
    3 => Text::_('WED'),
    4 => Text::_('THU'),
    5 => Text::_('FRI'),
    6 => Text::_('SAT'),
);

$renderRule = static function ($item) use ($weekdayLabels) {
    $parts = array();
    $weekdays = array_filter(array_map('trim', explode(',', (string) ($item->weekdays ?? ''))), 'strlen');

    if ($weekdays) {
        $labels = array();

        foreach ($weekdays as $weekday) {
            $weekday = (int) $weekday;

            if (isset($weekdayLabels[$weekday])) {
                $labels[] = $weekdayLabels[$weekday];
            }
        }

        $parts[] = Text::_('COM_JEM_SPECIAL_DAY_WEEKDAYS') . ': ' . implode(', ', $labels);
    }

    if (!empty($item->start_date) && $item->start_date !== '0000-00-00') {
        $range = HTMLHelper::_('date', $item->start_date, Text::_('DATE_FORMAT_LC4'));

        if (!empty($item->end_date) && $item->end_date !== '0000-00-00') {
            $range .= ' - ' . HTMLHelper::_('date', $item->end_date, Text::_('DATE_FORMAT_LC4'));
        }

        $parts[] = $range;
    }

    return $parts ? implode('<br>', $parts) : Text::_('COM_JEM_SPECIAL_DAY_RULE_NOT_SET');
};

$renderLocation = function ($item) {
    $country = trim((string) ($item->country ?? ''));
    $region = trim((string) ($item->region ?? ''));
    $city = trim((string) ($item->city ?? ''));
    $parts = array_filter(array($country, $region, $city), 'strlen');

    if (!$parts) {
        return '<span class="text-muted">-</span>';
    }

    if (preg_match('/^[A-Za-z]{2}$/', $country)) {
        $countryCode = strtoupper($country);
        $flag = '&#' . (127397 + ord($countryCode[0])) . ';&#' . (127397 + ord($countryCode[1])) . ';';
        $parts[0] = '<span class="jem-specialdays-location-country">'
            . '<span class="jem-specialdays-location-flag" aria-hidden="true">' . $flag . '</span>'
            . '<span>' . $this->escape($countryCode) . '</span>'
            . '</span>';
    } else {
        $parts[0] = $this->escape($country);
    }

    foreach ($parts as $key => $part) {
        if ($key === 0) {
            continue;
        }

        $parts[$key] = $this->escape($part);
    }

    return implode(', ', $parts);
};
?>

<div id="jem" class="jem_specialdays">
    <div class="buttons jem-buttons jem-specialdays-buttons">
        <?php
        $btn_params = array('print_link' => $this->printLink);
        echo JemOutput::createButtonBar($this->getName(), (object) array(), $btn_params);
        ?>
        <a class="jem-specialdays-action-button" href="<?php echo $this->newLink; ?>" title="<?php echo Text::_('COM_JEM_SPECIAL_DAY_NEW'); ?>" aria-label="<?php echo Text::_('COM_JEM_SPECIAL_DAY_NEW'); ?>">
            <?php echo jemhtml::icon('com_jem/icon-16-new.webp', 'fa fa-fw fa-lg fa-plus-square jem-specialdays-newbutton', Text::_('COM_JEM_SPECIAL_DAY_NEW')); ?>
        </a>
    </div>

    <?php if ($this->params->get('show_page_heading', 1)) : ?>
        <h1 class="componentheading"><?php echo $this->escape($this->params->get('page_heading')); ?></h1>
    <?php endif; ?>

    <form action="<?php echo $this->action; ?>" method="post" name="adminForm" id="adminForm" enctype="multipart/form-data">
        <fieldset class="adminform mb-3 jem-specialdays-import">
            <legend><?php echo Text::_('COM_JEM_SPECIAL_DAYS_IMPORT_CSV'); ?></legend>
            <div class="row g-2 align-items-end jem-specialdays-import-row">
                <div class="col-md-5 jem-specialdays-import-file">
                    <label for="specialdays-file-upload" class="form-label"><?php echo Text::_('COM_JEM_IMPORT_SELECTCSV'); ?></label>
                    <input type="file" id="specialdays-file-upload" accept=".csv,text/csv,text/plain" name="FileSpecialDays" class="form-control" />
                </div>
                <div class="col-md-3 jem-specialdays-import-replace">
                    <label for="replace_specialdays" class="form-label"><?php echo Text::_('COM_JEM_IMPORT_REPLACEIFEXISTS'); ?></label>
                    <select name="replace_specialdays" id="replace_specialdays" class="form-select">
                        <option value="0"><?php echo Text::_('JNO'); ?></option>
                        <option value="1"><?php echo Text::_('JYES'); ?></option>
                    </select>
                </div>
                <div class="col-md-4 jem-specialdays-import-action">
                    <button type="submit" class="btn btn-primary"
                            onclick="document.adminForm.task.value='specialdays.importCsv';">
                        <span class="icon-upload" aria-hidden="true"></span>
                        <?php echo Text::_('COM_JEM_IMPORT_START'); ?>
                    </button>
                </div>
            </div>
            <div class="form-text mt-2">
                <?php echo Text::_('COM_JEM_SPECIAL_DAYS_IMPORT_CSV_DESC'); ?>
            </div>
            <details class="mt-2">
                <summary><?php echo Text::_('COM_JEM_SPECIAL_DAYS_IMPORT_CSV_HELP_TITLE'); ?></summary>
                <div class="mt-2">
                    <p class="mb-2"><?php echo Text::_('COM_JEM_SPECIAL_DAYS_IMPORT_CSV_FIELDS'); ?></p>
                    <pre class="p-2 bg-light border rounded mb-0"><code><?php echo $this->escape(str_replace('\n', "\n", Text::_('COM_JEM_SPECIAL_DAYS_IMPORT_CSV_EXAMPLE'))); ?></code></pre>
                </div>
            </details>
        </fieldset>

        <div id="jem_filter" class="jem-specialdays-filter jem-row mb-3">
            <div class="jem-specialdays-filter-search">
                <div class="input-group">
                    <label for="filter_search" class="visually-hidden"><?php echo Text::_('COM_JEM_SEARCH'); ?></label>
                    <input type="text" name="filter_search" id="filter_search" class="form-control"
                           placeholder="<?php echo Text::_('COM_JEM_SEARCH'); ?>"
                           value="<?php echo $this->escape($this->state->get('filter.search')); ?>"
                           onchange="document.adminForm.submit();" />
                    <button type="submit" class="btn btn-primary">
                        <span class="icon-search" aria-hidden="true"></span>
                    </button>
                    <button type="button" class="btn btn-primary"
                            onclick="document.getElementById('filter_search').value='';this.form.filter_state.value='';this.form.filter_day_type.value='';this.form.filter_year.value='<?php echo (int) date('Y'); ?>';this.form.submit();">
                        <?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?>
                    </button>
                </div>
            </div>
            <div class="jem-specialdays-filter-select">
                <label for="filter_year" class="visually-hidden"><?php echo Text::_('COM_JEM_SPECIAL_DAY_FILTER_YEAR'); ?></label>
                <select name="filter_year" id="filter_year" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ((array) $this->years as $year) : ?>
                        <option value="<?php echo (int) $year; ?>"<?php echo (int) $this->state->get('filter.year') === (int) $year ? ' selected' : ''; ?>>
                            <?php echo (int) $year; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="jem-specialdays-filter-select">
                <select name="filter_state" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value=""><?php echo Text::_('JOPTION_SELECT_PUBLISHED'); ?></option>
                    <?php echo HTMLHelper::_('select.options', HTMLHelper::_('jgrid.publishedOptions', array('all' => true)), 'value', 'text', $this->state->get('filter.state'), true); ?>
                </select>
            </div>
            <div class="jem-specialdays-filter-select">
                <select name="filter_day_type" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value=""><?php echo Text::_('COM_JEM_SPECIAL_DAY_FILTER_TYPE'); ?></option>
                    <?php foreach ($this->dayTypes as $type) : ?>
                        <?php $typeValue = !empty($type['id']) ? (string) (int) $type['id'] : (string) $type['name']; ?>
                        <option value="<?php echo $this->escape($typeValue); ?>"<?php echo (string) $this->state->get('filter.day_type') === $typeValue ? ' selected="selected"' : ''; ?>>
                            <?php echo $this->escape($type['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="jem-specialdays-filter-limit">
                <?php echo $limitBox; ?>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-striped align-middle" id="specialDayList">
                <thead>
                    <tr>
                        <th>
                            <?php echo HTMLHelper::_('grid.sort', 'COM_JEM_SPECIAL_DAY_FIELD_TITLE', 'a.title', $listDirn, $listOrder); ?>
                        </th>
                        <th style="width:12%">
                            <?php echo HTMLHelper::_('grid.sort', 'COM_JEM_SPECIAL_DAY_FIELD_TYPE', 'a.day_type_id', $listDirn, $listOrder); ?>
                        </th>
                        <th style="width:22%">
                            <?php echo Text::_('COM_JEM_SPECIAL_DAY_RULE'); ?>
                        </th>
                        <th style="width:16%" class="center jem-specialdays-location-heading">
                            <?php echo Text::_('COM_JEM_SPECIAL_DAY_LOCATION'); ?>
                        </th>
                        <th style="width:8%" class="center">
                            <?php echo HTMLHelper::_('grid.sort', 'JSTATUS', 'a.published', $listDirn, $listOrder); ?>
                        </th>
                        <th style="width:5%" class="center">
                            <?php echo HTMLHelper::_('grid.sort', 'JGRID_HEADING_ID', 'a.id', $listDirn, $listOrder); ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($this->items as $item) : ?>
                    <?php
                    $editUrl = Route::_('index.php?option=com_jem&view=specialday&layout=edit&id=' . (int) $item->id . '&return=' . base64_encode($this->action));
                    $dayType = $item->day_type ?? '';
                    $type = !empty($item->day_type_id) && isset($this->dayTypesById[(int) $item->day_type_id])
                        ? $this->dayTypesById[(int) $item->day_type_id]
                        : ($this->dayTypes[$dayType] ?? array('name' => $dayType, 'color' => '#d1d5db'));
                    ?>
                    <tr>
                        <td>
                            <a href="<?php echo $editUrl; ?>"><?php echo $this->escape($item->title ?? ''); ?></a>
                            <?php if (!empty($item->description)) : ?>
                                <br><small class="text-muted"><?php echo $this->escape($item->description); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span style="display:inline-block;width:1rem;height:1rem;border:1px solid #adb5bd;border-radius:3px;background:<?php echo $this->escape($type['color']); ?>;" aria-hidden="true"></span>
                            <?php echo $dayType !== '' ? $this->escape($dayType) : '<span class="text-muted">-</span>'; ?>
                        </td>
                        <td>
                            <?php echo $renderRule($item); ?>
                        </td>
                        <td class="center jem-specialdays-location-cell">
                            <?php echo $renderLocation($item); ?>
                        </td>
                        <td class="center">
                            <?php echo ((int) $item->published === 1) ? Text::_('JPUBLISHED') : Text::_('JUNPUBLISHED'); ?>
                        </td>
                        <td class="center">
                            <?php echo (int) $item->id; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($this->items)) : ?>
                    <tr><td colspan="6" class="center"><?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php echo $this->pagination->getListFooter(); ?>

        <input type="hidden" name="filter_order" value="<?php echo $this->escape($this->state->get('list.ordering')); ?>">
        <input type="hidden" name="filter_order_Dir" value="<?php echo $this->escape($this->state->get('list.direction')); ?>">
        <input type="hidden" name="task" value="" />
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>
</div>
