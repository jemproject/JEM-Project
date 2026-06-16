<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

HTMLHelper::addIncludePath(JPATH_COMPONENT.'/helpers/html');
$app = Factory::getApplication();
$document = $app->getDocument();
$wa = $document->getWebAssetManager();
$wa->useScript('keepalive')
    ->useScript('form.validate');

$canDo = JEMHelperBackend::getActions();
$details = $this->details;
$isUserOverride = $details && !empty($details->userOverride);
$formatBytes = function ($bytes) {
    $bytes = (int) $bytes;

    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1) . ' MB';
    }

    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }

    return $bytes . ' B';
};
?>
<script>
    Joomla.submitbutton = function(task)
    {
        if (task == 'source.cancel' || document.formvalidator.isValid(document.getElementById('source-form'))) {
            if (window.JemCssSourceEditor) {
                window.JemCssSourceEditor.sync();
            }
            Joomla.submitform(task, document.getElementById('source-form'));
        } else {
            alert('<?php echo $this->escape(Text::_('JGLOBAL_VALIDATION_FORM_FAILED'));?>');
        }
    }

    window.JemCssSourceEditor = (function () {
        function getTextarea() {
            return document.getElementById('jform_source');
        }

        function getCodeMirror() {
            var wrapper = document.querySelector('.jem-source-editor-wrap .CodeMirror');
            return wrapper && wrapper.CodeMirror ? wrapper.CodeMirror : null;
        }

        function getEditorInstance() {
            if (window.Joomla && Joomla.editors && Joomla.editors.instances && Joomla.editors.instances.jform_source) {
                return Joomla.editors.instances.jform_source;
            }

            return null;
        }

        function getValue() {
            var editor = getEditorInstance();

            if (editor && typeof editor.getValue === 'function') {
                return editor.getValue();
            }

            var cm = getCodeMirror();
            if (cm && typeof cm.getValue === 'function') {
                return cm.getValue();
            }

            var textarea = getTextarea();
            return textarea ? textarea.value : '';
        }

        function sync() {
            var editor = getEditorInstance();

            if (editor && typeof editor.save === 'function') {
                editor.save();
            }

            var cm = getCodeMirror();
            var textarea = getTextarea();

            if (cm && textarea && typeof cm.save === 'function') {
                cm.save();
            }
        }

        function offsetForLine(value, line) {
            var currentLine = 1;
            var offset = 0;

            while (offset < value.length && currentLine < line) {
                if (value.charAt(offset) === '\n') {
                    currentLine++;
                }

                offset++;
            }

            return offset;
        }

        function focusLine(line) {
            var cm = getCodeMirror();
            var value = getValue();

            if (cm && typeof cm.setCursor === 'function') {
                cm.focus();
                cm.setCursor(Math.max(line - 1, 0), 0);
                if (typeof cm.scrollIntoView === 'function') {
                    cm.scrollIntoView({line: Math.max(line - 1, 0), ch: 0}, 120);
                }
                return;
            }

            var textarea = getTextarea();
            if (textarea && typeof textarea.setSelectionRange === 'function') {
                var offset = offsetForLine(value, line);
                textarea.focus();
                textarea.setSelectionRange(offset, offset);
            }
        }

        function showResult(message, type) {
            var result = document.getElementById('jem-css-source-check-result');

            if (!result) {
                return;
            }

            result.className = 'jem-source-check-result alert alert-' + type;
            result.textContent = message;
            result.hidden = false;
        }

        function checkCss() {
            sync();

            var value = getValue();
            var stack = [];
            var line = 1;
            var inComment = false;
            var quote = '';
            var escaped = false;

            for (var i = 0; i < value.length; i++) {
                var character = value.charAt(i);
                var next = value.charAt(i + 1);

                if (character === '\n') {
                    line++;
                }

                if (inComment) {
                    if (character === '*' && next === '/') {
                        inComment = false;
                        i++;
                    }
                    continue;
                }

                if (quote) {
                    if (escaped) {
                        escaped = false;
                    } else if (character === '\\') {
                        escaped = true;
                    } else if (character === quote) {
                        quote = '';
                    }
                    continue;
                }

                if (character === '/' && next === '*') {
                    inComment = true;
                    i++;
                    continue;
                }

                if (character === '"' || character === "'") {
                    quote = character;
                    continue;
                }

                if (character === '{') {
                    stack.push(line);
                    continue;
                }

                if (character === '}') {
                    if (!stack.length) {
                        focusLine(line);
                        showResult('<?php echo $this->escape(Text::_('COM_JEM_CSSMANAGER_CHECK_UNEXPECTED_CLOSING_BRACE')); ?>'.replace('%s', line), 'warning');
                        return false;
                    }

                    stack.pop();
                }
            }

            if (inComment) {
                focusLine(line);
                showResult('<?php echo $this->escape(Text::_('COM_JEM_CSSMANAGER_CHECK_UNCLOSED_COMMENT')); ?>'.replace('%s', line), 'warning');
                return false;
            }

            if (quote) {
                focusLine(line);
                showResult('<?php echo $this->escape(Text::_('COM_JEM_CSSMANAGER_CHECK_UNCLOSED_STRING')); ?>'.replace('%s', line), 'warning');
                return false;
            }

            if (stack.length) {
                var openLine = stack.pop();
                focusLine(openLine);
                showResult('<?php echo $this->escape(Text::_('COM_JEM_CSSMANAGER_CHECK_MISSING_CLOSING_BRACE')); ?>'.replace('%s', openLine), 'warning');
                return false;
            }

            showResult('<?php echo $this->escape(Text::_('COM_JEM_CSSMANAGER_CHECK_OK')); ?>', 'success');
            return true;
        }

        document.addEventListener('DOMContentLoaded', function () {
            var check = document.getElementById('jem-css-source-check');

            if (check) {
                check.addEventListener('click', checkCss);
            }
        });

        return {
            check: checkCss,
            sync: sync
        };
    }());
</script>

<form action="<?php echo Route::_('index.php?option=com_jem&layout=edit'); ?>" method="post" name="adminForm" id="source-form" class="form-validate">
    <?php if ($this->ftp) : ?>
        <?php echo $this->loadTemplate('ftp'); ?>
    <?php endif; ?>
    <fieldset class="adminform jem-source-edit">
        <legend class="visually-hidden"><?php echo $this->source->custom ? Text::sprintf('COM_JEM_CSSMANAGER_FILENAME_CUSTOM', $this->source->filename) : Text::sprintf('COM_JEM_CSSMANAGER_FILENAME', $this->source->filename); ?></legend>

        <div class="jem-source-edit-header" style="display: grid; grid-template-areas: 'title actions' 'meta actions'; grid-template-columns: minmax(0, 1fr) max-content; align-items: center; gap: .35rem 1rem;">
            <div class="jem-source-edit-title" style="grid-area: title;">
                <?php echo $this->source->custom ? Text::sprintf('COM_JEM_CSSMANAGER_FILENAME_CUSTOM', '<code>' . htmlspecialchars($this->source->filename, ENT_COMPAT, 'UTF-8') . '</code>') : Text::sprintf('COM_JEM_CSSMANAGER_FILENAME', '<code>' . htmlspecialchars($this->source->filename, ENT_COMPAT, 'UTF-8') . '</code>'); ?>
                <?php if ($isUserOverride) : ?>
                    <span class="badge bg-primary"><?php echo Text::_('COM_JEM_CSSMANAGER_FILE_TYPE_USER_OVERRIDE'); ?></span>
                <?php else : ?>
                    <span class="badge <?php echo $this->source->custom ? 'bg-info' : 'bg-secondary'; ?>"><?php echo $this->source->custom ? Text::_('COM_JEM_CSSMANAGER_FILE_TYPE_CUSTOM') : Text::_('COM_JEM_CSSMANAGER_FILE_TYPE_STANDARD'); ?></span>
                <?php endif; ?>
                <?php if ($details && $details->custom && ($details->active || $isUserOverride)) : ?>
                    <span class="badge bg-success"><?php echo Text::_('COM_JEM_CSSMANAGER_STATUS_ACTIVE'); ?></span>
                <?php endif; ?>
            </div>
            <?php if ($details) : ?>
                <div class="jem-source-meta-inline" style="grid-area: meta;">
                    <strong><?php echo Text::_('COM_JEM_CSSMANAGER_SIZE'); ?></strong> <?php echo $formatBytes($details->size); ?>
                    <strong><?php echo Text::_('COM_JEM_CSSMANAGER_MODIFIED'); ?></strong> <?php echo $details->modified ? HTMLHelper::_('date', $details->modified, 'Y-m-d H:i') : '-'; ?>
                    <?php if ($isUserOverride) : ?>
                        <strong><?php echo Text::_('COM_JEM_SETTINGS_CSS_SCOPE'); ?></strong> <?php echo htmlspecialchars($details->scope, ENT_COMPAT, 'UTF-8'); ?>
                    <?php elseif ($details->custom) : ?>
                        <strong><?php echo Text::_('COM_JEM_CSSMANAGER_VERSION'); ?></strong> <?php echo $details->sourceVersion ? htmlspecialchars($details->sourceVersion, ENT_COMPAT, 'UTF-8') : Text::_('COM_JEM_CSSMANAGER_VERSION_UNKNOWN_SHORT'); ?>
                        <?php if ($details->sourceFile) : ?>
                            <strong><?php echo Text::_('COM_JEM_CSSMANAGER_SOURCE_FILE'); ?></strong> <code><?php echo htmlspecialchars($details->sourceFile, ENT_COMPAT, 'UTF-8'); ?></code>
                        <?php endif; ?>
                    <?php else : ?>
                        <strong><?php echo Text::_('COM_JEM_CSSMANAGER_VERSION'); ?></strong> <?php echo $details->version ? htmlspecialchars($details->version, ENT_COMPAT, 'UTF-8') : '-'; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <div class="jem-source-edit-actions" style="grid-area: actions; justify-self: end; align-self: center; display: flex; flex-wrap: nowrap; justify-content: flex-end; gap: .5rem; white-space: nowrap;">
                <button type="button" class="btn btn-secondary" id="jem-css-source-check"><?php echo Text::_('COM_JEM_CSSMANAGER_CHECK_CSS'); ?></button>
                <a class="btn btn-secondary" href="<?php echo Route::_('index.php?option=com_jem&view=cssmanager'); ?>"><?php echo Text::_('COM_JEM_CSSMANAGER_TITLE'); ?></a>
                <a class="btn btn-secondary" href="<?php echo Route::_('index.php?option=com_jem&view=settings#layout'); ?>"><?php echo Text::_('COM_JEM_SETTINGS_TITLE'); ?></a>
                <?php if (!$this->source->custom) : ?>
                    <a class="btn btn-primary jem-copy-custom-btn"
                        href="<?php echo Route::_('index.php?option=com_jem&task=cssmanager.copycustom&file=' . rawurlencode($this->source->filename) . '&' . Session::getFormToken() . '=1'); ?>"
                        onclick="var target = prompt('<?php echo htmlspecialchars(Text::_('COM_JEM_CSSMANAGER_COPY_AS_CUSTOM_PROMPT'), ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($this->source->filename, ENT_QUOTES, 'UTF-8'); ?>'); if (!target) { return false; } this.href += '&customfile=' + encodeURIComponent(target);">
                        <span><?php echo Text::_('COM_JEM_CSSMANAGER_COPY_AS_CUSTOM'); ?></span><span class="jem-copy-custom-arrow" aria-hidden="true"></span>
                    </a>
                <?php elseif ($details) : ?>
                    <a class="btn btn-danger"
                        href="<?php echo Route::_('index.php?option=com_jem&task=cssmanager.deletecustom&file=' . rawurlencode($this->source->filename) . '&' . Session::getFormToken() . '=1'); ?>"
                        onclick="return confirm('<?php echo htmlspecialchars(Text::sprintf('COM_JEM_CSSMANAGER_CUSTOM_FILE_DELETE_CONFIRM', $this->source->filename), ENT_QUOTES, 'UTF-8'); ?>');">
                        <?php echo Text::_('JACTION_DELETE'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!$this->source->custom) : ?>
            <div class="alert alert-warning jem-source-edit-warning">
                <?php echo Text::_('COM_JEM_CSSMANAGER_STANDARD_EDIT_WARNING'); ?>
            </div>
        <?php endif; ?>

        <div class="jem-source-editor-shell">
            <div id="jem-css-source-check-result" hidden></div>

            <div class="jem-source-editor-wrap editor-border">
                <?php echo $this->form->getInput('source'); ?>
            </div>
        </div>
        <input type="hidden" name="task" value="" />
        <?php echo HTMLHelper::_('form.token'); ?>
    </fieldset>

    <?php echo $this->form->getInput('filename'); ?>
</form>
