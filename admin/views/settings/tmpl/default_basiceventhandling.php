<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;
?>

<div class="width-100">
	<fieldset class="adminform">
		<legend><?php echo JText::_( 'COM_JEM_EVENT_HANDLING' ); ?></legend>
		<ul class="adminformlist">
			<li><?php echo $this->form->getLabel('oldevent'); ?> <?php echo $this->form->getInput('oldevent'); ?>
				<span class="error hasTip" title="<?php echo JText::_( 'COM_JEM_WARNING' ); ?>::<?php echo JText::_( 'COM_JEM_OLD_EVENTS_WARN' ); ?>">
					<?php echo $this->WarningIcon(); ?>
				</span>
			</li>

			<li id="evhandler1"><?php echo $this->form->getLabel('minus'); ?> <?php echo $this->form->getInput('minus'); ?></li>
		</ul>
	</fieldset>
</div>