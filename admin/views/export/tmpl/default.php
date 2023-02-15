<?php
/**
 * @version 2.3.8
 * @package JEM
 * @copyright (C) 2013-2021 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

// JEMHelper::headerDeclarations();
?>
<script type="text/javascript">
    function selectAll()
    {
        selectBox = document.getElementById("cid");

        for (var i = 0; i < selectBox.options.length; i++){
             selectBox.options[i].selected = true;
        }
    }

    function unselectAll()
    {
        selectBox = document.getElementById("cid");

        for (var i = 0; i < selectBox.options.length; i++){
             selectBox.options[i].selected = false;
        }
    }
</script>

<div id="jem" class="jem_jem">
	<form action="index.php" method="post" name="adminForm" enctype="multipart/form-data" id="adminForm">
		<?php if (isset($this->sidebar)) : ?>
		<!-- <div id="j-sidebar-container" class="span2">
			<?php //echo $this->sidebar; ?>
		</div> -->
		<?php endif; ?>
		<div id="j-main-container" class="j-main-container">
			<div class="row">
				<div class="col-md-6">
					<fieldset class="adminform">
						<legend><?php echo Text::_('COM_JEM_EXPORT_EVENTS_LEGEND');?></legend>

						<ul class="adminformlist">
							<li>
								<label <?php echo JEMOutput::tooltip(Text::_('COM_JEM_EXPORT_ADD_CATEGORYCOLUMN'), Text::_('COM_JEM_EXPORT_ADD_CATEGORYCOLUMN'), 'editlinktip'); ?>>
								<?php echo Text::_('COM_JEM_EXPORT_ADD_CATEGORYCOLUMN'); ?></label>
								<?php
									$categorycolumn = array();
									$categorycolumn[] = HTMLHelper::_('select.option', '0', Text::_('JNO'));
									$categorycolumn[] = HTMLHelper::_('select.option', '1', Text::_('JYES'));
									$categorycolumn = HTMLHelper::_('select.genericlist', $categorycolumn, 'categorycolumn', array('size'=>'1','class'=>'inputbox form-select'), 'value', 'text', '1');
									echo $categorycolumn;?>
							</li>
							<li>
								<label for="dates"><?php echo Text::_('COM_JEM_EXPORT_FROM_DATE').':'; ?></label>
								<?php echo HTMLHelper::_('calendar', date("Y-m-d"), 'dates', 'dates', '%Y-%m-%d', array('class' => 'inputbox validate-date', 'showTime' => false)); ?>
							</li>
							<li>
								<label for="enddates"><?php echo Text::_('COM_JEM_EXPORT_TO_DATE').':'; ?></label>
								<?php echo HTMLHelper::_('calendar', date("Y-m-d"), 'enddates', 'enddates', '%Y-%m-%d', array('class' => 'inputbox validate-date', 'showTime' => false)); ?>
							</li>
							<li>
								<label for="cid"><?php echo Text::_('COM_JEM_CATEGORY').':'; ?></label>
								<?php echo $this->categories; ?>
								<input class="button" type="button" name="selectall" value="<?php echo Text::_('COM_JEM_EXPORT_SELECT_ALL_CATEGORIES'); ?>" onclick="selectAll();">
								<br />
								<input class="button" type="button" name="unselectall" value="<?php echo Text::_('COM_JEM_EXPORT_UNSELECT_ALL_CATEGORIES'); ?>" onclick="unselectAll();">
							</li>
							<li>
								<label></label>
								<input type="submit" id="csvexport" value="<?php echo Text::_('COM_JEM_EXPORT_FILE'); ?>" onclick="document.getElementsByName('task')[0].value='export.export';return true;"></input>
							</li>
						</ul>
					</fieldset>

					<div class="clr"></div>
				</div>

				<div class="col-md-6">
					<fieldset class="adminform">
						<legend><?php echo Text::_('COM_JEM_EXPORT_OTHER_LEGEND');?></legend>

						<ul class="adminformlist">
							<li>
								<label><?php echo Text::_('COM_JEM_EXPORT_CATEGORIES'); ?></label>
								<input type="submit" id="csvexport" value="<?php echo Text::_('COM_JEM_EXPORT_FILE'); ?>" onclick="document.getElementsByName('task')[0].value='export.exportcats';return true;"></input>
							</li>
							<li>
								<label><?php echo Text::_('COM_JEM_EXPORT_VENUES'); ?></label>
								<input type="submit" id="csvexport" value="<?php echo Text::_('COM_JEM_EXPORT_FILE'); ?>" onclick="document.getElementsByName('task')[0].value='export.exportvenues';return true;"></input>
							</li>
							<li>
								<label><?php echo Text::_('COM_JEM_EXPORT_CAT_EVENTS'); ?></label>
								<input type="submit" id="csvexport" value="<?php echo Text::_('COM_JEM_EXPORT_FILE'); ?>" onclick="document.getElementsByName('task')[0].value='export.exportcatevents';return true;"></input>
							</li>
						</ul>
					</fieldset>
					<div class="clr"></div>
				</div>
			</div>
		</div>
		<?php //if (isset($this->sidebar)) : ?>
		<?php //endif; ?>

		<?php echo HTMLHelper::_( 'form.token' ); ?>
		<input type="hidden" name="option" value="com_jem" />
		<input type="hidden" name="view" value="export" />
		<input type="hidden" name="controller" value="export" />
		<input type="hidden" name="task" value="" />
	</form>
</div>
