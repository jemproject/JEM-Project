<?php
/**
 * @version 1.9.1
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;
?>

<div class="width-100">
	<fieldset class="adminform">
		<legend><?php echo JText::_( 'COM_JEM_VENUES' ); ?></legend>
		<ul class="adminformlist">
			<li><?php echo $this->form->getLabel('showlocdescription'); ?> <?php echo $this->form->getInput('showlocdescription'); ?></li>

			<li><?php echo $this->form->getLabel('showdetailsadress'); ?> <?php echo $this->form->getInput('showdetailsadress'); ?></li>

			<li><?php echo $this->form->getLabel('showdetlinkvenue'); ?> <?php echo $this->form->getInput('showdetlinkvenue'); ?></li>

			<li><?php echo $this->form->getLabel('showmapserv'); ?> <?php echo $this->form->getInput('showmapserv'); ?></li>

			<li id="map1" style="display:none"><?php echo $this->form->getLabel('tld'); ?> <?php echo $this->form->getInput('tld'); ?></li>

			<li id="map2" style="display:none"><?php echo $this->form->getLabel('lg'); ?> <?php echo $this->form->getInput('lg'); ?></li>
		</ul>
	</fieldset>
</div>