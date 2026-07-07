<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;

$wa = $this->document->getWebAssetManager();
$wa->useScript('keepalive');


$canDo = JEMHelperBackend::getActions();
$customByStandard = array();
$customExtras = array();
$standardFiles = !empty($this->files['css']) ? $this->files['css'] : array();
$standardNames = array_map(function ($file) {
    return $file->name;
}, $standardFiles);
$standardStems = array();

foreach ($standardNames as $standardName) {
    $standardStems[$standardName] = preg_replace('/\.css$/', '', $standardName);
}

uasort($standardStems, function ($a, $b) {
    return strlen($b) <=> strlen($a);
});

$findStandardForCustom = function ($customName) use ($standardNames, $standardStems) {
    if (in_array($customName, $standardNames, true)) {
        return $customName;
    }

    foreach ($standardStems as $standardName => $stem) {
        if (strpos($customName, $stem . '-') === 0 || strpos($customName, $stem . '_') === 0) {
            return $standardName;
        }
    }

    return '';
};

if (!empty($this->files['custom'])) {
    foreach ($this->files['custom'] as $customFile) {
        if (!empty($customFile->sourceFile) && in_array($customFile->sourceFile, $standardNames, true)) {
            $customByStandard[$customFile->sourceFile][] = $customFile;
        } elseif (!empty($customFile->usedBy)) {
            foreach ($customFile->usedBy as $usedBy) {
                if (!isset($customByStandard[$usedBy])) {
                    $customByStandard[$usedBy] = array();
                }

                if (empty($customFile->sourceFile) && in_array($usedBy, $standardNames, true)) {
                    $customFile->sourceFile = $usedBy;
                }

                $customByStandard[$usedBy][] = $customFile;
            }
        } else {
            $standardName = $findStandardForCustom($customFile->name);

            if ($standardName) {
                if (empty($customFile->sourceFile)) {
                    $customFile->sourceFile = $standardName;
                }

                $customByStandard[$standardName][] = $customFile;
            } else {
                $customExtras[] = $customFile;
            }
        }
    }
}

$copyPrompt = Text::_('COM_JEM_CSSMANAGER_COPY_AS_CUSTOM_PROMPT');
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
$sortCustomFiles = function (&$files) {
    usort($files, function ($a, $b) {
        if ($a->active !== $b->active) {
            return $a->active ? -1 : 1;
        }

        return strnatcasecmp($a->name, $b->name);
    });
};
$renderCustomColumn = function ($files, $callback) use ($sortCustomFiles) {
    if (empty($files)) {
        return '';
    }

    $sortCustomFiles($files);

    ob_start();
    ?>
        <div class="jem-cssmanager-custom-column">
            <?php foreach ($files as $file) : ?>
                <div class="jem-cssmanager-custom-line"><?php echo $callback($file); ?></div>
            <?php endforeach; ?>
        </div>
    <?php
    return ob_get_clean();
};
$renderDownloadButton = function ($fileName) {
    $label = Text::_('COM_JEM_CSSMANAGER_DOWNLOAD');
    return '<a class="btn btn-outline-secondary btn-sm" title="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '" aria-label="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '" href="'
        . Route::_('index.php?option=com_jem&task=cssmanager.downloadcustom&file=' . rawurlencode($fileName) . '&' . Session::getFormToken() . '=1')
        . '"><span class="icon-download" aria-hidden="true"></span></a>';
};
$hasReplacementCustomFiles = !empty($this->files['custom']);
$hasUserCssFiles = false;

if (!empty($this->files['usercss'])) {
    foreach ($this->files['usercss'] as $userCssFile) {
        if (!empty($userCssFile->exists)) {
            $hasUserCssFiles = true;
            break;
        }
    }
}

$toggleLabel = Text::_('COM_JEM_CSSMANAGER_TOGGLE_SECTION');
?>

<form action="<?php echo Route::_('index.php?option=com_jem&view=cssmanager'); ?>" method="post" name="adminForm" id="adminForm">
    <?php if (isset($this->sidebar)) : ?>
        <!-- <div id="j-sidebar-container" class="span2">
        <?php //echo $this->sidebar; ?>
    </div> -->
    <?php endif; ?>
    <div id="j-main-container" class="j-main-container">
        <fieldset class="adminform">
            <legend><?php echo Text::_('COM_JEM_CSSMANAGER_DESCRIPTION_LEGEND');?></legend>
            <div class="jem-cssmanager-header">
                <p><?php echo Text::_('COM_JEM_CSSMANAGER_DESCRIPTION');?></p>
                <a class="btn btn-secondary" href="<?php echo Route::_('index.php?option=com_jem&view=settings#layout'); ?>"><?php echo Text::_('COM_JEM_SETTINGS_TITLE'); ?></a>
            </div>
            <div class="table-responsive">
                <table class="table table-striped table-sm jem-cssmanager-table">
                    <thead>
                        <tr class="table-secondary">
                            <th scope="col" colspan="11">
                                <div class="d-flex align-items-start justify-content-between gap-3">
                                    <div>
                                        <?php echo Text::_('COM_JEM_CSSMANAGER_CUSTOM_REPLACEMENTS'); ?>
                                        <div class="small fw-normal text-muted">
                                            <?php echo Text::_('COM_JEM_CSSMANAGER_CUSTOM_REPLACEMENTS_DESC'); ?>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-outline-secondary btn-sm jem-cssmanager-section-toggle"
                                        data-section="replacements"
                                        data-force-open="<?php echo $hasReplacementCustomFiles ? '1' : '0'; ?>"
                                        title="<?php echo htmlspecialchars($toggleLabel, ENT_QUOTES, 'UTF-8'); ?>"
                                        aria-label="<?php echo htmlspecialchars($toggleLabel, ENT_QUOTES, 'UTF-8'); ?>"
                                        aria-expanded="true">
                                        <span class="icon-eye" aria-hidden="true"></span>
                                    </button>
                                </div>
                            </th>
                        </tr>
                        <tr class="jem-cssmanager-section-row" data-section-row="replacements">
                            <th><?php echo Text::_('COM_JEM_CSSMANAGER_FILE'); ?></th>
                            <th><?php echo Text::_('COM_JEM_CSSMANAGER_VERSION'); ?></th>
                            <th><?php echo Text::_('COM_JEM_CSSMANAGER_SIZE'); ?></th>
                            <th><?php echo Text::_('COM_JEM_CSSMANAGER_ACTIONS'); ?></th>
                            <th><?php echo Text::_('COM_JEM_CSSMANAGER_CUSTOM_FILES'); ?></th>
                            <th><?php echo Text::_('COM_JEM_CSSMANAGER_STATUS'); ?></th>
                            <th><?php echo Text::_('COM_JEM_CSSMANAGER_VERSION'); ?></th>
                            <th><?php echo Text::_('COM_JEM_CSSMANAGER_SIZE'); ?></th>
                            <th><?php echo Text::_('COM_JEM_CSSMANAGER_CREATED'); ?></th>
                            <th><?php echo Text::_('COM_JEM_CSSMANAGER_MODIFIED'); ?></th>
                            <th><?php echo Text::_('COM_JEM_CSSMANAGER_ACTIONS'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($this->files['css'] as $file) : ?>
                        <tr class="jem-cssmanager-section-row" data-section-row="replacements">
                            <th scope="row"><code><?php echo htmlspecialchars($file->name, ENT_COMPAT, 'UTF-8'); ?></code></th>
                            <td class="text-muted"><?php echo htmlspecialchars($file->version ?: '-', ENT_COMPAT, 'UTF-8'); ?></td>
                            <td class="text-muted"><?php echo $formatBytes($file->size ?? 0); ?></td>
                            <td class="jem-cssmanager-actions-cell">
                                <?php if ($canDo->get('core.edit')) : ?>
                                    <a class="btn btn-secondary btn-sm jem-copy-custom-btn"
                                        href="<?php echo Route::_('index.php?option=com_jem&task=cssmanager.copycustom&file=' . rawurlencode($file->name) . '&' . Session::getFormToken() . '=1'); ?>"
                                        onclick="var target = prompt('<?php echo htmlspecialchars($copyPrompt, ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($file->name, ENT_QUOTES, 'UTF-8'); ?>'); if (!target) { return false; } this.href += '&customfile=' + encodeURIComponent(target);">
                                        <span><?php echo Text::_('COM_JEM_CSSMANAGER_COPY_AS_CUSTOM'); ?></span><span class="jem-copy-custom-arrow" aria-hidden="true"></span>
                                    </a>
                                <?php endif; ?>
                            </td>
                            <?php $customFiles = $customByStandard[$file->name] ?? array(); ?>
                            <td class="jem-cssmanager-custom-cell">
                                <?php echo $renderCustomColumn($customFiles, function ($customFile) {
                                    return '<code>' . htmlspecialchars($customFile->name, ENT_COMPAT, 'UTF-8') . '</code>';
                                }); ?>
                            </td>
                            <td class="jem-cssmanager-status-cell">
                                <?php echo $renderCustomColumn($customFiles, function ($customFile) {
                                    if (!$customFile->exists) {
                                        return '<span class="badge bg-warning">' . Text::_('COM_JEM_CSSMANAGER_STATUS_MISSING') . '</span>';
                                    }

                                    if ($customFile->active) {
                                        return '<span class="badge bg-success">' . Text::_('COM_JEM_CSSMANAGER_STATUS_ACTIVE') . '</span>';
                                    }

                                    return '<span class="badge bg-secondary">' . Text::_('COM_JEM_CSSMANAGER_STATUS_AVAILABLE') . '</span>';
                                }); ?>
                            </td>
                            <td>
                                <?php echo $renderCustomColumn($customFiles, function ($customFile) {
                                    if (!empty($customFile->sourceVersion)) {
                                        return htmlspecialchars($customFile->sourceVersion, ENT_COMPAT, 'UTF-8');
                                    }

                                    if (!empty($customFile->sourceFile)) {
                                        return '<span class="text-muted">' . Text::_('COM_JEM_CSSMANAGER_VERSION_UNKNOWN_SHORT') . '</span>';
                                    }

                                    return '';
                                }); ?>
                            </td>
                            <td class="text-muted">
                                <?php echo $renderCustomColumn($customFiles, function ($customFile) use ($formatBytes) {
                                    return $formatBytes($customFile->size ?? 0);
                                }); ?>
                            </td>
                            <td class="text-muted">
                                <?php echo $renderCustomColumn($customFiles, function ($customFile) {
                                    return !empty($customFile->created) ? HTMLHelper::_('date', $customFile->created, 'Y-m-d') : '';
                                }); ?>
                            </td>
                            <td class="text-muted">
                                <?php echo $renderCustomColumn($customFiles, function ($customFile) {
                                    return !empty($customFile->modified) ? HTMLHelper::_('date', $customFile->modified, 'Y-m-d') : '';
                                }); ?>
                            </td>
                            <td class="jem-cssmanager-actions-cell">
                                <?php echo $renderCustomColumn($customFiles, function ($customFile) use ($canDo, $renderDownloadButton) {
                                    ob_start();
                                    if ($canDo->get('core.edit') && $customFile->exists) {
                                        echo '<a class="btn btn-secondary btn-sm" href="' . Route::_('index.php?option=com_jem&task=source.edit&id=' . $customFile->id) . '">' . Text::_('JTOOLBAR_EDIT') . '</a>';
                                    }

                                    if ($canDo->get('core.delete') && $customFile->exists) {
                                        echo ' <a class="btn btn-danger btn-sm" href="'
                                            . Route::_('index.php?option=com_jem&task=cssmanager.deletecustom&file=' . rawurlencode($customFile->name) . '&' . Session::getFormToken() . '=1')
                                            . '" onclick="return confirm(\'' . htmlspecialchars(Text::sprintf('COM_JEM_CSSMANAGER_CUSTOM_FILE_DELETE_CONFIRM', $customFile->name), ENT_QUOTES, 'UTF-8') . '\');">'
                                            . Text::_('JACTION_DELETE') . '</a>';
                                    }

                                    if ($customFile->exists) {
                                        echo ' ' . $renderDownloadButton($customFile->name);
                                    }

                                    return ob_get_clean();
                                }); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!empty($this->files['usercss'])) : ?>
                        <tr>
                            <td colspan="11" class="bg-white py-4 border-0"></td>
                        </tr>
                        <tr class="table-secondary">
                            <th scope="row" colspan="11">
                                <div class="d-flex align-items-start justify-content-between gap-3">
                                    <div>
                                        <?php echo Text::_('COM_JEM_CSSMANAGER_USER_FILES'); ?>
                                        <div class="small fw-normal text-muted">
                                            <?php echo Text::_('COM_JEM_CSSMANAGER_USER_FILES_DESC'); ?>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-outline-secondary btn-sm jem-cssmanager-section-toggle"
                                        data-section="usercss"
                                        data-force-open="<?php echo $hasUserCssFiles ? '1' : '0'; ?>"
                                        title="<?php echo htmlspecialchars($toggleLabel, ENT_QUOTES, 'UTF-8'); ?>"
                                        aria-label="<?php echo htmlspecialchars($toggleLabel, ENT_QUOTES, 'UTF-8'); ?>"
                                        aria-expanded="true">
                                        <span class="icon-eye" aria-hidden="true"></span>
                                    </button>
                                </div>
                            </th>
                        </tr>
                        <tr class="jem-cssmanager-section-row" data-section-row="usercss">
                            <th><?php echo Text::_('COM_JEM_CSSMANAGER_FILE'); ?></th>
                            <th><?php echo Text::_('COM_JEM_CSSMANAGER_VERSION'); ?></th>
                            <th><?php echo Text::_('COM_JEM_CSSMANAGER_SIZE'); ?></th>
                            <th><?php echo Text::_('COM_JEM_CSSMANAGER_ACTIONS'); ?></th>
                            <th><?php echo Text::_('COM_JEM_CSSMANAGER_CUSTOM_FILES'); ?></th>
                            <th><?php echo Text::_('COM_JEM_CSSMANAGER_STATUS'); ?></th>
                            <th><?php echo Text::_('COM_JEM_CSSMANAGER_VERSION'); ?></th>
                            <th><?php echo Text::_('COM_JEM_CSSMANAGER_SIZE'); ?></th>
                            <th><?php echo Text::_('COM_JEM_CSSMANAGER_CREATED'); ?></th>
                            <th><?php echo Text::_('COM_JEM_CSSMANAGER_MODIFIED'); ?></th>
                            <th><?php echo Text::_('COM_JEM_CSSMANAGER_ACTIONS'); ?></th>
                        </tr>
                        <?php foreach ($this->files['usercss'] as $userFile) : ?>
                            <tr class="jem-cssmanager-section-row" data-section-row="usercss">
                                <th scope="row">
                                    <code><?php echo htmlspecialchars($userFile->name, ENT_COMPAT, 'UTF-8'); ?></code>
                                    <div class="small text-muted"><?php echo htmlspecialchars($userFile->scope ?? '', ENT_COMPAT, 'UTF-8'); ?></div>
                                </th>
                                <td class="text-muted"><?php echo htmlspecialchars($userFile->definitionVersion ?? '0.0', ENT_COMPAT, 'UTF-8'); ?></td>
                                <td class="text-muted"><?php echo $formatBytes($userFile->definitionSize ?? 0); ?></td>
                                <td class="jem-cssmanager-actions-cell">
                                    <?php if ($canDo->get('core.edit') && !$userFile->exists) : ?>
                                        <a class="btn btn-secondary btn-sm"
                                            href="<?php echo Route::_('index.php?option=com_jem&task=cssmanager.createusercss&file=' . rawurlencode($userFile->name) . '&' . Session::getFormToken() . '=1'); ?>">
                                            <?php echo Text::_('COM_JEM_CSSMANAGER_CREATE'); ?>
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="text-muted"><?php echo htmlspecialchars($userFile->description ?? '', ENT_COMPAT, 'UTF-8'); ?></span>
                                </td>
                                <td class="jem-cssmanager-status-cell">
                                    <?php if ($userFile->exists) : ?>
                                        <span class="badge bg-success"><?php echo Text::_('COM_JEM_CSSMANAGER_STATUS_ACTIVE'); ?></span>
                                    <?php else : ?>
                                        <span class="text-muted"><?php echo Text::_('COM_JEM_CSSMANAGER_STATUS_NOT_CREATED'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $userFile->exists ? htmlspecialchars($userFile->userVersion ?: '1.0', ENT_COMPAT, 'UTF-8') : ''; ?></td>
                                <td class="text-muted"><?php echo $userFile->exists ? $formatBytes($userFile->size ?? 0) : ''; ?></td>
                                <td class="text-muted"><?php echo !empty($userFile->created) ? HTMLHelper::_('date', $userFile->created, 'Y-m-d') : ''; ?></td>
                                <td class="text-muted"><?php echo !empty($userFile->modified) ? HTMLHelper::_('date', $userFile->modified, 'Y-m-d') : ''; ?></td>
                                <td class="jem-cssmanager-actions-cell">
                                    <?php if ($canDo->get('core.edit') && $userFile->exists) : ?>
                                        <a class="btn btn-secondary btn-sm" href="<?php echo Route::_('index.php?option=com_jem&task=source.edit&id=' . $userFile->id); ?>">
                                            <?php echo Text::_('JTOOLBAR_EDIT'); ?>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($canDo->get('core.delete') && $userFile->exists) : ?>
                                        <a class="btn btn-danger btn-sm" href="<?php echo Route::_('index.php?option=com_jem&task=cssmanager.deletecustom&file=' . rawurlencode($userFile->name) . '&' . Session::getFormToken() . '=1'); ?>"
                                            onclick="return confirm('<?php echo htmlspecialchars(Text::sprintf('COM_JEM_CSSMANAGER_CUSTOM_FILE_DELETE_CONFIRM', $userFile->name), ENT_QUOTES, 'UTF-8'); ?>');">
                                            <?php echo Text::_('JACTION_DELETE'); ?>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($userFile->exists) : ?>
                                        <?php echo $renderDownloadButton($userFile->name); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <?php if (!empty($customExtras)) : ?>
                        <tr class="jem-cssmanager-section-row" data-section-row="replacements">
                            <th scope="row"><?php echo Text::_('COM_JEM_CSSMANAGER_UNASSIGNED_CUSTOM_FILES'); ?></th>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td class="jem-cssmanager-custom-cell">
                                <?php echo $renderCustomColumn($customExtras, function ($customFile) {
                                    return '<code>' . htmlspecialchars($customFile->name, ENT_COMPAT, 'UTF-8') . '</code>';
                                }); ?>
                            </td>
                            <td class="jem-cssmanager-status-cell"></td>
                            <td></td>
                            <td class="text-muted">
                                <?php echo $renderCustomColumn($customExtras, function ($customFile) use ($formatBytes) {
                                    return $formatBytes($customFile->size ?? 0);
                                }); ?>
                            </td>
                            <td class="text-muted">
                                <?php echo $renderCustomColumn($customExtras, function ($customFile) {
                                    return !empty($customFile->created) ? HTMLHelper::_('date', $customFile->created, 'Y-m-d') : '';
                                }); ?>
                            </td>
                            <td class="text-muted">
                                <?php echo $renderCustomColumn($customExtras, function ($customFile) {
                                    return !empty($customFile->modified) ? HTMLHelper::_('date', $customFile->modified, 'Y-m-d') : '';
                                }); ?>
                            </td>
                            <td class="jem-cssmanager-actions-cell">
                                <?php echo $renderCustomColumn($customExtras, function ($customFile) use ($canDo, $renderDownloadButton) {
                                    ob_start();
                                    if ($canDo->get('core.edit') && $customFile->exists) {
                                        echo '<a class="btn btn-secondary btn-sm" href="' . Route::_('index.php?option=com_jem&task=source.edit&id=' . $customFile->id) . '">' . Text::_('JTOOLBAR_EDIT') . '</a>';
                                    }

                                    if ($canDo->get('core.delete') && $customFile->exists) {
                                        echo ' <a class="btn btn-danger btn-sm" href="'
                                            . Route::_('index.php?option=com_jem&task=cssmanager.deletecustom&file=' . rawurlencode($customFile->name) . '&' . Session::getFormToken() . '=1')
                                            . '" onclick="return confirm(\'' . htmlspecialchars(Text::sprintf('COM_JEM_CSSMANAGER_CUSTOM_FILE_DELETE_CONFIRM', $customFile->name), ENT_QUOTES, 'UTF-8') . '\');">'
                                            . Text::_('JACTION_DELETE') . '</a>';
                                    }

                                    if ($customFile->exists) {
                                        echo ' ' . $renderDownloadButton($customFile->name);
                                    }

                                    return ob_get_clean();
                                }); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <input type="hidden" name="task" value="" />
        </fieldset>
    </div>
            <?php //if (isset($this->sidebar)) : ?>
    <?php //endif; ?>
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.jem-cssmanager-section-toggle').forEach(function (button) {
        var section = button.getAttribute('data-section');
        var forceOpen = button.getAttribute('data-force-open') === '1';
        var key = 'jem.cssmanager.section.' + section;
        var rows = document.querySelectorAll('[data-section-row="' + section + '"]');
        var setOpen = function (open) {
            rows.forEach(function (row) {
                row.hidden = !open;
            });

            button.setAttribute('aria-expanded', open ? 'true' : 'false');
            button.innerHTML = '<span class="' + (open ? 'icon-eye' : 'icon-eye-close') + '" aria-hidden="true"></span>';
            window.localStorage.setItem(key, open ? '1' : '0');
        };

        setOpen(forceOpen || window.localStorage.getItem(key) !== '0');

        if (forceOpen) {
            button.disabled = true;
            return;
        }

        button.addEventListener('click', function () {
            setOpen(button.getAttribute('aria-expanded') !== 'true');
        });
    });
});
</script>
