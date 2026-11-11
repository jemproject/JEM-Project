<?php
/** @package JEM @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL */
defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$data = $this->invitationData;
$form = $data['form'];
$escape = static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$label = static fn ($key) => $escape(Text::_('COM_JEM_INV_' . $key));
$selected = static fn ($value, $current) => (string) $value === (string) $current ? ' selected' : '';
?>
<div class="jem-invitations">
    <h1><?php echo $label('TITLE'); ?></h1>
    <p><?php echo $label('INTRO'); ?></p>
    <form method="get" action="<?php echo $escape(Route::_('index.php')); ?>">
        <input type="hidden" name="option" value="com_jem"><input type="hidden" name="view" value="invitations">
        <label for="inv-event"><?php echo $label('EVENT'); ?></label>
        <select id="inv-event" name="event_id" class="form-select">
            <option value="0"><?php echo $label('SELECT_EVENT'); ?></option>
            <?php foreach ($data['events'] as $event) : ?>
                <option value="<?php echo (int) $event->id; ?>"<?php echo $selected($event->id, $form['event_id'] ?? 0); ?>><?php echo $escape($event->title . ' — ' . $event->dates); ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-secondary" type="submit"><?php echo $label('OPEN'); ?></button>
    </form>
    <?php if ($data['event']) : ?>
        <div class="alert alert-info jem-inv-pool">
            <?php foreach ($data['pool'] as $name => $quantity) : ?>
                <span><?php echo $label('POOL_' . strtoupper($name)); ?>: <strong><?php echo (int) $quantity; ?></strong></span>
            <?php endforeach; ?>
        </div>
        <form method="post" action="<?php echo $escape(Route::_('index.php?option=com_jem')); ?>" id="jem-invitation-form">
            <input type="hidden" name="invitation[event_id]" value="<?php echo (int) $data['event']->id; ?>">
            <fieldset><legend><?php echo $label('SCOPE'); ?></legend>
                <select name="invitation[scope]" class="form-select">
                    <option value="single"<?php echo $selected('single', $form['scope'] ?? 'single'); ?>><?php echo $label('SINGLE'); ?></option>
                    <option value="series"<?php echo $selected('series', $form['scope'] ?? 'single'); ?>><?php echo $label('SERIES'); ?></option>
                </select>
            </fieldset>
            <fieldset><legend><?php echo $label('RECIPIENTS'); ?></legend>
                <label for="inv-audience"><?php echo $label('AUDIENCE'); ?></label>
                <select id="inv-audience" name="invitation[audience]" class="form-select">
                    <?php foreach ($data['groups'] ? ['users', 'groups', 'all'] : ['users'] as $option) : ?>
                        <option value="<?php echo $option; ?>"<?php echo $selected($option, $form['audience'] ?? 'users'); ?>><?php echo $label(strtoupper($option)); ?></option>
                    <?php endforeach; ?>
                </select>
                <div data-inv-audience="users"><label for="inv-users"><?php echo $label('USERS'); ?></label>
                    <select id="inv-users" name="invitation[users][]" multiple class="form-select" size="6">
                        <?php foreach ($data['users'] as $user) : ?>
                            <option value="<?php echo (int) $user->id; ?>"<?php echo in_array((string) $user->id, array_map('strval', (array) ($form['users'] ?? [])), true) ? ' selected' : ''; ?>><?php echo $escape($user->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div data-inv-audience="groups"><label for="inv-groups"><?php echo $label('GROUPS'); ?></label>
                    <select id="inv-groups" name="invitation[groups][]" multiple class="form-select" size="6">
                        <?php foreach ($data['groups'] as $group) : ?>
                            <option value="<?php echo (int) $group->id; ?>"<?php echo in_array((string) $group->id, array_map('strval', (array) ($form['groups'] ?? [])), true) ? ' selected' : ''; ?>><?php echo $escape($group->title); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small><?php echo $label('GROUPS_HINT'); ?></small>
                </div>
                <label for="inv-external"><?php echo $label('EXTERNAL'); ?></label>
                <textarea id="inv-external" name="invitation[external]" rows="4" class="form-control" placeholder="Ana Smith; ana@example.org"><?php echo $escape($form['external'] ?? ''); ?></textarea>
                <small><?php echo $label('EXTERNAL_HINT'); ?></small>
            </fieldset>
            <fieldset><legend><?php echo $label('PLACES'); ?></legend>
                <label for="inv-kind"><?php echo $label('KIND'); ?></label>
                <select id="inv-kind" name="invitation[kind]" class="form-select">
                    <option value="reserved"<?php echo $selected('reserved', $form['kind'] ?? 'reserved'); ?>><?php echo $label('RESERVED'); ?></option>
                    <option value="open"<?php echo $selected('open', $form['kind'] ?? 'reserved'); ?>><?php echo $label('OPEN_INVITATION'); ?></option>
                </select>
                <label for="inv-quantity"><?php echo $label('QUANTITY'); ?></label>
                <input id="inv-quantity" name="invitation[quantity]" class="form-control" type="number" min="1" required value="<?php echo (int) ($form['quantity'] ?? 1); ?>">
                <?php if ($data['prices']) : ?>
                    <label for="inv-price"><?php echo $label('PRICE'); ?></label>
                    <select id="inv-price" name="invitation[price]" class="form-select">
                        <option value=""><?php echo $label('SELECT_PRICE'); ?></option>
                        <?php foreach ($data['prices'] as $price) : ?>
                            <option value="<?php echo $escape($price->code); ?>"<?php echo $selected($price->code, $form['price'] ?? ''); ?>><?php echo $escape($price->name . ' — ' . $price->amount . ' ' . $data['event']->currency); ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php elseif ($data['areas']) : ?>
                    <label for="inv-area"><?php echo $label('AREA'); ?></label>
                    <select id="inv-area" name="invitation[area]" class="form-select">
                        <option value=""><?php echo $label('SELECT_AREA'); ?></option>
                        <?php foreach ($data['areas'] as $area) : ?>
                            <option value="<?php echo $escape($area['key']); ?>"<?php echo $selected($area['key'], $form['area'] ?? ''); ?>><?php echo $escape($area['space_name'] . ' / ' . $area['area_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
                <?php if ($data['canFree']) : ?>
                    <label><input type="checkbox" name="invitation[free]" value="1"<?php echo !empty($form['free']) ? ' checked' : ''; ?>> <?php echo $label('FREE'); ?></label>
                    <small><?php echo $label('FREE_HINT'); ?></small>
                <?php endif; ?>
                <label for="inv-expires"><?php echo $label('EXPIRES'); ?></label>
                <input id="inv-expires" type="date" name="invitation[expires]" class="form-control" value="<?php echo $escape($form['expires'] ?? ''); ?>">
            </fieldset>
            <fieldset><legend><?php echo $label('MESSAGE'); ?></legend>
                <textarea name="invitation[message]" rows="5" maxlength="4000" class="form-control"><?php echo $escape($form['message'] ?? ''); ?></textarea>
                <small><?php echo $label('MESSAGE_HINT'); ?></small>
            </fieldset>
            <?php if ($data['report']) : $report = $data['report']; ?>
                <section class="alert alert-info" aria-live="polite"><h2><?php echo $label('SUMMARY'); ?></h2>
                    <p><?php echo $escape(Text::sprintf('COM_JEM_INV_SUMMARY_COUNTS', $report['recipients'], $report['dates'], $report['places'])); ?></p>
                    <ul><?php foreach ($report['excluded'] as $excluded) : ?><li><?php echo $escape($excluded['name'] . ': ' . Text::_($excluded['reason'])); ?></li><?php endforeach; ?></ul>
                    <?php if (!empty($report['rows'][0])) : ?>
                        <p><?php echo $escape(Text::sprintf('COM_JEM_INV_PREVIEW_GREETING', $report['rows'][0]['name'])); ?></p>
                        <ul><?php foreach ($report['rows'][0]['sessions'] as $session) : ?>
                            <li><?php echo $escape($session['title'] . ' — ' . $session['date']); ?>
                                <?php if (($form['kind'] ?? '') === 'reserved') : ?>
                                    — <?php echo $escape($session['amount'] . ' ' . $session['currency']); ?>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?></ul>
                        <p><?php echo nl2br($escape($form['message'] ?? '')); ?></p>
                        <p><?php echo $label(($form['kind'] ?? '') === 'reserved' ? 'GUARANTEED_NOTICE' : 'OPEN_NOTICE'); ?></p>
                        <p><?php echo $label(!empty($form['free']) ? 'FREE_NOTICE' : 'PRICE_NOTICE'); ?></p>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
            <div class="jem-inv-actions">
                <a class="btn btn-secondary" href="<?php echo $escape(Route::_('index.php?option=com_jem&view=registrationstatus')); ?>"><?php echo $label('BACK'); ?></a>
                <button class="btn btn-secondary" type="submit" name="task" value="invitations.preview"><?php echo $label('PREVIEW'); ?></button>
                <button class="btn btn-primary" type="submit" name="task" value="invitations.send" data-inv-send<?php echo !$data['reviewed'] ? ' disabled' : ''; ?>><?php echo $label('SEND'); ?></button>
            </div>
            <?php echo HTMLHelper::_('form.token'); ?>
        </form>
        <h2><?php echo $label('EXISTING'); ?></h2>
        <div class="table-responsive"><table class="table"><thead><tr>
            <?php foreach (['RECIPIENTS', 'KIND', 'PLACES', 'STATUS', 'ACTIONS'] as $column) : ?><th><?php echo $label($column); ?></th><?php endforeach; ?>
        </tr></thead><tbody>
        <?php foreach ($data['rows'] as $row) : ?>
            <tr><td><?php echo $escape($row->holder_name); ?><br><small><?php echo $escape($row->holder_email); ?></small></td>
                <td><?php echo $label($row->invitation_kind === 'reserved' ? 'RESERVED' : 'OPEN_INVITATION'); ?></td>
                <td><?php echo (int) $row->invitation_quantity; ?></td>
                <td><?php echo $label($row->invitation_revoked ? 'REVOKED' : ((int) $row->status === 0 ? (JemInvitationPolicy::expired($row, gmdate('Y-m-d H:i:s')) ? 'EXPIRED' : 'PENDING')
                    : ((int) $row->status < 0 ? 'DECLINED' : ($row->waiting ? 'WAITING' : 'ACCEPTED')))); ?></td>
                <td><form method="post" action="<?php echo $escape(Route::_('index.php?option=com_jem')); ?>">
                    <input type="hidden" name="id" value="<?php echo (int) $row->id; ?>"><input type="hidden" name="event_id" value="<?php echo (int) $row->event; ?>">
                    <?php if ((int) $row->status === 0 && !$row->invitation_revoked && !JemInvitationPolicy::expired($row, gmdate('Y-m-d H:i:s'))) : ?>
                        <button class="btn btn-sm btn-secondary" name="task" value="invitations.resend"><?php echo $label('RESEND'); ?></button>
                    <?php endif; ?>
                    <?php if (!$row->invitation_revoked) : ?><button class="btn btn-sm btn-warning" name="task" value="invitations.revoke"><?php echo $label('REVOKE'); ?></button><?php endif; ?>
                    <?php echo HTMLHelper::_('form.token'); ?>
                </form></td>
            </tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</div>
