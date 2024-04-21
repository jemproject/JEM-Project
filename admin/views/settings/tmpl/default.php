<?php
/**
 * @version    4.2.1
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 *
 * * @todo: move js to a file
 */

defined('_JEXEC') or die;

use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;

// Load tooltips behavior
// HTMLHelper::_('behavior.formvalidation');
// HTMLHelper::_('behavior.switcher');
// HTMLHelper::_('behavior.tooltip');


$wa = $this->document->getWebAssetManager();
		$wa
			->useScript('keepalive')
			->useStyle('jem.colorpicker')
			->useScript('inlinehelp')
			->useScript('form.validate');


?>

<script>
jQuery(document).ready(function($){
	$('#jform_showcity0').bind('click', cityon);
	$('#jform_showcity1').bind('click', cityoff);

	if($('#jform_showcity0').checked) {
		cityon();
	}

	$('#jform_showatte0').bind('click', atteon);
	$('#jform_showatte1').bind('click', atteoff);

	if($('#jform_showatte0').checked) {
		atteon();
	}

	$('#jform_showtitle0').bind('click', titleon);
	$('#jform_showtitle1').bind('click', titleoff);

	if(document.getElementById('jform_showtitle0').checked) {
		titleon();
	}

	$('#jform_showlocate0').bind('click', locon);
	$('#jform_showlocate1').bind('click', locoff);

	if(document.getElementById('jform_showlocate0').checked) {
		locon();
	}

	$('#jform_showstate0').bind('click', stateon);
	$('#jform_showstate1').bind('click', stateoff);

	if(document.getElementById('jform_showstate0').checked) {
		stateon();
	}

	$('#jform_showcat0').bind('click', caton);
	$('#jform_showcat1').bind('click', catoff);

	if(document.getElementById('jform_showcat0').checked) {
		caton();
	}

	$('#jform_showeventimage0').bind('click', evimageon);
	$('#jform_showeventimage1').bind('click', evimageoff);

	if(document.getElementById('jform_showeventimage0').checked) {
		evimageon();
	}

	$('#jform_gddisabled0').bind('click', lbon);
	$('#jform_gddisabled1').bind('click', lboff);

	if(document.getElementById('jform_gddisabled0').checked) {
		lbon();
	}

	$("#jform_globalattribs_event_show_mapserv").bind('change', testmap);

	var mapserv = document.getElementById("jform_globalattribs_event_show_mapserv");
	var nrmapserv = mapserv.options[mapserv.selectedIndex].value;

	if (nrmapserv == 1 || nrmapserv == 2) {
		eventmapon();
	} else {
		eventmapoff();
	}


	$("#jform_globalattribs_global_show_mapserv").bind('change', testmap);

	var mapserv = document.getElementById("jform_globalattribs_global_show_mapserv");
	var nrmapserv = mapserv.options[mapserv.selectedIndex].value;

	if (nrmapserv == 1 || nrmapserv == 2) {
		globalmapon();
	} else {
		globalmapoff();
	}

	$("#jform_oldevent").bind('change', testevhandler);

	var evhandler = document.getElementById("jform_oldevent");
	var nrevhandler = evhandler.options[evhandler.selectedIndex].value;

	if (nrevhandler > 0) {
		evhandleron();
	} else {
		evhandleroff();
	}

	$('#jform_globalattribs_event_comunsolution').bind('change', testcomm);

	var commhandler = document.getElementById("jform_globalattribs_event_comunsolution");
	var nrcommhandler = commhandler.options[commhandler.selectedIndex].value;

	if (nrcommhandler == 1) {
		common();
	} else {
		commoff();
	}


	var ObjArray = $('input.colorpicker').get('id').sort();

	var arrayLength = ObjArray.length;
	for (var i = 0; i < arrayLength; i++) {
	    var Obj 	= $(ObjArray[i]);
		var color = testcolor(Obj.value);
		if (color) {
			Obj.style.color = color;
		}
	}

	$("#jform_showfroregistra").bind('change', testregistra);

	var registra = document.getElementById("jform_showfroregistra");
	var nrregistra = registra.options[registra.selectedIndex].value;

	if (nrregistra >= 1) {
		registraon();
	} else {
		registraoff();
	}
});


function testcolor(color) {
	if(color.length==7)
	{
		color=color.substring(1);
	}
	var R = parseInt(color.substring(0,2),16);
	var G = parseInt(color.substring(2,4),16);
	var B = parseInt(color.substring(4,6),16);
	var x = Math.sqrt(R * R * .299 + G * G * .587 + B * B * .114);

	var sColorText = x < 130 ? '#FFFFFF' : '#000000';

	return sColorText;
	}

function testcomm()
{
	var commhandler = document.getElementById("jform_globalattribs_event_comunsolution");
	var nrcommhandler = commhandler.options[commhandler.selectedIndex].value;

	if (nrcommhandler == 1) {
		common();
	} else {
		commoff();
	}
}

function testmap()
{
	var mapserv = document.getElementById("jform_globalattribs_event_show_mapserv");
	var nrmapserv = mapserv.options[mapserv.selectedIndex].value;

	if (nrmapserv == 1 || nrmapserv == 2) {
		eventmapon();
	} else {
		eventmapoff();
	}

	var mapserv2 = document.getElementById("jform_globalattribs_global_show_mapserv");
	var nrmapserv2 = mapserv2.options[mapserv2.selectedIndex].value;

	if (nrmapserv2 == 1 || nrmapserv2 == 2) {
		globalmapon();
	} else {
		globalmapoff();
	}
}

function testevhandler()
{
	var evhandler = document.getElementById("jform_oldevent");
	var nrevhandler = evhandler.options[evhandler.selectedIndex].value;

	if (nrevhandler > 0) {
		evhandleron();
	} else {
		evhandleroff();
	}
}

function testregistra()
{
	var registra = document.getElementById("jform_showfroregistra");
	var nrregistra = registra.options[registra.selectedIndex].value;

	if (nrregistra >= 1) {
		registraon();
	} else {
		registraoff();
	}
}

function cityon()
{
	document.getElementById('city1').style.display = '';
}

function cityoff()
{
	var citywidth = document.getElementById('jform_citywidth');
	document.getElementById('city1').style.display = 'none';
	citywidth.value='';
}

function atteon()
{
	document.getElementById('atte1').style.display = '';
}

function atteoff()
{
	var attewidth = document.getElementById('jform_attewidth');
	document.getElementById('atte1').style.display = 'none';
	attewidth.value='';
}

function titleon()
{
	document.getElementById('title1').style.display = '';
}

function titleoff()
{
	var titlewidth = document.getElementById('jform_titlewidth');
	document.getElementById('title1').style.display = 'none';
	titlewidth.value='';
}

function locon()
{
	document.getElementById('loc1').style.display = '';
	document.getElementById('loc2').style.display = '';
}

function locoff()
{
	var locatewidth = document.getElementById('jform_locationwidth');
	document.getElementById('loc1').style.display = 'none';
	locatewidth.value='';
	document.getElementById('loc2').style.display = 'none';
}

function stateon()
{
	document.getElementById('state1').style.display = '';
}

function stateoff()
{
	var statewidth = document.getElementById('jform_statewidth');
	document.getElementById('state1').style.display = 'none';
	statewidth.value='';
}

function caton()
{
	document.getElementById('cat1').style.display = '';
	document.getElementById('cat2').style.display = '';
}

function catoff()
{
	var catwidth = document.getElementById('jform_catfrowidth');
	document.getElementById('cat1').style.display = 'none';
	catwidth.value='';
	document.getElementById('cat2').style.display = 'none';
}

function evimageon()
{
	document.getElementById('evimage1').style.display = '';
}

function evimageoff()
{
	var evimagewidth = document.getElementById('jform_tableeventimagewidth');
	document.getElementById('evimage1').style.display = 'none';
	evimagewidth.value='';
}

function lbon()
{
	document.getElementById('lb1').style.display = '';
}

function lboff()
{
	document.getElementById('lb1').style.display = 'none';
}

function eventmapon()
{
	document.getElementById('eventmap1').style.display = '';
	document.getElementById('eventmap2').style.display = '';
}

function eventmapoff()
{
	document.getElementById('eventmap1').style.display = 'none';
	document.getElementById('eventmap2').style.display = 'none';
	document.getElementById('jform_globalattribs_event_tld').value = '';
	document.getElementById('jform_globalattribs_event_lg').value = '';
}

function globalmapon()
{
	document.getElementById('globalmap1').style.display = '';
	document.getElementById('globalmap2').style.display = '';
}

function globalmapoff()
{
	document.getElementById('globalmap1').style.display = 'none';
	document.getElementById('globalmap2').style.display = 'none';
	document.getElementById('jform_globalattribs_global_tld').value = '';
	document.getElementById('jform_globalattribs_global_lg').value = '';
}


function evhandleron()
{
	document.getElementById('evhandler1').style.display = '';
}

function evhandleroff()
{
	document.getElementById('evhandler1').style.display = 'none';
}

function common()
{
	document.getElementById('comm1').style.display = '';
}

function commoff()
{
	document.getElementById('comm1').style.display = 'none';
}

function registraon()
{
	document.getElementById('froreg1').style.display = '';
	document.getElementById('froreg2').style.display = '';
}

function registraoff()
{Route
	document.getElementById('froreg1').style.display = 'none';
	document.getElementById('froreg2').style.display = 'none';
}

    $(document).ready(function() {
        function updateLightboxVisibility() {
            if ($('input[name="jform[gddisabled]"]:checked').val() === '0') {
                $('input[name="jform[lightbox]"]').val(['0']);
                $('#jform_lightbox, #jform_lightbox-lbl').css('display', 'none');
            } else {
                $('#jform_lightbox, #jform_lightbox-lbl').css('display', '');
            }
        }

        updateLightboxVisibility();

        $('input[name="jform[gddisabled]"]').on('change', function() {
            updateLightboxVisibility();
        });
    });
</script>

<script type="text/javascript">
	Joomla.submitbutton = function(task)
	{
		if (task == 'settings.cancel' || document.formvalidator.isValid(document.getElementById('settings-form'))) {
			Joomla.submitform(task, document.getElementById('settings-form'));
		}
	}
</script>



<form action="<?php echo Route::_('index.php?option=com_jem&view=settings'); ?>" method="post" id="settings-form" name="adminForm" class="form-validate">

	<div id="j-main-container" class="j-main-container">

			<div class="row">
				<div class="col-md-12">
				    <?php echo HTMLHelper::_('uitab.startTabSet', 'settings-pane', ['active' => 'settings-basic', 'recall' => true, 'breakpoint' => 768]); ?>
					<?php echo HTMLHelper::_('uitab.addTab', 'settings-pane', 'settings-basic', Text::_('COM_JEM_BASIC_SETTINGS')); ?>
					    <fieldset class="adminform">
						      
								<div class="w-50 fltlft">
									<?php echo $this->loadTemplate('basicdisplay'); ?>
									<?php echo $this->loadTemplate('basiclayout'); ?>
									<?php echo $this->loadTemplate('basiceventhandling'); ?>
								</div>
								<div class="w-50 fltrt">
									<?php echo $this->loadTemplate('basicimagehandling'); ?>
									<?php echo $this->loadTemplate('basicmetahandling'); ?>
								</div>
						</fieldset>
					<?php echo HTMLHelper::_('uitab.endTab'); ?>
					<div class="clr"></div>


					
					<?php echo HTMLHelper::_('uitab.addTab','settings-pane', 'settings-pane-2', Text::_('COM_JEM_EVENT_PAGE')); ?>
					    <fieldset class="adminform">
							<div class="width-50 fltlft">
								<?php echo $this->loadTemplate('evevents'); ?>
							</div>
							<div class="width-50 fltrt">
								<?php echo $this->loadTemplate('evvenues'); ?>
								<?php echo $this->loadTemplate('evregistration'); ?>
							</div>
						</fieldset>
					<?php echo HTMLHelper::_('uitab.endTab'); ?>
					<div class="clr"></div>


				
					<?php echo HTMLHelper::_('uitab.addTab', 'settings-pane', 'layout', Text::_('COM_JEM_LAYOUT')); ?>
					    <fieldset class="adminform">
						   <?php echo $this->loadTemplate('layout'); ?>
						</fieldset>
					<?php echo HTMLHelper::_('uitab.endTab'); ?>
					<div class="clr"></div>

					
					<?php echo HTMLHelper::_('uitab.addTab', 'settings-pane', 'parameters', Text::_('COM_JEM_GLOBAL_PARAMETERS')); ?>
					    <fieldset class="adminform">
						    <?php echo $this->loadTemplate('parameters'); ?>
						</fieldset>
					<?php echo HTMLHelper::_('uitab.endTab'); ?>
					<div class="clr"></div>


				
					<?php echo HTMLHelper::_('uitab.addTab', 'settings-pane', 'usercontrol', Text::_('COM_JEM_USER_CONTROL')); ?>
					    <fieldset class="adminform">
						   <?php echo $this->loadTemplate('usercontrol'); ?>
						</fieldset>
					<?php echo HTMLHelper::_('uitab.endTab'); ?>
					<div class="clr"></div>


					
					<?php echo HTMLHelper::_('uitab.addTab', 'settings-pane', 'configinfo', Text::_('COM_JEM_SETTINGS_TAB_CONFIGINFO')); ?>
					    <fieldset class="adminform">
						   <?php echo $this->loadTemplate('configinfo'); ?>
						</fieldset>
					<?php echo HTMLHelper::_('uitab.endTab'); ?>
					<div class="clr"></div>
					
				</div>
			</div>
  
	</div>

	<input type="hidden" name="task" value="">
	<input type="hidden" name="id" value="1">
	<input type="hidden" name="lastupdate" value="<?php $this->jemsettings->lastupdate; ?>">
	<input type="hidden" name="option" value="com_jem">
	<input type="hidden" name="controller" value="settings">
	<?php echo HTMLHelper::_('form.token'); ?>
</form>
