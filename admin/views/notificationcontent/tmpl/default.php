<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

$section = $this->item->section;
$sampleRoot = rtrim(Uri::root(), '/');
$sampleValues = array(
    'site_name' => 'Example Site',
    'site_url' => $sampleRoot . '/',
    'privacy_url' => $this->item->privacy_url ?: $sampleRoot . '/privacy',
    'contact_email' => (string) Factory::getApplication()->get('mailfrom', ''),
);
?>

<form action="<?php echo Route::_('index.php?option=com_jem&task=notificationcontent.save'); ?>" method="post" name="adminForm" id="adminForm">
    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item" role="presentation">
            <a class="nav-link" href="<?php echo Route::_('index.php?option=com_jem&view=notifications'); ?>">
                <?php echo Text::_('COM_JEM_NOTIFICATION_TAB_TEMPLATES'); ?>
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link <?php echo $section === 'footer' ? 'active' : ''; ?>"
               href="<?php echo Route::_('index.php?option=com_jem&view=notificationcontent&section=footer&language=' . rawurlencode($this->item->language)); ?>">
                <?php echo Text::_('COM_JEM_NOTIFICATION_TAB_FOOTER'); ?>
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link <?php echo $section === 'disclaimer' ? 'active' : ''; ?>"
               href="<?php echo Route::_('index.php?option=com_jem&view=notificationcontent&section=disclaimer&language=' . rawurlencode($this->item->language)); ?>">
                <?php echo Text::_('COM_JEM_NOTIFICATION_TAB_DISCLAIMER'); ?>
            </a>
        </li>
    </ul>

    <div class="d-flex flex-wrap align-items-end gap-3 mb-4">
        <div>
            <label class="form-label" for="jemNotificationContentLanguage"><?php echo Text::_('JFIELD_LANGUAGE_LABEL'); ?></label>
            <select id="jemNotificationContentLanguage" class="form-select">
                <?php foreach ($this->languages as $language) : ?>
                    <?php
                    $label = $language->title_native ?: $language->title;
                    $url = Route::_(
                        'index.php?option=com_jem&view=notificationcontent&section=' . rawurlencode($section)
                        . '&language=' . rawurlencode($language->lang_code)
                    );
                    ?>
                    <option value="<?php echo $this->escape($url); ?>"
                        <?php echo $language->lang_code === $this->item->language ? 'selected' : ''; ?>
                        <?php echo empty($language->jem_available) ? 'disabled' : ''; ?>>
                        <?php echo $this->escape(
                            $label . ' (' . $language->lang_code . ')'
                            . (empty($language->jem_available) ? ' — ' . Text::_('COM_JEM_NOTIFICATION_LANGUAGE_UNAVAILABLE') : '')
                        ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="pb-2">
            <?php if ($this->item->customized) : ?>
                <span class="badge bg-success"><?php echo Text::_('COM_JEM_NOTIFICATION_TEMPLATE_CUSTOM'); ?></span>
            <?php else : ?>
                <span class="badge bg-secondary"><?php echo Text::_('COM_JEM_NOTIFICATION_TEMPLATE_DEFAULT'); ?></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="alert alert-info">
        <?php echo Text::_($section === 'footer'
            ? 'COM_JEM_NOTIFICATION_FOOTER_INTRO'
            : 'COM_JEM_NOTIFICATION_DISCLAIMER_INTRO'); ?>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="mb-4">
                        <?php echo $this->form->getLabel('body'); ?>
                        <?php echo $this->form->getInput('body'); ?>
                    </div>
                    <div class="mb-4">
                        <?php echo $this->form->getLabel('htmlbody'); ?>
                        <?php echo $this->form->getInput('htmlbody'); ?>
                    </div>
                    <?php if ($section === 'disclaimer') : ?>
                        <div class="mb-4">
                            <?php echo $this->form->renderField('privacy_url'); ?>
                        </div>
                    <?php endif; ?>
                    <div class="row">
                        <div class="col-md-6"><?php echo $this->form->renderField('enabled_user'); ?></div>
                        <div class="col-md-6"><?php echo $this->form->renderField('enabled_admin'); ?></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><strong><?php echo Text::_('COM_JEM_NOTIFICATION_TEMPLATE_PREVIEW'); ?></strong></div>
                <div class="card-body">
                    <button type="button" class="btn btn-outline-primary mb-3" id="jemNotificationContentPreviewButton">
                        <?php echo Text::_('COM_JEM_NOTIFICATION_TEMPLATE_REFRESH_PREVIEW'); ?>
                    </button>
                    <pre id="jemNotificationContentPreviewPlain" class="border rounded bg-light p-3" style="white-space:pre-wrap;overflow-wrap:anywhere"></pre>
                    <iframe id="jemNotificationContentPreviewHtml" sandbox="" class="border rounded w-100" style="min-height:220px" title="<?php echo Text::_('COM_JEM_NOTIFICATION_TEMPLATE_HTML_PREVIEW'); ?>"></iframe>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card sticky-xl-top" style="top:1rem">
                <div class="card-header"><strong><?php echo Text::_('COM_JEM_NOTIFICATION_TEMPLATE_AVAILABLE_VARIABLES'); ?></strong></div>
                <div class="card-body">
                    <p><?php echo Text::_('COM_JEM_NOTIFICATION_TEMPLATE_VARIABLES_DESC'); ?></p>
                    <label class="form-label" for="jemNotificationContentTokenTarget"><?php echo Text::_('COM_JEM_NOTIFICATION_TEMPLATE_INSERT_IN'); ?></label>
                    <select id="jemNotificationContentTokenTarget" class="form-select mb-3">
                        <option value="jform_body"><?php echo Text::_('COM_JEM_NOTIFICATION_CONTENT_PLAIN'); ?></option>
                        <option value="jform_htmlbody"><?php echo Text::_('COM_JEM_NOTIFICATION_CONTENT_HTML'); ?></option>
                    </select>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($this->item->allowed_tokens as $token) : ?>
                            <button type="button" class="btn btn-outline-secondary btn-sm jem-notification-content-token" data-token="{<?php echo $this->escape($token); ?>}">
                                <code>{<?php echo $this->escape($token); ?>}</code>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <hr>
                    <p class="small text-muted mb-2"><strong><?php echo Text::_('COM_JEM_NOTIFICATION_TEMPLATE_TOOLS'); ?></strong></p>
                    <button type="button" class="btn btn-outline-primary w-100" id="jemNotificationContentGenerateHtml">
                        <span class="icon-code" aria-hidden="true"></span>
                        <?php echo Text::_('COM_JEM_NOTIFICATION_TEMPLATE_GENERATE_HTML'); ?>
                    </button>
                    <p class="form-text mb-0"><?php echo Text::_('COM_JEM_NOTIFICATION_TEMPLATE_GENERATE_HTML_DESC'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <?php echo $this->form->getInput('section'); ?>
    <?php echo $this->form->getInput('language'); ?>
    <input type="hidden" name="section" value="<?php echo $this->escape($section); ?>" />
    <input type="hidden" name="language" value="<?php echo $this->escape($this->item->language); ?>" />
    <input type="hidden" name="task" value="notificationcontent.save" />
    <?php echo HTMLHelper::_('form.token'); ?>
</form>

<script>
(function () {
    'use strict';

    const samples = <?php echo json_encode($sampleValues, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const target = document.getElementById('jemNotificationContentTokenTarget');
    const positions = {};

    function editorInstance(id) {
        return window.Joomla && Joomla.editors && Joomla.editors.instances
            ? Joomla.editors.instances[id] || null
            : null;
    }

    function fieldValue(id) {
        const editor = editorInstance(id);
        if (editor && typeof editor.getValue === 'function') {
            return editor.getValue();
        }
        const field = document.getElementById(id);
        return field ? field.value : '';
    }

    function setFieldValue(id, value) {
        const editor = editorInstance(id);
        if (editor && typeof editor.setValue === 'function') {
            editor.setValue(value);
        } else {
            const field = document.getElementById(id);
            if (field) {
                field.value = value;
            }
        }
    }

    function remember(id) {
        const field = document.getElementById(id);
        if (field && typeof field.selectionStart === 'number') {
            positions[id] = { start: field.selectionStart, end: field.selectionEnd };
        }
    }

    function activate(id) {
        target.value = id;
        remember(id);
    }

    function insert(id, token) {
        const editor = editorInstance(id);
        if (editor && typeof editor.replaceSelection === 'function') {
            editor.replaceSelection(token);
            return;
        }
        const field = document.getElementById(id);
        if (!field) {
            return;
        }
        const cursor = positions[id] || { start: field.selectionStart, end: field.selectionEnd };
        field.focus();
        if (typeof field.setRangeText === 'function') {
            field.setRangeText(token, cursor.start, cursor.end, 'end');
        } else {
            field.value += token;
        }
        remember(id);
    }

    function escapeHtml(value) {
        const node = document.createElement('div');
        node.textContent = value;
        return node.innerHTML;
    }

    function render(value, html) {
        return String(value).replace(/\{([a-z][a-z0-9_]*)\}/g, function (match, name) {
            if (!Object.prototype.hasOwnProperty.call(samples, name)) {
                return match;
            }
            return html ? escapeHtml(String(samples[name])) : String(samples[name]);
        });
    }

    function plainTextToHtml(value) {
        const text = String(value).replace(/\r\n?/g, '\n').trim();
        if (!text) {
            return '';
        }
        return text.split(/\n{2,}/).map(function (paragraph) {
            return '<p>' + escapeHtml(paragraph).replace(/\n/g, '<br>\n') + '</p>';
        }).join('\n');
    }

    function refreshPreview() {
        document.getElementById('jemNotificationContentPreviewPlain').textContent = render(fieldValue('jform_body'), false);
        const html = fieldValue('jform_htmlbody');
        document.getElementById('jemNotificationContentPreviewHtml').srcdoc = html
            ? render(html, true)
            : '<p><?php echo addslashes(Text::_('COM_JEM_NOTIFICATION_TEMPLATE_NO_HTML_PREVIEW')); ?></p>';
    }

    ['jform_body', 'jform_htmlbody'].forEach(function (id) {
        const field = document.getElementById(id);
        if (!field) {
            return;
        }
        ['focus', 'click', 'keyup', 'select'].forEach(function (eventName) {
            field.addEventListener(eventName, function () { activate(id); });
        });
    });

    document.querySelectorAll('.jem-notification-content-token').forEach(function (button) {
        button.addEventListener('click', function () { insert(target.value, button.dataset.token); });
    });
    document.getElementById('jemNotificationContentGenerateHtml').addEventListener('click', function () {
        const current = fieldValue('jform_htmlbody').trim();
        if (current && !window.confirm('<?php echo addslashes(Text::_('COM_JEM_NOTIFICATION_TEMPLATE_GENERATE_HTML_CONFIRM')); ?>')) {
            return;
        }
        setFieldValue('jform_htmlbody', plainTextToHtml(fieldValue('jform_body')));
        activate('jform_htmlbody');
        refreshPreview();
    });
    document.getElementById('jemNotificationContentPreviewButton').addEventListener('click', refreshPreview);
    document.getElementById('jemNotificationContentLanguage').addEventListener('change', function () {
        window.location.href = this.value;
    });
    refreshPreview();
}());
</script>
