<?php
/**
 * @package    JEM
 * @copyright  (C) 2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */
defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$base = 'index.php?option=com_jem&view=housekeeping&tool=attachmentrecovery';
?>
<div class="card"><div class="card-body">
    <h2><?php echo Text::_('COM_JEM_DELETED_RESOURCES_TITLE'); ?></h2>
    <p><?php echo Text::_('COM_JEM_DELETED_RESOURCES_DESC'); ?></p>
    <?php foreach ($this->cleanupRun as $object => $status) : ?>
        <p class="<?php echo $status === 'done' ? 'text-success' : 'text-warning'; ?>">
            <?php echo $escape($object) . ': ' . Text::_('COM_JEM_DELETED_RESOURCES_' . strtoupper($status)); ?>
        </p>
    <?php endforeach; ?>
    <?php if (!$this->cleanupEntries) : ?>
        <p><?php echo Text::_('COM_JEM_DELETED_RESOURCES_NONE'); ?></p>
    <?php else : ?>
        <form method="post" action="<?php echo Route::_('index.php?option=com_jem&task=housekeeping.recoverDeletedResources'); ?>"
              onsubmit="return confirm(<?php echo $escape(json_encode(Text::_('COM_JEM_DELETED_RESOURCES_CONFIRM'))); ?>);">
            <div class="table-responsive"><table class="table">
                <caption><?php echo Text::sprintf('COM_JEM_DELETED_RESOURCES_BATCH', JemDeletedObjectCleanup::BATCH_LIMIT); ?></caption>
                <thead><tr>
                    <th><?php echo Text::_('JSELECT'); ?></th>
                    <th><?php echo Text::_('COM_JEM_DELETED_RESOURCES_OWNER'); ?></th>
                    <th><?php echo Text::_('COM_JEM_ATTACHMENTS'); ?></th>
                    <th><?php echo Text::_('COM_JEM_DELETED_RESOURCES_LINKS'); ?></th>
                    <th><?php echo Text::_('JSTATUS'); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($this->cleanupEntries as $object => $entry) : ?>
                    <tr>
                        <td><input type="checkbox" name="objects[]" value="<?php echo $escape($object); ?>"
                            aria-label="<?php echo $escape($object); ?>" <?php echo $entry['status'] === 'ready' ? '' : 'disabled'; ?>></td>
                        <td><?php echo $escape($object); ?></td>
                        <td><?php echo count($entry['rows']); ?></td>
                        <td><?php echo count($entry['links']); ?></td>
                        <td><?php echo Text::_('COM_JEM_DELETED_RESOURCES_' . strtoupper($entry['status'])); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <button type="submit" class="btn btn-danger"><?php echo Text::_('COM_JEM_DELETED_RESOURCES_CLEAN'); ?></button>
            <input type="hidden" name="cleanup_nonce" value="<?php echo $escape($this->cleanupNonce); ?>">
            <?php echo HTMLHelper::_('form.token'); ?>
        </form>
    <?php endif; ?>
    <nav class="mt-3" aria-label="<?php echo Text::_('JPAGINATION'); ?>">
        <?php if ($this->cleanupPage > 1) : ?>
            <a class="btn btn-secondary" href="<?php echo Route::_($base . '&page=' . ($this->cleanupPage - 1)); ?>"><?php echo Text::_('JPREV'); ?></a>
        <?php endif; ?>
        <?php if ($this->cleanupMore) : ?>
            <a class="btn btn-secondary" href="<?php echo Route::_($base . '&page=' . ($this->cleanupPage + 1)); ?>"><?php echo Text::_('JNEXT'); ?></a>
        <?php endif; ?>
    </nav>
</div></div>
