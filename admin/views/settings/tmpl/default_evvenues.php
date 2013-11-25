<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;
$group = 'globalattribs';
?>

<div class="width-100">
	<fieldset class="adminform">
		<legend><?php echo JText::_( 'COM_JEM_VENUES' ); ?></legend>
		<ul class="adminformlist">
			<li><?php echo $this->form->getLabel('showlocdescription',$group); ?> <?php echo $this->form->getInput('showlocdescription',$group); ?></li>
			<li><?php echo $this->form->getLabel('showdetailsadress',$group); ?> <?php echo $this->form->getInput('showdetailsadress',$group); ?></li>
			<li><?php echo $this->form->getLabel('showdetlinkvenue',$group); ?> <?php echo $this->form->getInput('showdetlinkvenue',$group); ?></li>
			<li><?php echo $this->form->getLabel('showmapserv',$group); ?> <?php echo $this->form->getInput('showmapserv',$group); ?></li>
			<li id="map1" style="display:none"><?php echo $this->form->getLabel('tld',$group); ?> <?php echo $this->form->getInput('tld',$group); ?></li>
			<li id="map2" style="display:none"><?php echo $this->form->getLabel('lg',$group); ?> <?php echo $this->form->getInput('lg',$group); ?></li>
		</ul>
	</fieldset>
</div>