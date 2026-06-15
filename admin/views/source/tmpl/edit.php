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

        function updateFullscreenButton() {
            var shell = document.querySelector('.jem-source-editor-shell');
            var fullscreen = document.getElementById('jem-css-source-fullscreen');

            if (!shell || !fullscreen) {
                return;
            }

            fullscreen.textContent = shell.classList.contains('jem-source-editor-fullscreen')
                ? '<?php echo $this->escape(Text::_('COM_JEM_CSSMANAGER_EXIT_FULLSCREEN')); ?>'
                : '<?php echo $this->escape(Text::_('COM_JEM_CSSMANAGER_TOGGLE_FULLSCREEN')); ?>';
        }

        function toggleFullscreen() {
            var shell = document.querySelector('.jem-source-editor-shell');

            if (!shell) {
                return;
            }

            shell.classList.toggle('jem-source-editor-fullscreen');
            document.body.classList.toggle('jem-source-editor-fullscreen-active', shell.classList.contains('jem-source-editor-fullscreen'));
            updateFullscreenButton();

            var cm = getCodeMirror();
            if (cm && typeof cm.refresh === 'function') {
                window.setTimeout(function () {
                    cm.refresh();
                    cm.focus();
                }, 50);
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            var fullscreen = document.getElementById('jem-css-source-fullscreen');
            var check = document.getElementById('jem-css-source-check');
            var shell = document.querySelector('.jem-source-editor-shell');

            if (shell) {
                shell.classList.add('jem-source-editor-fullscreen');
                document.body.classList.add('jem-source-editor-fullscreen-active');
                updateFullscreenButton();
            }

            if (fullscreen) {
                fullscreen.addEventListener('click', toggleFullscreen);
            }

            if (check) {
                check.addEventListener('click', checkCss);
            }
        });

        return {
            check: checkCss,
            sync: sync,
            toggleFullscreen: toggleFullscreen
        };
    }());
</script>

<form action="<?php echo Route::_('index.php?option=com_jem&layout=edit'); ?>" method="post" name="adminForm" id="source-form" class="form-validate">
    <?php if ($this->ftp) : ?>
        <?php echo $this->loadTemplate('ftp'); ?>
    <?php endif; ?>
    <fieldset class="adminform jem-source-edit">
        <legend><?php echo $this->source->custom ? Text::sprintf('COM_JEM_CSSMANAGER_FILENAME_CUSTOM', $this->source->filename) : Text::sprintf('COM_JEM_CSSMANAGER_FILENAME', $this->source->filename); ?></legend>

        <div class="jem-source-edit-header">
            <div>
                <p class="jem-source-edit-title"><code><?php echo htmlspecialchars($this->source->filename, ENT_COMPAT, 'UTF-8'); ?></code></p>
                <span class="badge <?php echo $this->source->custom ? 'bg-info' : 'bg-secondary'; ?>">
                    <?php echo $this->source->custom ? Text::_('COM_JEM_CSSMANAGER_FILE_TYPE_CUSTOM') : Text::_('COM_JEM_CSSMANAGER_FILE_TYPE_STANDARD'); ?>
                </span>
                <?php if ($details && $details->custom && $details->active) : ?>
                    <span class="badge bg-success"><?php echo Text::_('COM_JEM_CSSMANAGER_STATUS_ACTIVE'); ?></span>
                <?php endif; ?>
            </div>
            <div class="jem-source-edit-actions">
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

        <?php if ($details) : ?>
            <dl class="jem-source-meta">
                <div>
                    <dt><?php echo Text::_('COM_JEM_CSSMANAGER_SIZE'); ?></dt>
                    <dd><?php echo $formatBytes($details->size); ?></dd>
                </div>
                <div>
                    <dt><?php echo Text::_('COM_JEM_CSSMANAGER_MODIFIED'); ?></dt>
                    <dd><?php echo $details->modified ? HTMLHelper::_('date', $details->modified, 'Y-m-d H:i') : '-'; ?></dd>
                </div>
                <?php if ($details->custom) : ?>
                    <div>
                        <dt><?php echo Text::_('COM_JEM_CSSMANAGER_SOURCE_FILE'); ?></dt>
                        <dd><?php echo $details->sourceFile ? '<code>' . htmlspecialchars($details->sourceFile, ENT_COMPAT, 'UTF-8') . '</code>' : Text::_('COM_JEM_CSSMANAGER_VERSION_UNKNOWN_SHORT'); ?></dd>
                    </div>
                    <div>
                        <dt><?php echo Text::_('COM_JEM_CSSMANAGER_SOURCE_VERSION'); ?></dt>
                        <dd><?php echo $details->sourceVersion ? htmlspecialchars($details->sourceVersion, ENT_COMPAT, 'UTF-8') : Text::_('COM_JEM_CSSMANAGER_VERSION_UNKNOWN_SHORT'); ?></dd>
                    </div>
                    <div>
                        <dt><?php echo Text::_('COM_JEM_CSSMANAGER_CREATED'); ?></dt>
                        <dd><?php echo $details->created ? HTMLHelper::_('date', $details->created, 'Y-m-d H:i') : '-'; ?></dd>
                    </div>
                    <?php if (!empty($details->usedBy)) : ?>
                        <div>
                            <dt><?php echo Text::_('COM_JEM_CSSMANAGER_USED_BY'); ?></dt>
                            <dd><?php echo htmlspecialchars(implode(', ', $details->usedBy), ENT_COMPAT, 'UTF-8'); ?></dd>
                        </div>
                    <?php endif; ?>
                <?php else : ?>
                    <div>
                        <dt><?php echo Text::_('COM_JEM_CSSMANAGER_VERSION'); ?></dt>
                        <dd><?php echo $details->version ? htmlspecialchars($details->version, ENT_COMPAT, 'UTF-8') : '-'; ?></dd>
                    </div>
                <?php endif; ?>
            </dl>
        <?php endif; ?>

        <div class="jem-source-editor-shell">
            <div class="jem-source-editor-toolbar">
                <button type="button" class="btn btn-secondary" id="jem-css-source-check"><?php echo Text::_('COM_JEM_CSSMANAGER_CHECK_CSS'); ?></button>
                <?php if ($canDo->get('core.edit')) : ?>
                    <button type="button" class="btn btn-primary" onclick="Joomla.submitbutton('source.apply');"><?php echo Text::_('JTOOLBAR_APPLY'); ?></button>
                    <button type="button" class="btn btn-primary" onclick="Joomla.submitbutton('source.save');"><?php echo Text::_('JTOOLBAR_SAVE'); ?></button>
                <?php endif; ?>
                <button type="button" class="btn btn-secondary" onclick="Joomla.submitbutton('source.cancel');"><?php echo Text::_('JTOOLBAR_CLOSE'); ?></button>
                <button type="button" class="btn btn-secondary" id="jem-css-source-fullscreen"><?php echo Text::_('COM_JEM_CSSMANAGER_TOGGLE_FULLSCREEN'); ?></button>
            </div>
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
