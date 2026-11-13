<?php
/** @package JEM @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL */
defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$escape = static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div class="jem-invitation-portal">
    <h1><?php echo Text::_('COM_JEM_INV_YOUR_INVITATION'); ?></h1>
    <?php if ($this->invitationRows) : $first = $this->invitationRows[0]['registration']; ?>
        <?php if (!$this->accessDenied) : ?>
        <p><?php echo nl2br($escape($first->invitation_message)); ?></p>
        <p><?php echo Text::_($first->invitation_kind === 'reserved' ? 'COM_JEM_INV_GUARANTEED_NOTICE' : 'COM_JEM_INV_OPEN_NOTICE'); ?></p>
        <p><?php echo Text::_($first->invitation_free ? 'COM_JEM_INV_FREE_NOTICE' : 'COM_JEM_INV_PRICE_NOTICE'); ?></p>
        <?php if ($first->invitation_expires) : ?><p><?php echo Text::_('COM_JEM_INV_EXPIRES'); ?>: <?php echo $escape(HTMLHelper::_('date', $first->invitation_expires, 'Y-m-d H:i T')); ?></p><?php endif; ?>
        <ul>
        <?php foreach ($this->invitationRows as $item) : $row = $item['registration']; ?>
            <li><strong><?php echo $escape($item['event']->title) . JemOutput::eventStatusNotice($item['event']); ?></strong>
                — <?php echo $escape($item['event']->dates . ' ' . substr((string) $item['event']->times, 0, 5)); ?>
                — <?php echo (int) $row->invitation_quantity; ?> <?php echo Text::_('COM_JEM_INV_PLACES'); ?>
                <?php if (isset($row->preview_total)) : ?><p><?php echo Text::_('COM_JEM_TK_DUE'); ?>: <?php echo $escape($row->preview_total . ' ' . $row->preview_currency); ?></p><?php endif; ?>
                <?php if (!empty($row->preview_waiting)) : ?><p><?php echo Text::_('COM_JEM_INV_WAITING_NOTICE'); ?></p><?php endif; ?>
                <?php if ($this->answered) : ?>
                    <p><?php echo Text::_((int) $row->status < 0 ? 'COM_JEM_INV_DECLINED' : ($row->waiting ? 'COM_JEM_INV_WAITING' : 'COM_JEM_INV_ACCEPTED')); ?></p>
                    <?php if ((int) $row->status === 1 && !$row->waiting) : ?>
                        <?php if (JemOnlineMeeting::restricted($item['event']) && !empty($item['event']->online_meeting_url)
                            && (int) JemHelper::globalattribs()->get('event_show_online_meeting', 1) === 1) : ?>
                            <p><a href="<?php echo $escape(Route::_(JemOnlineMeeting::route((int) $row->event)
                                . ((int) $row->uid === 0 ? '&registration_id=' . (int) $row->id : ''))); ?>"><?php echo Text::_('COM_JEM_JOIN_ONLINE'); ?></a></p>
                        <?php endif; ?>
                        <form method="post" action="<?php echo $escape(Route::_('index.php?option=com_jem&task=invitations.download')); ?>">
                            <input type="hidden" name="id" value="<?php echo (int) $row->id; ?>">
                            <button class="btn btn-secondary"><?php echo Text::_('COM_JEM_TK_DOWNLOAD'); ?></button>
                            <?php echo HTMLHelper::_('form.token'); ?>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
        </ul>
        <?php endif; ?>
        <?php if (!$this->answered) : ?>
            <form method="post" action="<?php echo $escape(Route::_('index.php?option=com_jem&task=invitations.respond')); ?>">
                <input type="hidden" name="invitation_id" value="<?php echo (int) $this->invitationId; ?>">
                <input type="hidden" name="invitation_token" value="<?php echo $escape($this->invitationToken); ?>">
                <button name="answer" value="accept" class="btn btn-primary"<?php echo !$this->canAccept ? ' disabled' : ''; ?>><?php echo Text::_('COM_JEM_INV_ACCEPT'); ?></button>
                <button name="answer" value="decline" class="btn btn-secondary"><?php echo Text::_('COM_JEM_INV_DECLINE'); ?></button>
                <?php echo HTMLHelper::_('form.token'); ?>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</div>
