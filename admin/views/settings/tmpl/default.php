<?php
/**
 * @version $Id$
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 
 * JEM is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.
 *
 * JEM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with JEM; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

defined('_JEXEC') or die;

// Load tooltips behavior
 JHtml::_('behavior.formvalidation');
 JHtml::_('behavior.tooltip');
 JHtml::_('behavior.switcher');


// Load submenu template, using element id 'submenu' as needed by behavior.switcher
// $this->document->setBuffer($this->loadTemplate('navigation'), 'modules', 'submenu');
?>

<form action="index.php" method="post" id="adminForm" name="adminForm">



			<?php
			$title = JText::_( 'COM_JEM_BASIC_SETTINGS' );
			echo JHtml::_('tabs.start', 'det-pane', array('useCookie'=>1));
			
			echo JHtml::_('tabs.panel', $title, 'basic');
			?>

			<div id="config-document">
			<div id="page-basic" class="tab">
			<div class="noshow">
				<?php echo $this->loadTemplate('basic'); ?>
			</div></div></div>

			<?php
			$title = JText::_( 'COM_JEM_USER_CONTROL' );
			echo JHtml::_('tabs.panel', $title, 'layout');
			?>
			<div id="page-usercontrol" class="tab">
			<div class="noshow">
				<?php 
				echo $this->loadTemplate('usercontrol'); 
				?>
			</div></div>
			
			<?php
			$title = JText::_( 'COM_JEM_DETAILS_PAGE' );
			echo JHtml::_('tabs.panel', $title, 'details');
			?>
			<div id="page-details" class="tab">
			<div class="noshow">
				<?php 
				echo $this->loadTemplate('detailspage');
				 ?>
			</div></div>

			<?php
			$title = JText::_( 'COM_JEM_LAYOUT' );
			echo JHtml::_('tabs.panel', $title, 'layout');
			?>
            <div id="page-layout" class="tab">
            <div class="noshow">
				<?php 
				echo $this->loadTemplate('layout'); 
				?>
			</div></div>

			<?php
			$title = JText::_( 'COM_JEM_GLOBAL_PARAMETERS' );
			echo JHtml::_('tabs.panel', $title, 'parameters');
			?>
            <div id="page-parameters" class="tab">
            <div class="noshow">
               <?php 
               echo $this->loadTemplate('parameters');
                ?>
            </div></div>

		</div>
		
		<?php
		echo JHtml::_('sliders.end');
		?>
		
		<div class="clr"></div>
		
		<?php echo JHTML::_( 'form.token' ); ?>
		<input type="hidden" name="task" value="">
		<input type="hidden" name="id" value="1">
		<input type="hidden" name="lastupdate" value="<?php echo $this->jemsettings->lastupdate; ?>">
		<input type="hidden" name="option" value="com_jem">
		<input type="hidden" name="controller" value="settings">
		</form>

		<p class="copyright">
			<?php echo JEMAdmin::footer( ); ?>
		</p>