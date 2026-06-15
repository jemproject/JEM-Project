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
                        <tr>
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
                        <tr>
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
                                <?php echo $renderCustomColumn($customFiles, function ($customFile) use ($canDo) {
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

                                    return ob_get_clean();
                                }); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!empty($customExtras)) : ?>
                        <tr>
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
                                <?php echo $renderCustomColumn($customExtras, function ($customFile) use ($canDo) {
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
