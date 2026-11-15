<?php
/** Private breakdown of the signed-in user's current reservation. */
defined('_JEXEC') or die;
use Joomla\CMS\Language\Text;

if (empty($this->ownPlaceBreakdown) || empty($this->user->id)
    || (int) ($this->registration->uid ?? 0) !== (int) $this->user->id) {
    return;
}
$hasTypes = count(array_filter($this->ownPlaceBreakdown, static function ($row) {
    return $row['type'] !== '';
})) > 0;
?>
<div class="table-responsive jem-own-places">
    <table class="table table-sm">
        <caption class="caption-top"><?php echo Text::_('COM_JEM_YOUR_PLACES'); ?></caption>
        <thead><tr>
            <?php if ($hasTypes) : ?><th scope="col"><?php echo Text::_('COM_JEM_BOOKING_TICKET_TYPE'); ?></th><?php endif; ?>
            <th scope="col"><?php echo Text::_('COM_JEM_BOOKING_SPACE'); ?></th>
            <th scope="col"><?php echo Text::_('COM_JEM_RELOCATION_AREA'); ?></th>
            <th scope="col"><?php echo Text::_('COM_JEM_PLACES'); ?></th>
        </tr></thead>
        <tbody>
        <?php foreach ($this->ownPlaceBreakdown as $row) : ?>
            <tr>
                <?php if ($hasTypes) : ?><td><?php echo $this->escape($row['type']); ?></td><?php endif; ?>
                <td><?php echo $row['space'] !== '' ? $this->escape($row['space']) : '—'; ?></td>
                <td><?php echo $row['area'] !== '' ? $this->escape($row['area']) : '—'; ?></td>
                <td><?php echo (int) $row['quantity']; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
