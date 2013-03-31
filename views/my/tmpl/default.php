<?php
/**
 * @version 1.1 $Id$
 * @package Joomla
 * @subpackage EventList
 * @copyright (C) 2005 - 2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 * EventList is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.

 * EventList is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.

 * You should have received a copy of the GNU General Public License
 * along with EventList; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

defined( '_JEXEC' ) or die( 'Restricted access' );
?>
<div id="eventlist" class="el_eventlist">

<?php if ($this->params->def( 'show_page_title', 1 )) : ?>

    <h1 class="componentheading">
		<?php echo $this->escape($this->pagetitle); ?>
	</h1>

<?php endif; ?>

<!--table-->

<?php 
if($this->params->get('showmyevents')) :
	echo $this->loadTemplate('events'); 
endif;	
?>

<?php 
if($this->params->get('showmyvenues')) :
	echo $this->loadTemplate('venues'); 
endif;	
?>

<?php
if($this->params->get('showmyregistrations')) :
	echo $this->loadTemplate('attending'); 
endif;	
?>
<!--footer-->

<p class="copyright">
  <?php echo ELOutput::footer( ); ?>
</p>

</div>