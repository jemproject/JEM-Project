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
?>

<div class="p-3">
    <div class="row">
        <div class="form-group col-md-6">
            <div class="controls">
                <label id="batch-access-lbl" for="batch-access">
                    <?php echo Text::_('JLIB_HTML_BATCH_ACCESS_LABEL'); ?>
                </label>
                <?php echo HTMLHelper::_(
                    'access.assetgrouplist',
                    'batch[assetgroup_id]',
                    '',
                    'class="form-select"',
                    array(
                        'title' => Text::_('JLIB_HTML_BATCH_NOCHANGE'),
                        'id'    => 'batch-access',
                    )
                ); ?>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="form-group col-md-6">
            <div class="controls">
                <label id="batch-category-lbl" for="batch_category_id">
                    <?php echo Text::_('COM_JEM_EVENTS_MOVE_TO_CATEGORY'); ?>
                </label>
                <?php echo $this->lists['batch_category']; ?>
            </div>
        </div>
        <div class="form-group col-md-6">
            <div class="controls">
                <label id="batch-venue-lbl" for="batch_venue_id">
                    <?php echo Text::_('COM_JEM_EVENTS_MOVE_TO_VENUE'); ?>
                </label>
                <?php echo $this->lists['batch_venue']; ?>
            </div>
        </div>
        <div class="form-group col-md-6">
            <div class="controls">
                <label id="batch-type-lbl" for="batch_type_id">
                    <?php echo Text::_('COM_JEM_EVENTS_MOVE_TO_TYPE'); ?>
                </label>
                <?php echo $this->lists['batch_type']; ?>
            </div>
        </div>
    </div>
</div>
<div class="btn-toolbar p-3">
    <joomla-toolbar-button task="events.batch" class="ms-auto">
        <button type="button" class="btn btn-success"><?php echo Text::_('JGLOBAL_BATCH_PROCESS'); ?></button>
    </joomla-toolbar-button>
</div>
