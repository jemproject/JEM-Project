<?php
/**
 * @version    4.2.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;


// HTMLHelper::_('behavior.tooltip');
// HTMLHelper::_('behavior.formvalidation');
// HTMLHelper::_('behavior.keepalive');

$wa = $this->document->getWebAssetManager();
		$wa->useScript('keepalive')
			->useScript('inlinehelp')
			->useScript('form.validate');

// Create shortcut to parameters.
$params = $this->state->get('params');
$params = $params->toArray();

?>
<script type="text/javascript">
	window.addEvent('domready', function(){
	});

	// moves elements from one select box to another one
	function moveOptions(from,to) {
		// Move them over
		for (var i=0; i<from.options.length; i++) {
			var o = from.options[i];
			if (o.selected) {
			  to.options[to.options.length] = new Option( o.text, o.value, false, false);
			}
		}

		// Delete them from original
		for (var i=(from.options.length-1); i>=0; i--) {
			var o = from.options[i];
			if (o.selected) {
			  from.options[i] = null;
			}
		}
		from.selectedIndex = -1;
		to.selectedIndex = -1;
	}

	function selectAll()
    {
        selectBox = document.getElementById("maintainers");

        for (var i = 0; i < selectBox.options.length; i++)
        {
             selectBox.options[i].selected = true;
        }
    }
</script>
<script type="text/javascript">
	Joomla.submitbutton = function(task)
	{
		selectAll();
		if (task == 'group.cancel' || document.formvalidator.isValid(document.getElementById('adminForm'))) {
			Joomla.submitform(task, document.getElementById('adminForm'));
		}
	}
</script>




<form
	action="<?php echo Route::_('index.php?option=com_jem&layout=edit&id='.(int) $this->item->id); ?>"
	class="form-validate" method="post" name="adminForm" id="adminForm" enctype="multipart/form-data">

	<div class="row">
		    <div class="col-md-7">
		        <!-- <div class="width-55 fltlft"> -->
					<?php echo HTMLHelper::_('uitab.startTabSet', 'myTab', ['active' => 'group-info', 'recall' => true, 'breakpoint' => 768]); ?>
					<?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'group-info', Text::_('COM_JEM_GROUP_INFO_TAB')); ?>
					<fieldset class="adminform">
						<legend>
							<?php echo empty($this->item->id) ? Text::_('COM_JEM_NEW_GROUP') : Text::sprintf('COM_JEM_GROUP_DETAILS', $this->item->id); ?>
						</legend>
						<ul class="adminformlist">
							<li><div class="label-form"><?php echo $this->form->renderfield('name'); ?></div>
							</li>
							<li><div class="label-form"><?php echo $this->form->renderfield('id'); ?></div>
							</li>
							<li><div class="label-form"><?php echo $this->form->renderfield('maintainers2'); ?></div>
							</li>
						</ul>
					</fieldset>
					<fieldset class="adminform">
						<table class="adminform" style="width: 100%">
							<tr>
								<td><b><?php echo Text::_('COM_JEM_GROUP_AVAILABLE_USERS').':'; ?></b></td>
								<td>&nbsp;</td>
								<td><b><?php echo Text::_('COM_JEM_GROUP_MAINTAINERS').':'; ?></b></td>
							</tr>
							<tr>
								<td width="44%"><?php echo $this->lists['available_users']; ?></td>
								<td width="10%">
									<input style="width: 90%" type="button" name="right" value="&gt;" onClick="moveOptions(document.adminForm['available_users'], document.adminForm['maintainers[]'])" />
									<br /><br />
									<input style="width: 90%" type="button" name="left" value="&lt;" onClick="moveOptions(document.adminForm['maintainers[]'], document.adminForm['available_users'])" />
								</td>
								<td width="44%"><?php echo $this->lists['maintainers']; ?></td>
							</tr>
						</table>
					</fieldset>
					<fieldset class="adminform">
						<div>
							<?php echo $this->form->getLabel('description'); ?>
							<div class="clr"></div>
							<?php echo $this->form->getInput('description'); ?>
						</div>
					</fieldset>
					<?php echo HTMLHelper::_('uitab.endTab'); ?>
				<!-- </div> -->
			</div>
			<div class="col-md-5">
				<!-- <div class="width-40 fltrt"> -->
					<div class="accordion" id="accordionGroupForm">
						<div class="accordion-item">
								<h2 class="accordion-header" id="group-permission-header">
									<button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#group-permission" aria-expanded="true" aria-controls="group-permission">
									<?php echo Text::_('COM_JEM_GROUP_PERMISSIONS'); ?>
									</button>
								</h2>
								<div id="group-permission" class="accordion-collapse collapse show" aria-labelledby="group-permission-header" data-bs-parent="#accordionGroupForm">
									<div class="accordion-body">
										<fieldset class="panelform">
											<ul class="adminformlist">
											<li><div class="label-form"><?php echo $this->form->renderfield('addvenue'); ?></div></li>
											<li><div class="label-form"><?php echo $this->form->renderfield('publishvenue'); ?></div></li>
											<li><div class="label-form"><?php echo $this->form->renderfield('editvenue'); ?></div></li>
											<li><div class="label-form"><?php echo $this->form->renderfield('addevent'); ?></div></li>
											<li><div class="label-form"><?php echo $this->form->renderfield('publishevent'); ?></div></li>
											<li><div class="label-form"><?php echo $this->form->renderfield('editevent'); ?></div></li>
											</ul>
										</fieldset>
									</div>
								</div>
						</div>
					</div>
				<!-- </div> -->
			</div>
    </div>

	<div class="clr"></div>
	<input type="hidden" name="task" value="" />
	<?php echo HTMLHelper::_( 'form.token' ); ?>
</form>

