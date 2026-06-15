<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$renderInlineHelp = function ($field) {
    if (empty($field->description) || empty($field->id)) {
        return '';
    }

    return '<div id="' . $field->id . '-desc" class="hide-aware-inline-help d-none">'
        . '<small class="form-text">' . Text::_($field->description) . '</small>'
        . '</div>';
};
?>
<div class="width-50 fltlft">
    <div class="width-100" style="padding: 10px 1vw;">
        <fieldset class="options-form">
            <legend><?php echo Text::_('COM_JEM_GENERAL_LAYOUT_SETTINGS'); ?></legend>
            <ul class="adminformlist">
                <li id="loc1" style="display:none"><div class="label-form"><?php echo $this->form->renderfield('tablewidth'); ?></div></li>
            </ul>
        </fieldset>
    </div>
    <div class="width-100" style="padding: 10px 1vw;">
        <fieldset class="options-form">
            <legend><?php echo Text::_('COM_JEM_DATE_COLUMN'); ?></legend>
            <ul class="adminformlist">
                <li id="date1"><div class="label-form"><?php echo $this->form->renderfield('datewidth'); ?></div></li>
                <li id="date2"><div class="label-form"><?php echo $this->form->renderfield('showtime'); ?></div></li>
                <li id="date3"><div class="label-form"><?php echo $this->form->renderfield('datemode'); ?></div></li>

            </ul>
        </fieldset>
    </div>
    <div class="width-100" style="padding: 10px 1vw;">
        <fieldset class="options-form">
            <legend><?php echo Text::_('COM_JEM_TITLE_COLUMN'); ?></legend>
            <ul class="adminformlist">
                <li><div class="label-form"><?php echo $this->form->renderfield('showtitle'); ?></div></li>
                <li id="title1" style="<?php echo ($this->form->getValue('showtitle')? '':'display:none');?>"><div class="label-form"><?php echo $this->form->renderfield('titlewidth'); ?></div>
                </li>
            </ul>
        </fieldset>
    </div>
    <div class="width-100" style="padding: 10px 1vw;">
        <fieldset class="options-form">
            <legend><?php echo Text::_('COM_JEM_VENUE_COLUMN'); ?></legend>
            <ul class="adminformlist">
                <li><div class="label-form"><?php echo $this->form->renderfield('showlocate'); ?></div></li>
                <li id="loc1" style="<?php echo ($this->form->getValue('showlocate')? '':'display:none');?>"><div class="label-form"><?php echo $this->form->renderfield('locationwidth'); ?></div></li>
                <li id="loc2" style="<?php echo ($this->form->getValue('showlocate')? '':'display:none');?>"><div class="label-form"><?php echo $this->form->renderfield('showlinkvenue'); ?></div></li>
            </ul>
        </fieldset>
    </div>
    <div class="width-100" style="padding: 10px 1vw;">
        <fieldset class="options-form">
            <legend><?php echo Text::_('COM_JEM_CITY_COLUMN'); ?></legend>
            <ul class="adminformlist">
                <li><div class="label-form"><?php echo $this->form->renderfield('showcity'); ?></div></li>
                <li id="city1" style="<?php echo ($this->form->getValue('showcity')? '':'display:none');?>"><div class="label-form"><?php echo $this->form->renderfield('citywidth'); ?></div></li>
            </ul>
        </fieldset>
    </div>
    <div class="width-100" style="padding: 10px 1vw;">
        <fieldset class="options-form">
            <legend><?php echo Text::_('COM_JEM_STATE_COLUMN'); ?></legend>
            <ul class="adminformlist">
                <li><div class="label-form"><?php echo $this->form->renderfield('showstate'); ?></div></li>
                <li id="state1" style="<?php echo ($this->form->getValue('showstate')? '':'display:none');?>"><div class="label-form"><?php echo $this->form->renderfield('statewidth'); ?></div></li>
            </ul>
        </fieldset>
    </div>
    <div class="width-100" style="padding: 10px 1vw;">
        <fieldset class="options-form">
            <legend><?php echo Text::_('COM_JEM_CATEGORY_COLUMN'); ?></legend>
            <ul class="adminformlist">
                <li><div class="label-form"><?php echo $this->form->renderfield('showcat'); ?></div></li>
                <li id="cat1" style="<?php echo ($this->form->getValue('showcat')? '':'display:none');?>"><div class="label-form"><?php echo $this->form->renderfield('catfrowidth'); ?></div></li>
                <li id="cat2" style="<?php echo ($this->form->getValue('showcat')? '':'display:none');?>"><div class="label-form"><?php echo $this->form->renderfield('catlinklist'); ?></div></li>
            </ul>
        </fieldset>
    </div>
    <div class="width-100" style="padding: 10px 1vw;">
        <fieldset class="options-form">
            <legend><?php echo Text::_('COM_JEM_ATTENDEE_COLUMN'); ?></legend>
            <ul class="adminformlist">
                <li><div class="label-form"><?php echo $this->form->renderfield('showatte'); ?></div></li>
                <li id="atte1" style="<?php echo ($this->form->getValue('showatte')? '':'display:none');?>"><div class="label-form"><?php echo $this->form->renderfield('attewidth'); ?></div></li>
            </ul>
        </fieldset>
    </div>
    <div class="width-100" style="padding: 10px 1vw;">
        <fieldset class="options-form">
            <legend><?php echo Text::_('COM_JEM_LAYOUT_TABLE_EVENTIMAGE'); ?></legend>
            <ul class="adminformlist">
                <li><div class="label-form"><?php echo $this->form->renderfield('showeventimage'); ?></div></li>
                <li id="evimage1" style="<?php echo ($this->form->getValue('showeventimage')? '':'display:none');?>"><div class="label-form"><?php echo $this->form->renderfield('tableeventimagewidth'); ?></div></li>
            </ul>
        </fieldset>
    </div>
</div>

<div class="width-50 fltrt">
    <div class="width-100" style="padding: 10px 1vw;">
        <fieldset class="options-form">
        <legend><?php echo Text::_('COM_JEM_SETTINGS_LEGEND_CSS'); ?></legend>
        <div class="jem-stylesheet-header">
            <p class="small text-muted"><?php echo Text::_('COM_JEM_SETTINGS_CSS_CUSTOM_WORKFLOW'); ?></p>
            <a class="btn btn-secondary" href="<?php echo Route::_('index.php?option=com_jem&view=cssmanager'); ?>"><?php echo Text::_('COM_JEM_CSSMANAGER_TITLE'); ?></a>
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-sm jem-stylesheet-table">
                <thead>
                    <tr>
                        <th><?php echo Text::_('COM_JEM_SETTINGS_CSS_STYLESHEET'); ?></th>
                        <th><?php echo Text::_('COM_JEM_SETTINGS_CSS_SCOPE'); ?></th>
                        <th><?php echo Text::_('COM_JEM_SETTINGS_CSS_USE_CUSTOM'); ?></th>
                        <th><?php echo Text::_('COM_JEM_SETTINGS_CSS_CUSTOM_FILE'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stylesheetFields = $this->form->getFieldset('stylesheet');
                    $stylesheetFieldsByName = array();
                    $cssBasePath = JPATH_ROOT . '/media/com_jem/css/';
                    $cssCustomPath = $cssBasePath . 'custom/';
                    $customCssFiles = is_dir($cssCustomPath) ? array_values(array_filter(scandir($cssCustomPath), function ($file) use ($cssCustomPath) {
                        return is_file($cssCustomPath . $file) && strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'css';
                    })) : array();

                    $cssFileFromField = function ($fieldname) {
                        $name = preg_replace('/^css_|_usecustom$/', '', $fieldname);
                        return str_replace('_', '-', $name) . '.css';
                    };

                    $customSourceFile = function ($file) use ($cssCustomPath) {
                        $contents = is_file($cssCustomPath . $file) ? file_get_contents($cssCustomPath . $file, false, null, 0, 512) : false;

                        if ($contents !== false && preg_match('/JEM custom source:\s*([A-Za-z0-9._-]+\.css)/', $contents, $matches)) {
                            return $matches[1];
                        }

                        return '';
                    };

                    $hasCompatibleCustom = function ($sourceFile) use ($customCssFiles, $customSourceFile) {
                        $sourceStem = preg_replace('/\.css$/', '', $sourceFile);

                        foreach ($customCssFiles as $file) {
                            $declaredSource = $customSourceFile($file);

                            if ($declaredSource !== '') {
                                if ($declaredSource === $sourceFile) {
                                    return true;
                                }

                                continue;
                            }

                            $fileStem = preg_replace('/\.css$/', '', $file);

                            if ($file === $sourceFile || strpos($fileStem, $sourceStem . '-') === 0 || strpos($fileStem, $sourceStem . '_') === 0) {
                                return true;
                            }
                        }

                        return false;
                    };

                    $disableYesOption = function ($input) {
                        return preg_replace_callback('/<input\b[^>]*\bvalue=(["\'])1\1[^>]*>/i', function ($matches) {
                            if (stripos($matches[0], ' disabled') !== false) {
                                return $matches[0];
                            }

                            return preg_replace('/\s*(\/?)>$/', ' disabled="disabled" aria-disabled="true"$1>', $matches[0]);
                        }, $input);
                    };

                    $cssScope = function ($file) {
                        if (strpos($file, '-responsive.css') !== false) {
                            return Text::_('COM_JEM_SETTINGS_CSS_SCOPE_RESPONSIVE');
                        }

                        if ($file === 'backend.css') {
                            return Text::_('COM_JEM_SETTINGS_CSS_SCOPE_BACKEND');
                        }

                        if ($file === 'print.css') {
                            return Text::_('COM_JEM_SETTINGS_CSS_SCOPE_PRINT');
                        }

                        return Text::_('COM_JEM_SETTINGS_CSS_SCOPE_LEGACY');
                    };

                    foreach ($stylesheetFields as $field) {
                        $stylesheetFieldsByName[$field->fieldname] = $field;
                    }

                    foreach ($stylesheetFields as $field):
                        if (substr($field->fieldname, -10) !== '_usecustom') {
                            continue;
                        }

                        $fileFieldName = substr($field->fieldname, 0, -10) . '_customfile';
                        $fileField = $stylesheetFieldsByName[$fileFieldName] ?? null;
                        $cssFile = $cssFileFromField($field->fieldname);
                        $useCustom = (int) $this->form->getValue($field->fieldname, 'css', 0) === 1;
                        $customFile = $fileField ? trim((string) $this->form->getValue($fileField->fieldname, 'css', '')) : '';
                        $customCandidate = $customFile ?: $cssFile;
                        $customExists = $customCandidate && is_file($cssCustomPath . $customCandidate);
                        $defaultExists = is_file($cssBasePath . $cssFile);
                        $hasCustomFile = $hasCompatibleCustom($cssFile);
                        $useCustomInput = $hasCustomFile ? $field->input : $disableYesOption($field->input);

                    ?>
                    <tr>
                        <th scope="row"><code><?php echo htmlspecialchars($cssFile, ENT_COMPAT, 'UTF-8'); ?></code></th>
                        <td><?php echo $cssScope($cssFile); ?></td>
                        <td>
                            <?php echo $useCustomInput; ?>
                            <?php echo $renderInlineHelp($field); ?>
                        </td>
                        <td>
                            <?php if ($fileField) : ?>
                                <?php echo $fileField->input; ?>
                                <?php echo $renderInlineHelp($fileField); ?>
                            <?php endif; ?>
                            <?php if ($useCustom && !$customExists) : ?>
                                <div class="mt-1"><span class="badge bg-warning"><?php echo Text::_('COM_JEM_SETTINGS_CSS_STATUS_CUSTOM_MISSING'); ?></span></div>
                            <?php elseif (!$defaultExists) : ?>
                                <div class="mt-1"><span class="badge bg-danger"><?php echo Text::_('COM_JEM_SETTINGS_CSS_STATUS_DEFAULT_MISSING'); ?></span></div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </fieldset>
</div>
    <div class="width-100" style="padding: 10px 1vw;">
        <fieldset class="options-form">
        <legend><?php echo Text::_('COM_JEM_SETTINGS_LEGEND_CSS_COLOR_BACKGROUND'); ?></legend>
        <ul class="adminformlist label-button-line">
            <?php foreach ($this->form->getFieldset('css_color') as $field): ?>
                <li><?php echo $field->label; ?> <?php echo $field->input; ?><?php echo $renderInlineHelp($field); ?></li>
            <?php endforeach; ?>
        </ul>
    </fieldset>
</div>
    <div class="width-100" style="padding: 10px 1vw;">
        <fieldset class="options-form">
        <legend><?php echo Text::_('COM_JEM_SETTINGS_LEGEND_CSS_COLOR_BORDER'); ?></legend>
        <ul class="adminformlist label-button-line">
            <?php foreach ($this->form->getFieldset('css_color_border') as $field): ?>
                <li><?php echo $field->label; ?> <?php echo $field->input; ?><?php echo $renderInlineHelp($field); ?></li>
            <?php endforeach; ?>
        </ul>
    </fieldset>
</div>
    <div class="width-100" style="padding: 10px 1vw;">
        <fieldset class="options-form">
        <legend><?php echo Text::_('COM_JEM_SETTINGS_LEGEND_CSS_COLOR_FONT'); ?></legend>
        <ul class="adminformlist label-button-line">
            <?php foreach ($this->form->getFieldset('css_color_font') as $field): ?>
                <li><?php echo $field->label; ?> <?php echo $field->input; ?><?php echo $renderInlineHelp($field); ?></li>
            <?php endforeach; ?>
        </ul>
    </fieldset>
</div>
</div><div class="clr"></div>
