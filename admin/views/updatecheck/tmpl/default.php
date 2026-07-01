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
use Joomla\CMS\Router\Route;

$update = $this->updatedata ?? null;

// No update data at all -> treat as connection problem
if (!$update) {
    $update = new stdClass();
    $update->failed = 1;
} else {
    // Connection worked if 'failed' not set
    $update->failed = $update->failed ?? 0;
}

// Ensure properties exist
$update->current          = $update->current ?? null;
$update->versiondetail    = $update->versiondetail ?? '';
$update->installedversion = $update->installedversion ?? '';
$update->date             = $update->date ?? '';
$update->changes          = is_array($update->changes ?? null) ? $update->changes : [];
$update->notes            = is_array($update->notes ?? null) ? $update->notes : [];
$update->info             = $update->info ?? '';
$update->download         = $update->download ?? '';
$update->updateurl        = $update->updateurl ?? 'https://www.joomlaeventmanager.net/updatecheck/update_pkg_jem.xml';
$update->joomlaversion    = $update->joomlaversion ?? JVERSION;
$update->targetplatform   = $update->targetplatform ?? '';
$update->phpversion       = $update->phpversion ?? PHP_VERSION;
$update->phpminimum       = $update->phpminimum ?? '';
$update->installeddate    = $update->installeddate ?? '';
$update->manifestpath     = $update->manifestpath ?? (defined('JPATH_COMPONENT_ADMINISTRATOR') ? JPATH_COMPONENT_ADMINISTRATOR . '/jem.xml' : 'administrator/components/com_jem/jem.xml');
$update->localnotes       = is_array($update->localnotes ?? null) ? $update->localnotes : [];
$update->localdate        = $update->localdate ?? '';

$statusClass = 'warning';
$statusIcon  = 'com_jem/icon-48-unknown-version.svg';
$statusText  = Text::_('COM_JEM_UPDATECHECK_NEWER_VERSION');
$statusDesc  = Text::_('COM_JEM_UPDATECHECK_STATUS_DESC');

if ((int) $update->failed === 0 && $update->current !== null) {
    if ((int) $update->current === 0) {
        $statusClass = 'success';
        $statusIcon  = 'com_jem/icon-48-latest-version.svg';
        $statusText  = Text::_('COM_JEM_UPDATECHECK_LATEST_VERSION');
    } elseif ((int) $update->current === -1) {
        $statusClass = 'danger';
        $statusIcon  = 'com_jem/icon-48-update.svg';
        $statusText  = Text::_('COM_JEM_UPDATECHECK_OLD_VERSION');
    } elseif ((int) $update->current > 0) {
        $statusDesc = Text::_('COM_JEM_UPDATECHECK_TEST_VERSION_DESC');
    }
} elseif ((int) $update->failed !== 0) {
    $statusClass = 'danger';
    $statusIcon  = 'com_jem/icon-48-update.svg';
    $statusText  = Text::_('COM_JEM_UPDATECHECK_CONNECTION_FAILED');
} else {
    $statusClass = 'warning';
    $statusText  = Text::_('COM_JEM_UPDATECHECK_NO_COMPATIBLE_VERSION');
}

$notesTitle = Text::_('COM_JEM_UPDATECHECK_VERSION_NOTES');
$notesDate  = $update->date;
$notes      = $update->notes;

if ((int) $update->failed === 0 && $update->current !== null) {
    if ((int) $update->current === -1) {
        $notesTitle = Text::_('COM_JEM_UPDATECHECK_NEW_VERSION_NOTES');
    } elseif ((int) $update->current === 0) {
        $notesTitle = Text::_('COM_JEM_UPDATECHECK_INSTALLED_VERSION_NOTES');
    } elseif ((int) $update->current > 0) {
        $notesTitle = Text::_('COM_JEM_UPDATECHECK_LOCAL_NEWER_VERSION_NOTES');
        $notesDate  = $update->localdate ?: $update->date;
        $notes      = !empty($update->localnotes) ? $update->localnotes : $update->notes;
    }
}
?>

<form action="<?php echo Route::_('index.php?option=com_jem&view=updatecheck'); ?>" method="post" name="adminForm" id="adminForm">
    <style>
        .jem-updatecheck {
            display: grid;
            gap: 1rem;
            width: 100%;
        }

        .jem-updatecheck-status {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr);
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border: 1px solid #d6dde8;
            background: #fff;
        }

        .jem-updatecheck-status img {
            width: 48px;
            height: 48px;
        }

        .jem-updatecheck-status h2 {
            margin: 0 0 .2rem;
            font-size: 1.25rem;
        }

        .jem-updatecheck-status p {
            margin: 0;
        }

        .jem-updatecheck-status--success h2 {
            color: #2f7d32;
        }

        .jem-updatecheck-status--danger h2 {
            color: #b02a37;
        }

        .jem-updatecheck-status--warning h2 {
            color: #b26b00;
        }

        .jem-updatecheck-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem;
        }

        .jem-updatecheck-card {
            border: 1px solid #d6dde8;
            background: #fff;
        }

        .jem-updatecheck-card h3 {
            margin: 0;
            padding: .75rem 1rem;
            border-bottom: 1px solid #d6dde8;
            background: #f8fafc;
            font-size: 1.05rem;
        }

        .jem-updatecheck-list {
            display: grid;
            grid-template-columns: minmax(10rem, max-content) minmax(0, 1fr);
            margin: 0;
        }

        .jem-updatecheck-list dt,
        .jem-updatecheck-list dd {
            margin: 0;
            padding: .7rem 1rem;
            border-bottom: 1px solid #edf0f5;
        }

        .jem-updatecheck-list dt {
            font-weight: 700;
            color: #1f2937;
        }

        .jem-updatecheck-list dd {
            min-width: 0;
        }

        .jem-updatecheck-list ul {
            margin: 0;
            padding-left: 1.2rem;
        }

        .jem-updatecheck-actions {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
            padding: 1rem;
        }

        .jem-updatecheck-muted {
            color: #6b7280;
        }

        @media (max-width: 900px) {
            .jem-updatecheck-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            .jem-updatecheck-status,
            .jem-updatecheck-list {
                grid-template-columns: 1fr;
            }

            .jem-updatecheck-list dt {
                padding-bottom: .2rem;
                border-bottom: 0;
            }

            .jem-updatecheck-list dd {
                padding-top: .2rem;
            }
        }
    </style>

    <?php if (isset($this->sidebar)) : ?>
    <div id="j-sidebar-container" class="span2">
        <?php echo $this->sidebar; ?>
    </div>
    <div id="j-main-container" class="span10">
        <?php endif; ?>

        <div class="jem-updatecheck">
            <div class="jem-updatecheck-status jem-updatecheck-status--<?php echo $statusClass; ?>">
                <?php echo HTMLHelper::_('image', $statusIcon, '', array(), true); ?>
                <div>
                    <h2><?php echo htmlspecialchars($statusText, ENT_QUOTES, 'UTF-8'); ?></h2>
                    <p class="jem-updatecheck-muted"><?php echo htmlspecialchars($statusDesc, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            </div>

            <div class="jem-updatecheck-grid">
                <section class="jem-updatecheck-card">
                    <h3><?php echo Text::_('COM_JEM_UPDATECHECK_LOCAL_INSTALLATION'); ?></h3>
                    <dl class="jem-updatecheck-list">
                        <dt><?php echo Text::_('COM_JEM_UPDATECHECK_INSTALLED_VERSION'); ?></dt>
                        <dd><?php echo htmlspecialchars((string) $update->installedversion, ENT_QUOTES, 'UTF-8'); ?></dd>

                        <dt><?php echo Text::_('COM_JEM_UPDATECHECK_INSTALLED_DATE'); ?></dt>
                        <dd><?php echo htmlspecialchars((string) ($update->installeddate ?: '-'), ENT_QUOTES, 'UTF-8'); ?></dd>

                        <dt><?php echo Text::_('COM_JEM_UPDATECHECK_JOOMLA_VERSION'); ?></dt>
                        <dd><?php echo htmlspecialchars((string) $update->joomlaversion, ENT_QUOTES, 'UTF-8'); ?></dd>

                        <dt><?php echo Text::_('COM_JEM_UPDATECHECK_PHP_VERSION'); ?></dt>
                        <dd><?php echo htmlspecialchars((string) $update->phpversion, ENT_QUOTES, 'UTF-8'); ?></dd>

                        <dt><?php echo Text::_('COM_JEM_UPDATECHECK_LOCAL_MANIFEST'); ?></dt>
                        <dd><?php echo htmlspecialchars((string) $update->manifestpath, ENT_QUOTES, 'UTF-8'); ?></dd>
                    </dl>
                </section>

                <section class="jem-updatecheck-card">
                    <h3><?php echo Text::_('COM_JEM_UPDATECHECK_SERVER_RELEASE'); ?></h3>
                    <?php if ((int) $update->failed === 0 && $update->current !== null) : ?>
                        <dl class="jem-updatecheck-list">
                            <dt><?php echo Text::_('COM_JEM_UPDATECHECK_VERSION'); ?></dt>
                            <dd><?php echo htmlspecialchars((string) $update->versiondetail, ENT_QUOTES, 'UTF-8'); ?></dd>

                            <dt><?php echo Text::_('COM_JEM_UPDATECHECK_RELEASE_DATE'); ?></dt>
                            <dd><?php echo htmlspecialchars((string) $update->date, ENT_QUOTES, 'UTF-8'); ?></dd>

                            <dt><?php echo Text::_('COM_JEM_UPDATECHECK_TARGET_PLATFORM'); ?></dt>
                            <dd><?php echo htmlspecialchars((string) ($update->targetplatform ?: '-'), ENT_QUOTES, 'UTF-8'); ?></dd>

                            <dt><?php echo Text::_('COM_JEM_UPDATECHECK_PHP_MINIMUM'); ?></dt>
                            <dd><?php echo htmlspecialchars((string) ($update->phpminimum ?: '-'), ENT_QUOTES, 'UTF-8'); ?></dd>

                            <dt><?php echo Text::_('COM_JEM_UPDATECHECK_UPDATE_SOURCE'); ?></dt>
                            <dd><a href="<?php echo htmlspecialchars((string) $update->updateurl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars((string) $update->updateurl, ENT_QUOTES, 'UTF-8'); ?></a></dd>
                        </dl>
                    <?php else : ?>
                        <div class="p-3">
                            <p class="mb-0"><?php echo htmlspecialchars($statusText, ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    <?php endif; ?>
                </section>
            </div>

            <section class="jem-updatecheck-card">
                <h3><?php echo htmlspecialchars($notesTitle, ENT_QUOTES, 'UTF-8'); ?></h3>
                <dl class="jem-updatecheck-list">
                    <dt><?php echo Text::_('COM_JEM_UPDATECHECK_RELEASE_DATE'); ?></dt>
                    <dd><?php echo htmlspecialchars((string) ($notesDate ?: '-'), ENT_QUOTES, 'UTF-8'); ?></dd>

                    <dt><?php echo Text::_('COM_JEM_UPDATECHECK_NOTES'); ?></dt>
                    <dd>
                        <?php if (!empty($notes)) : ?>
                            <ul>
                                <?php foreach ($notes as $note) : ?>
                                    <?php $note = trim((string) $note); ?>
                                    <?php if ($note !== '') : ?>
                                        <li><?php echo htmlspecialchars($note, ENT_QUOTES, 'UTF-8'); ?></li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                        <?php else : ?>
                            <span class="jem-updatecheck-muted">-</span>
                        <?php endif; ?>
                        <?php if ($update->info !== '') : ?>
                            <div class="mt-2">
                                <a href="<?php echo htmlspecialchars((string) $update->info, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer"><?php echo Text::_('COM_JEM_UPDATECHECK_CHANGELOG'); ?></a>
                            </div>
                        <?php endif; ?>
                    </dd>
                </dl>
            </section>

            <section class="jem-updatecheck-card">
                <h3><?php echo Text::_('COM_JEM_UPDATECHECK_INFORMATION'); ?></h3>
                <div class="jem-updatecheck-actions">
                    <a class="btn btn-primary" href="https://www.joomlaeventmanager.net/" target="_blank" rel="noopener noreferrer"><?php echo Text::_('COM_JEM_UPDATECHECK_VISIT_WEBSITE'); ?></a>
                    <a class="btn btn-secondary" href="https://www.joomlaeventmanager.net/forum" target="_blank" rel="noopener noreferrer"><?php echo Text::_('COM_JEM_UPDATECHECK_VISIT_FORUM'); ?></a>
                    <a class="btn btn-secondary" href="https://www.joomlaeventmanager.net/documentation" target="_blank" rel="noopener noreferrer"><?php echo Text::_('COM_JEM_UPDATECHECK_VISIT_DOCUMENTATION'); ?></a>
                    <a class="btn btn-secondary" href="https://github.com/jemproject/JEM-Project/issues" target="_blank" rel="noopener noreferrer"><?php echo Text::_('COM_JEM_UPDATECHECK_REPORT_GITHUB'); ?></a>
                    <?php if ($update->download !== '') : ?>
                        <a class="btn btn-secondary" href="<?php echo htmlspecialchars((string) $update->download, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer"><?php echo Text::_('COM_JEM_UPDATECHECK_DOWNLOAD'); ?></a>
                    <?php endif; ?>
                    <?php if ((int) $update->current === -1) : ?>
                        <a class="btn btn-success" href="<?php echo Route::_('index.php?option=com_installer&view=update&filter[search]=JEM', false); ?>" target="_blank" rel="noopener noreferrer"><?php echo Text::_('COM_JEM_UPDATECHECK_UPDATE'); ?></a>
                    <?php endif; ?>
                </div>
            </section>
        </div>

        <br>
        <?php if (isset($this->sidebar)) : ?>
    </div>
        <?php endif; ?>

    <input type="hidden" name="task" value="" />
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
