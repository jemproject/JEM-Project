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

$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn = $this->escape($this->state->get('list.direction'));
$workflowLabels = array(
    'registration' => Text::_('COM_JEM_NOTIFICATION_WORKFLOW_REGISTRATION'),
    'waiting_list' => Text::_('COM_JEM_NOTIFICATION_WORKFLOW_WAITING_LIST'),
    'attendance_status' => Text::_('COM_JEM_NOTIFICATION_WORKFLOW_ATTENDANCE_STATUS'),
    'invitation' => Text::_('COM_JEM_NOTIFICATION_WORKFLOW_INVITATION'),
    'cancellation' => Text::_('COM_JEM_NOTIFICATION_WORKFLOW_CANCELLATION'),
    'waiting_list_change' => Text::_('COM_JEM_NOTIFICATION_WORKFLOW_WAITING_LIST_CHANGE'),
    'reminder' => Text::_('COM_JEM_NOTIFICATION_WORKFLOW_REMINDER'),
);
$recipientLabels = array(
    'user' => Text::_('COM_JEM_NOTIFICATION_RECIPIENT_USER'),
    'admin' => Text::_('COM_JEM_NOTIFICATION_RECIPIENT_ADMIN'),
);
$variantLabels = array(
    'self' => Text::_('COM_JEM_NOTIFICATION_VARIANT_SELF'),
    'on_behalf' => Text::_('COM_JEM_NOTIFICATION_VARIANT_ON_BEHALF'),
    'scheduled' => Text::_('COM_JEM_NOTIFICATION_VARIANT_SCHEDULED'),
);
?>

<form action="<?php echo Route::_('index.php?option=com_jem&view=notifications'); ?>" method="post" name="adminForm" id="adminForm">
    <div id="j-main-container" class="j-main-container">
        <?php
        $sharedLanguage = (string) $this->state->get('filter_language');
        if (!JemNotificationTemplateService::hasMailerLanguage($sharedLanguage)) {
            $sharedLanguage = '';
        }
        ?>
        <ul class="nav nav-tabs mb-4" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link active" aria-current="page" href="<?php echo Route::_('index.php?option=com_jem&view=notifications'); ?>">
                    <?php echo Text::_('COM_JEM_NOTIFICATION_TAB_TEMPLATES'); ?>
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" href="<?php echo Route::_('index.php?option=com_jem&view=notificationcontent&section=footer&language=' . rawurlencode($sharedLanguage)); ?>">
                    <?php echo Text::_('COM_JEM_NOTIFICATION_TAB_FOOTER'); ?>
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" href="<?php echo Route::_('index.php?option=com_jem&view=notificationcontent&section=disclaimer&language=' . rawurlencode($sharedLanguage)); ?>">
                    <?php echo Text::_('COM_JEM_NOTIFICATION_TAB_DISCLAIMER'); ?>
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" href="<?php echo Route::_('index.php?option=com_jem&view=reminders'); ?>">
                    <?php echo Text::_('COM_JEM_NOTIFICATION_TAB_REMINDERS'); ?>
                </a>
            </li>
            <?php if (JemHelperBackend::canManage('jem.notifications.history')) : ?>
                <li class="nav-item" role="presentation">
                    <a class="nav-link" href="<?php echo Route::_('index.php?option=com_jem&view=notificationhistory'); ?>">
                        <?php echo Text::_('COM_JEM_NOTIFICATION_TAB_HISTORY'); ?>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
        <div class="alert alert-info">
            <?php echo Text::_('COM_JEM_NOTIFICATION_TEMPLATES_INTRO'); ?>
        </div>

        <fieldset id="filter-bar" class="mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-lg-4 col-md-6">
                    <label class="form-label" for="filter_search"><?php echo Text::_('JSEARCH_FILTER'); ?></label>
                    <div class="input-group">
                        <input type="text" name="filter_search" id="filter_search" class="form-control"
                               placeholder="<?php echo Text::_('COM_JEM_NOTIFICATION_TEMPLATE_SEARCH_HINT'); ?>"
                               value="<?php echo $this->escape($this->state->get('filter_search')); ?>" />
                        <button type="submit" class="btn btn-primary" title="<?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?>">
                            <span class="icon-search" aria-hidden="true"></span>
                        </button>
                    </div>
                </div>
                <div class="col-lg-2 col-md-3">
                    <label class="form-label" for="filter_language"><?php echo Text::_('JFIELD_LANGUAGE_LABEL'); ?></label>
                    <select name="filter_language" id="filter_language" class="form-select" onchange="this.form.submit()">
                        <?php foreach ($this->languages as $language) : ?>
                            <?php $label = $language->title_native ?: $language->title; ?>
                            <option value="<?php echo $this->escape($language->lang_code); ?>"
                                <?php echo $this->state->get('filter_language') === $language->lang_code ? 'selected' : ''; ?>
                                <?php echo empty($language->jem_available) ? 'disabled' : ''; ?>>
                                <?php echo $this->escape(
                                    $label . ' (' . $language->lang_code . ')'
                                    . (empty($language->jem_available) ? ' — ' . Text::_('COM_JEM_NOTIFICATION_LANGUAGE_UNAVAILABLE') : '')
                                ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-3">
                    <label class="form-label" for="filter_workflow"><?php echo Text::_('COM_JEM_NOTIFICATION_TEMPLATE_WORKFLOW'); ?></label>
                    <select name="filter_workflow" id="filter_workflow" class="form-select" onchange="this.form.submit()">
                        <option value=""><?php echo Text::_('JALL'); ?></option>
                        <?php foreach ($workflowLabels as $value => $label) : ?>
                            <option value="<?php echo $this->escape($value); ?>" <?php echo $this->state->get('filter_workflow') === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-3">
                    <label class="form-label" for="filter_recipient"><?php echo Text::_('COM_JEM_NOTIFICATION_TEMPLATE_RECIPIENT'); ?></label>
                    <select name="filter_recipient" id="filter_recipient" class="form-select" onchange="this.form.submit()">
                        <option value=""><?php echo Text::_('JALL'); ?></option>
                        <?php foreach ($recipientLabels as $value => $label) : ?>
                            <option value="<?php echo $this->escape($value); ?>" <?php echo $this->state->get('filter_recipient') === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-3">
                    <label class="form-label" for="filter_customized"><?php echo Text::_('COM_JEM_NOTIFICATION_TEMPLATE_SOURCE'); ?></label>
                    <select name="filter_customized" id="filter_customized" class="form-select" onchange="this.form.submit()">
                        <option value=""><?php echo Text::_('JALL'); ?></option>
                        <option value="0" <?php echo $this->state->get('filter_customized') === '0' ? 'selected' : ''; ?>><?php echo Text::_('COM_JEM_NOTIFICATION_TEMPLATE_DEFAULT'); ?></option>
                        <option value="1" <?php echo $this->state->get('filter_customized') === '1' ? 'selected' : ''; ?>><?php echo Text::_('COM_JEM_NOTIFICATION_TEMPLATE_CUSTOM'); ?></option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><?php echo Text::_('JFILTER'); ?></button>
                    <button type="button" class="btn btn-secondary"
                            onclick="['filter_search','filter_workflow','filter_recipient','filter_customized'].forEach(function(id){document.getElementById(id).value='';});this.form.submit();">
                        <?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?>
                    </button>
                </div>
                <div class="col-lg-2 col-md-3 ms-auto">
                    <?php echo $this->pagination->getLimitBox(); ?>
                </div>
            </div>
        </fieldset>

        <div class="table-responsive">
            <table class="table table-striped itemList" id="notificationTemplateList">
                <thead>
                    <tr>
                        <th><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_NOTIFICATION_TEMPLATE_IDENTIFIER', 'a.template_id', $listDirn, $listOrder); ?></th>
                        <th><?php echo Text::_('COM_JEM_NOTIFICATION_TEMPLATE_WORKFLOW'); ?></th>
                        <th><?php echo Text::_('COM_JEM_NOTIFICATION_TEMPLATE_RECIPIENT'); ?></th>
                        <th><?php echo Text::_('COM_JEM_NOTIFICATION_TEMPLATE_VARIANT'); ?></th>
                        <th><?php echo Text::_('COM_JEM_NOTIFICATION_TEMPLATE_SUBJECT'); ?></th>
                        <th><?php echo Text::_('COM_JEM_NOTIFICATION_TEMPLATE_SOURCE'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$this->items) : ?>
                    <tr><td colspan="6" class="text-center"><?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?></td></tr>
                <?php endif; ?>
                <?php foreach ($this->items as $item) : ?>
                    <?php
                    $editUrl = Route::_(
                        'index.php?option=com_jem&view=notificationtemplate&template_id=' . rawurlencode($item->template_id)
                        . '&language=' . rawurlencode($item->language)
                    );
                    $variant = $variantLabels[$item->variant] ?? $item->variant;
                    if ($item->with_comment) {
                        $variant .= ' / ' . Text::_('COM_JEM_NOTIFICATION_VARIANT_WITH_COMMENT');
                    }
                    ?>
                    <tr>
                        <td>
                            <a href="<?php echo $editUrl; ?>"><code><?php echo $this->escape($item->template_id); ?></code></a>
                            <br><small class="text-muted"><code><?php echo $this->escape($item->body_key); ?></code></small>
                        </td>
                        <td><?php echo $workflowLabels[$item->workflow] ?? $this->escape($item->workflow); ?></td>
                        <td><?php echo $recipientLabels[$item->recipient] ?? $this->escape($item->recipient); ?></td>
                        <td><?php echo $this->escape($variant); ?></td>
                        <td><?php echo $this->escape($item->display_subject); ?></td>
                        <td>
                            <?php if ($item->customized) : ?>
                                <span class="badge bg-success"><?php echo Text::_('COM_JEM_NOTIFICATION_TEMPLATE_CUSTOM'); ?></span>
                            <?php else : ?>
                                <span class="badge bg-secondary"><?php echo Text::_('COM_JEM_NOTIFICATION_TEMPLATE_DEFAULT'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php echo $this->pagination->getListFooter(); ?>
        <input type="hidden" name="task" value="" />
        <input type="hidden" name="boxchecked" value="0" />
        <input type="hidden" name="filter_order" value="<?php echo $listOrder; ?>" />
        <input type="hidden" name="filter_order_Dir" value="<?php echo $listDirn; ?>" />
        <?php echo HTMLHelper::_('form.token'); ?>
    </div>
</form>
