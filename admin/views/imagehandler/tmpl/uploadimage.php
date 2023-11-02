<?php
/**
 * @version    4.2.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
?>

<form method="post" action="<?php echo htmlspecialchars($this->request_url); ?>" enctype="multipart/form-data" name="adminForm" id="adminForm">

<table class="noshow">
	<tr>
		<td width="50%" valign="top">

			<?php if($this->ftp): ?>
				<fieldset class="adminform">
					<legend><?php echo Text::_('COM_JEM_FTP_TITLE'); ?></legend>

					<?php echo Text::_('COM_JEM_FTP_DESC'); ?>

					<?php if($this->ftp INSTANCEOF Exception): ?>
						<p><?php echo Text::_($this->ftp->message); ?></p>
					<?php endif; ?>

					<table class="adminform nospace">
						<tbody>
							<tr>
								<td width="120">
									<label for="username"><?php echo Text::_('COM_JEM_USERNAME'); ?>:</label>
								</td>
								<td>
									<input type="text" id="username" name="username" class="input_box" size="70" value="" />
								</td>
							</tr>
							<tr>
								<td width="120">
									<label for="password"><?php echo Text::_('COM_JEM_PASSWORD'); ?>:</label>
								</td>
								<td>
									<input type="password" id="password" name="password" class="input_box" size="70" value="" />
								</td>
							</tr>
						</tbody>
					</table>
				</fieldset>
			<?php endif; ?>

			<fieldset class="adminform">
				<legend><?php echo Text::_('COM_JEM_SELECT_IMAGE_UPLOAD'); ?></legend>
				<table class="admintable">
					<tbody>
						<tr>
							<td>
								<input class="inputbox" name="userfile" id="userfile" type="file" />
								<br /><br />
								<input class="btn btn-primary" type="submit" value="<?php echo Text::_('COM_JEM_UPLOAD') ?>" name="adminForm" />
							</td>
						</tr>
					</tbody>
				</table>
			</fieldset>

		</td>
		<td width="50%" valign="top">

			<fieldset class="adminform">
				<legend><?php echo Text::_('COM_JEM_ATTENTION'); ?></legend>
				<table class="admintable">
					<tbody>
						<tr>
							<td>
								<b><?php echo Text::_('COM_JEM_TARGET_DIRECTORY').':'; ?></b>
								<?php
								if($this->task == 'venueimg') {
									echo "/images/jem/venues/";
									$this->task = 'imagehandler.venueimgup';
								} else if($this->task == 'eventimg') {
									echo "/images/jem/events/";
									$this->task = 'imagehandler.eventimgup';
								} else if($this->task == 'categoriesimg') {
									echo "/images/jem/categories/";
									$this->task = 'imagehandler.categoriesimgup';
								}
								?>
								<br />
								<b><?php echo Text::_('COM_JEM_IMAGE_FILESIZE').':'; ?></b> <?php echo $this->jemsettings->sizelimit; ?> kb<br />

								<?php
								if($this->jemsettings->gddisabled == 0 || (imagetypes() & IMG_PNG)) {
									echo "<br /><span style='color:green'>".Text::_('COM_JEM_PNG_SUPPORT')."</span>";
								} else {
									echo "<br /><span style='color:red'>".Text::_('COM_JEM_NO_PNG_SUPPORT')."</span>";
								}
								if($this->jemsettings->gddisabled == 0 || (imagetypes() & IMG_JPEG)) {
									echo "<br /><span style='color:green'>".Text::_('COM_JEM_JPG_SUPPORT')."</span>";
								} else {
									echo "<br /><span style='color:red'>".Text::_('COM_JEM_NO_JPG_SUPPORT')."</span>";
								}
								if($this->jemsettings->gddisabled == 0 || (imagetypes() & IMG_GIF)) {
									echo "<br /><span style='color:green'>".Text::_('COM_JEM_GIF_SUPPORT')."</span>";
								} else {
									echo "<br /><span style='color:red'>".Text::_('COM_JEM_NO_GIF_SUPPORT')."</span>";
								}
                                if($this->jemsettings->gddisabled == 0 || (imagetypes() & IMG_WEBP)) {
                                    echo "<br /><span style='color:green'>".Text::_('COM_JEM_WEBP_SUPPORT')."</span>";
                                } else {
                                    echo "<br /><span style='color:red'>".Text::_('COM_JEM_NO_WEBP_SUPPORT')."</span>";
                                }
								?>
							</td>
						</tr>
					</tbody>
				</table>
			</fieldset>

		</td>
	</tr>
</table>

<?php if($this->jemsettings->gddisabled) { ?>

<table class="noshow">
	<tr>
		<td>

			<fieldset class="adminform">
				<legend><?php echo Text::_('COM_JEM_ATTENTION'); ?></legend>
				<table class="admintable">
					<tbody>
						<tr>
							<td class="center">
								<?php echo Text::_('COM_JEM_GD_WARNING'); ?>
							</td>
						</tr>
					</tbody>
				</table>
			</fieldset>

		</td>
	</tr>
</table>

<?php } ?>

<?php echo JHtml::_('form.token'); ?>
<input type="hidden" name="option" value="com_jem" />
<input type="hidden" name="task" value="<?php echo $this->task;?>" />
</form>

<p class="copyright">
	<?php echo JEMAdmin::footer(); ?>
</p>
