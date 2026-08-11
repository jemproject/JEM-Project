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
use Joomla\CMS\Uri\Uri;

$sampleRoot = rtrim(Uri::root(), '/');
$sampleValues = array(
    'user_name' => 'Alex Morgan',
    'actor_name' => 'Event administrator',
    'comment' => 'Example registration comment',
    'event_title' => 'JEM Community Day',
    'event_date' => '2030-06-15',
    'event_time' => '18:00',
    'venue' => 'Community Hall',
    'city' => 'Example City',
    'places' => '2',
    'event_description' => 'Example event description.',
    'event_url' => $sampleRoot . '/event',
    'event_image_url' => $sampleRoot . '/images/jem/events/example-event.jpg',
    'venue_image_url' => $sampleRoot . '/images/jem/venues/example-venue.jpg',
    'site_name' => 'Example Site',
);
?>

<form action="<?php echo Route::_('index.php?option=com_jem&task=notificationtemplate.save'); ?>" method="post" name="adminForm" id="adminForm">
    <div class="container-fluid">
        <div class="row g-4">
            <div class="col-xl-8">
                <div class="card mb-4">
                    <div class="card-body">
                        <dl class="row mb-4">
                            <dt class="col-sm-3"><?php echo Text::_('COM_JEM_NOTIFICATION_TEMPLATE_IDENTIFIER'); ?></dt>
                            <dd class="col-sm-9"><code><?php echo $this->escape($this->item->template_id); ?></code></dd>
                            <dt class="col-sm-3"><?php echo Text::_('JFIELD_LANGUAGE_LABEL'); ?></dt>
                            <dd class="col-sm-9"><code><?php echo $this->escape($this->item->language); ?></code></dd>
                            <dt class="col-sm-3"><?php echo Text::_('COM_JEM_NOTIFICATION_TEMPLATE_SOURCE'); ?></dt>
                            <dd class="col-sm-9">
                                <?php if ($this->item->customized) : ?>
                                    <span class="badge bg-success"><?php echo Text::_('COM_JEM_NOTIFICATION_TEMPLATE_CUSTOM'); ?></span>
                                <?php else : ?>
                                    <span class="badge bg-secondary"><?php echo Text::_('COM_JEM_NOTIFICATION_TEMPLATE_DEFAULT'); ?></span>
                                <?php endif; ?>
                            </dd>
                        </dl>

                        <div class="mb-4">
                            <?php echo $this->form->getLabel('subject'); ?>
                            <?php echo $this->form->getInput('subject'); ?>
                        </div>
                        <div class="mb-4">
                            <?php echo $this->form->getLabel('body'); ?>
                            <?php echo $this->form->getInput('body'); ?>
                            <div class="form-text"><?php echo Text::_('COM_JEM_NOTIFICATION_TEMPLATE_PLAIN_BODY_DESC'); ?></div>
                        </div>
                        <div class="mb-4">
                            <?php echo $this->form->getLabel('htmlbody'); ?>
                            <?php echo $this->form->getInput('htmlbody'); ?>
                            <div class="form-text"><?php echo Text::_('COM_JEM_NOTIFICATION_TEMPLATE_HTML_BODY_DESC'); ?></div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><strong><?php echo Text::_('COM_JEM_NOTIFICATION_TEMPLATE_PREVIEW'); ?></strong></div>
                    <div class="card-body">
                        <button type="button" class="btn btn-outline-primary mb-3" id="jemNotificationPreviewButton">
                            <?php echo Text::_('COM_JEM_NOTIFICATION_TEMPLATE_REFRESH_PREVIEW'); ?>
                        </button>
                        <h4 id="jemNotificationPreviewSubject"></h4>
                        <pre id="jemNotificationPreviewBody" class="border rounded bg-light p-3" style="white-space:pre-wrap;overflow-wrap:anywhere"></pre>
                        <iframe id="jemNotificationPreviewHtml" sandbox="" class="border rounded w-100" style="min-height:260px" title="<?php echo Text::_('COM_JEM_NOTIFICATION_TEMPLATE_HTML_PREVIEW'); ?>"></iframe>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card sticky-xl-top" style="top:1rem">
                    <div class="card-header"><strong><?php echo Text::_('COM_JEM_NOTIFICATION_TEMPLATE_AVAILABLE_VARIABLES'); ?></strong></div>
                    <div class="card-body">
                        <p><?php echo Text::_('COM_JEM_NOTIFICATION_TEMPLATE_VARIABLES_DESC'); ?></p>
                        <label class="form-label" for="jemNotificationTokenTarget"><?php echo Text::_('COM_JEM_NOTIFICATION_TEMPLATE_INSERT_IN'); ?></label>
                        <select id="jemNotificationTokenTarget" class="form-select mb-3">
                            <option value="jform_subject"><?php echo Text::_('COM_JEM_NOTIFICATION_TEMPLATE_SUBJECT'); ?></option>
                            <option value="jform_body"><?php echo Text::_('COM_JEM_NOTIFICATION_TEMPLATE_PLAIN_BODY'); ?></option>
                            <option value="jform_htmlbody"><?php echo Text::_('COM_JEM_NOTIFICATION_TEMPLATE_HTML_BODY'); ?></option>
                        </select>
                        <div class="d-flex flex-wrap gap-2" id="jemNotificationTokens">
                            <?php foreach ($this->item->allowed_tokens as $token) : ?>
                                <button type="button" class="btn btn-outline-secondary btn-sm jem-notification-token" data-token="{<?php echo $this->escape($token); ?>}">
                                    <code>{<?php echo $this->escape($token); ?>}</code>
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <hr>
                        <p class="small text-muted mb-2"><strong><?php echo Text::_('COM_JEM_NOTIFICATION_TEMPLATE_TOOLS'); ?></strong></p>
                        <button type="button" class="btn btn-outline-primary w-100" id="jemNotificationGenerateHtml">
                            <span class="icon-code" aria-hidden="true"></span>
                            <?php echo Text::_('COM_JEM_NOTIFICATION_TEMPLATE_GENERATE_HTML'); ?>
                        </button>
                        <p class="form-text mb-0"><?php echo Text::_('COM_JEM_NOTIFICATION_TEMPLATE_GENERATE_HTML_DESC'); ?></p>
                        <hr>
                        <p class="small text-muted mb-0"><?php echo Text::_('COM_JEM_NOTIFICATION_TEMPLATE_RECOMMENDED_DESC'); ?></p>
                        <p class="mb-0">
                            <?php foreach ($this->item->recommended_tokens as $token) : ?>
                                <code class="me-2">{<?php echo $this->escape($token); ?>}</code>
                            <?php endforeach; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php echo $this->form->getInput('template_id'); ?>
    <?php echo $this->form->getInput('language'); ?>
    <input type="hidden" name="template_id" value="<?php echo $this->escape($this->item->template_id); ?>" />
    <input type="hidden" name="language" value="<?php echo $this->escape($this->item->language); ?>" />
    <input type="hidden" name="task" value="notificationtemplate.save" />
    <?php echo HTMLHelper::_('form.token'); ?>
</form>

<script>
(function () {
    'use strict';

    const samples = <?php echo json_encode($sampleValues, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const form = document.getElementById('adminForm');
    const tokenTarget = document.getElementById('jemNotificationTokenTarget');
    const cursorPositions = {};

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

        const field = document.getElementById(id);
        if (field) {
            field.dispatchEvent(new Event('input', { bubbles: true }));
            field.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    function insertToken(id, token) {
        const editor = editorInstance(id);
        if (editor && typeof editor.replaceSelection === 'function') {
            editor.replaceSelection(token);
            return;
        }

        const field = document.getElementById(id);
        if (!field) {
            return;
        }

        const cursor = cursorPositions[id] || {
            start: field.selectionStart,
            end: field.selectionEnd
        };
        field.focus();
        if (typeof field.setRangeText === 'function') {
            field.setRangeText(token, cursor.start, cursor.end, 'end');
        } else {
            field.value += token;
        }
        rememberCursor(id);
        field.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function rememberCursor(id) {
        const field = document.getElementById(id);
        if (!field || typeof field.selectionStart !== 'number') {
            return;
        }

        cursorPositions[id] = {
            start: field.selectionStart,
            end: field.selectionEnd
        };
    }

    function activateTokenTarget(id) {
        tokenTarget.value = id;
        rememberCursor(id);
    }

    function render(template, html) {
        return template.replace(/\{([a-z][a-z0-9_]*)\}/g, function (match, token) {
            if (!Object.prototype.hasOwnProperty.call(samples, token)) {
                return match;
            }
            const value = String(samples[token]);
            if (!html) {
                return value;
            }
            const node = document.createElement('div');
            node.textContent = value;
            return node.innerHTML;
        });
    }

    function escapeHtml(value) {
        const node = document.createElement('div');
        node.textContent = value;

        return node.innerHTML;
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
        document.getElementById('jemNotificationPreviewSubject').textContent = render(fieldValue('jform_subject'), false);
        document.getElementById('jemNotificationPreviewBody').textContent = render(fieldValue('jform_body'), false);
        const html = fieldValue('jform_htmlbody');
        document.getElementById('jemNotificationPreviewHtml').srcdoc = html
            ? render(html, true)
            : '<p><?php echo addslashes(Text::_('COM_JEM_NOTIFICATION_TEMPLATE_NO_HTML_PREVIEW')); ?></p>';
    }

    ['jform_subject', 'jform_body', 'jform_htmlbody'].forEach(function (id) {
        const field = document.getElementById(id);
        if (!field) {
            return;
        }

        ['focus', 'click', 'keyup', 'select'].forEach(function (eventName) {
            field.addEventListener(eventName, function () {
                activateTokenTarget(id);
            });
        });
    });

    tokenTarget.addEventListener('change', function () {
        const field = document.getElementById(tokenTarget.value);
        if (field) {
            field.focus();
        }
    });

    document.querySelectorAll('.jem-notification-token').forEach(function (button) {
        button.addEventListener('click', function () {
            insertToken(tokenTarget.value, button.dataset.token);
        });
    });
    document.getElementById('jemNotificationGenerateHtml').addEventListener('click', function () {
        const currentHtml = fieldValue('jform_htmlbody').trim();
        if (currentHtml && !window.confirm('<?php echo addslashes(Text::_('COM_JEM_NOTIFICATION_TEMPLATE_GENERATE_HTML_CONFIRM')); ?>')) {
            return;
        }

        setFieldValue('jform_htmlbody', plainTextToHtml(fieldValue('jform_body')));
        activateTokenTarget('jform_htmlbody');
        refreshPreview();
    });
    document.getElementById('jemNotificationPreviewButton').addEventListener('click', refreshPreview);
    refreshPreview();

    if (window.Joomla) {
        const originalSubmit = Joomla.submitbutton;
        Joomla.submitbutton = function (task) {
            if (task === 'notificationtemplate.reset'
                && !window.confirm('<?php echo addslashes(Text::_('COM_JEM_NOTIFICATION_TEMPLATE_RESET_CONFIRM')); ?>')) {
                return;
            }
            if (typeof originalSubmit === 'function') {
                originalSubmit(task);
            } else {
                Joomla.submitform(task, form);
            }
        };
    }
}());
</script>
