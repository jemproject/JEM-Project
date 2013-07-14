<?php
/**
 * @version 1.9
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die;

// Load tooltips behavior
 JHtml::_('behavior.formvalidation');
 JHtml::_('behavior.tooltip');
 JHtml::_('behavior.switcher');
?>

<script>
Joomla.submitbutton = function(task)
{

	var form = document.adminForm;

	if (task == 'cancel') {
		submitform( task );
	} else if (form.recurrence_anticipation.value == "" || form.recurrence_anticipation.value == 0 ){
		alert( "<?php echo JText::_ ( 'COM_JEM_ADD_RECURRENCE' ); ?>" );
		form.recurrence_anticipation.focus();
	} else {
		submitform( task );
	}
}

</script>



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
			$title = JText::_( 'COM_JEM_EVENT_PAGE' );
			echo JHtml::_('tabs.panel', $title, 'event');
			?>
			<div id="page-event" class="tab">
			<div class="noshow">
				<?php
				echo $this->loadTemplate('eventpage');
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