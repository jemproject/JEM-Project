<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
?>

<form action="<?php echo Route::_('index.php?option=com_jem&view=updatecheck'); ?>" method="post" name="adminForm" id="adminForm">
	<?php if (isset($this->sidebar)) : ?>
	<div id="j-sidebar-container" class="span2">
		<?php echo $this->sidebar; ?>
	</div>
	<div id="j-main-container" class="span10">
	<?php endif; ?>

		<?php if ($this->updatedata->failed == 0) : ?>
		<div class="update-info">
				<?php
					if ($this->updatedata->current == 0 ) {
						echo HTMLHelper::_('image', 'com_jem/icon-48-latest-version.png', NULL, NULL, true);
					} elseif( $this->updatedata->current == -1 ) {
						echo HTMLHelper::_('image', 'com_jem/icon-48-update.png', NULL, NULL, true);
					} else {
						echo HTMLHelper::_('image', 'com_jem/icon-48-unknown-version.png', NULL, NULL, true);
					}
				?>
				<?php
					if ($this->updatedata->current == 0) {
						echo '<p style="color:green;font-weight: bold;">'.Text::_('COM_JEM_UPDATECHECK_LATEST_VERSION').'</p>';
					} elseif( $this->updatedata->current == -1 ) {
						echo '<p style="color:red;font-weight: bold;">'.Text::_('COM_JEM_UPDATECHECK_OLD_VERSION').'</p>';
					} else {
						echo '<p style="color:orange;font-weight: bold;">'.Text::_('COM_JEM_UPDATECHECK_NEWER_VERSION').'</p>';
					}
				?>
		</div>

	    <div class="update-details">
			<div class="detail-item">
				<strong><?php echo Text::_('COM_JEM_UPDATECHECK_VERSION').':'; ?></strong>
            	<span><?php echo $this->updatedata->versiondetail; ?></span>
	        </div>
			<div class="detail-item">
				<strong><?php echo Text::_('COM_JEM_UPDATECHECK_INSTALLED_VERSION').':'; ?></strong>
            	<span><?php echo $this->updatedata->installedversion; ?></span>
	        </div>
			<div class="detail-item">
				<strong><?php echo Text::_('COM_JEM_UPDATECHECK_RELEASE_DATE').':'; ?></strong>
            	<span><?php echo $this->updatedata->date; ?></span>
	        </div>
			<div class="detail-item">
				<strong><?php echo Text::_('COM_JEM_UPDATECHECK_CHANGES').':'; ?></strong>
            	<span>
            		<ul><?php
					foreach ($this->updatedata->changes as $change) {
						echo '<li>'.$change.'</li>';
						} ?>
					</ul>
					<a href="<?php echo $this->updatedata->info; ?>" target="_blank"><?php echo Text::_('COM_JEM_UPDATECHECK_CHANGELOG'); ?></a></span>
        	</div>
        	<div class="detail-item">
				<strong><?php echo Text::_('COM_JEM_UPDATECHECK_INFORMATION').':'; ?></strong>
            	<span>Visit the JEM Website: <a href="https://www.joomlaeventmanager.net/" target="_blank">www.joomlaeventmanager.net</a></span>
	        </div>
			<div class="detail-item">
				<strong><?php echo Text::_('COM_JEM_UPDATECHECK_FILES').':'; ?></strong>
      	      <span><a href="<?php echo $this->updatedata->download; ?>" target="_blank"><?php echo Text::_('COM_JEM_UPDATECHECK_DOWNLOAD'); ?></a></span>
			</div>
			<div class="detail-item">
				<strong><?php echo Text::_('COM_JEM_UPDATECHECK_NOTES').':'; ?></strong>
    	        <span><?php echo $this->updatedata->notes; ?></span>
    	    </div>
    	</div>

		<?php else : ?>

		<table class="updatecheck">
			<tr>
		  		<td>
		  		<?php
		  			echo HTMLHelper::_('image', 'com_jem/icon-48-update.png', NULL, NULL, true);
		  		?>
		  		</td>
		  		<td>
		  		<?php
		  			echo '<strong style="color:red">'.Text::_('COM_JEM_UPDATECHECK_CONNECTION_FAILED').'</strong>';
		  		?></span>
        </div>
		</table>
		<?php endif; ?>

		<br />
	<?php if (isset($this->sidebar)) : ?>
	</div>
	<?php endif; ?>

	<input type="hidden" name="task" value="" />
	<?php echo HTMLHelper::_('form.token'); ?>
</form>
