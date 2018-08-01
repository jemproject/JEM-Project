<?php
/**
 * @version 2.2.2
 * @package JEM
 * @copyright (C) 2013-2017 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;

$options = array(
    'onActive' => 'function(title, description){
        description.setStyle("display", "block");
        title.addClass("open").removeClass("closed");
    }',
    'onBackground' => 'function(title, description){
        description.setStyle("display", "none");
        title.addClass("closed").removeClass("open");
    }',
	'opacityTransition' => true,
    'startOffset' => 0,  // 0 starts on the first tab, 1 starts the second, etc...
    'useCookie' => true, // this must not be a string. Don't use quotes.
);
?>
<form action="<?php echo JRoute::_('index.php?option=com_jem&view=help'); ?>" method="post" name="adminForm" id="adminForm">
	<?php if (isset($this->sidebar)) : ?>
	<div id="j-sidebar-container" class="span2">
		<?php echo $this->sidebar; ?>
	</div>
	<div id="j-main-container" class="span10">
	<?php endif; ?>
		<table border="1" class="adminform">
			<tr>
				<td colspan="2">
					<table style="width:100%">
						<tr>
							<td>
								<strong><?php echo JText::_('COM_JEM_SEARCH'); ?></strong>
								<input class="text_area" type="hidden" name="option" value="com_jem" />
								<input type="text" name="filter_search" id="filter_search" value="<?php echo $this->helpsearch;?>" class="inputbox" />
								<input type="submit" value="<?php echo JText::_('JSEARCH_FILTER_SUBMIT'); ?>" class="button" />
								<input type="button" value="<?php echo JText::_('JSEARCH_FILTER_CLEAR'); ?>" class="button" onclick="f=document.adminForm;f.filter_search.value='';f.submit()" />
							</td>
							<td style="text-align:right">
								<a href="<?php echo 'components/com_jem/help/'.$this->langTag.'/intro.html'; ?>" target='helpFrame'><?php echo JText::_('COM_JEM_HOME'); ?></a>
								|
								<a href="<?php echo 'components/com_jem/help/'.$this->langTag.'/helpsite/gethelp.html'; ?>" target='helpFrame'><?php echo JText::_('COM_JEM_GET_HELP'); ?></a>
								|
								<a href="<?php echo 'components/com_jem/help/'.$this->langTag.'/helpsite/givehelp.html'; ?>" target='helpFrame'><?php echo JText::_('COM_JEM_GIVE_HELP'); ?></a>
								|
								<a href="<?php echo 'components/com_jem/help/'.$this->langTag.'/helpsite/credits.html'; ?>" target='helpFrame'><?php echo JText::_('COM_JEM_CREDITS'); ?></a>
								|
								<?php echo JHtml::_('link', 'https://www.gnu.org/licenses/gpl-2.0.html', JText::_('COM_JEM_LICENSE'), array('target' => 'helpFrame')) ?>
							</td>
						</tr>
					</table>
				</td>
			</tr>
		</table>
		<div class="clr"> </div>
		<div id="treecellhelp" class="width-20 fltleft">
			<?php echo JHtml::_('sliders.start', 'det-pane', $options); ?>
			<?php echo JHtml::_('sliders.panel', JText::_('COM_JEM_SCREEN_HELP'), 'help'); ?>
				<table class="adminlist">
					<?php
					foreach ($this->toc as $k=>$v) {
						echo '<tr>';
						echo '<td>';
						echo JHtml::Link('components/com_jem/help/'.$this->langTag.'/'.$k, $v, array('target' => 'helpFrame'));
						echo '</td>';
						echo '</tr>';
					}
					?>
				</table>
			<?php echo JHtml::_('sliders.end');?>
		</div>
		<div id="datacellhelp" class="width-80 fltrt">
			<fieldset title="<?php echo JText::_('COM_JEM_HELP_VIEW'); ?>">
				<legend>
					<?php echo JText::_('COM_JEM_HELP_VIEW'); ?>
				</legend>
					<iframe name="helpFrame" src="<?php echo 'components/com_jem/help/'.$this->langTag.'/intro.html'; ?>" class="helpFrame"></iframe>
			</fieldset>
		</div>
	<?php if (isset($this->sidebar)) : ?>
	</div>
	<?php endif; ?>
	<input type="hidden" name="option" value="com_jem" />
	<input type="hidden" name="view" value="help" />
	<input type="hidden" name="task" value="" />
</form>

<?php
//keep session alive
JHtml::_('behavior.keepalive');
?>