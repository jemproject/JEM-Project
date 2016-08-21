<?php
/**
 * @version 2.1.7
 * @package JEM
 * @copyright (C) 2013-2016 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 *
 * * @todo: move js to a file
 */

defined('_JEXEC') or die;

// Load tooltips behavior
JHtml::_('behavior.formvalidation');
JHtml::_('behavior.switcher');
JHtml::_('behavior.tooltip');


?>

<script>
window.addEvent('domready', function(){

	$('jform_showcity0').addEvent('click', cityon);
	$('jform_showcity1').addEvent('click', cityoff);

	if($('jform_showcity0').checked) {
		cityon();
	}

	$('jform_showatte0').addEvent('click', atteon);
	$('jform_showatte1').addEvent('click', atteoff);

	if($('jform_showatte0').checked) {
		atteon();
	}

	$('jform_showtitle0').addEvent('click', titleon);
	$('jform_showtitle1').addEvent('click', titleoff);

	if($('jform_showtitle0').checked) {
		titleon();
	}

	$('jform_showlocate0').addEvent('click', locon);
	$('jform_showlocate1').addEvent('click', locoff);

	if($('jform_showlocate0').checked) {
		locon();
	}

	$('jform_showstate0').addEvent('click', stateon);
	$('jform_showstate1').addEvent('click', stateoff);

	if($('jform_showstate0').checked) {
		stateon();
	}

	$('jform_showcat0').addEvent('click', caton);
	$('jform_showcat1').addEvent('click', catoff);

	if($('jform_showcat0').checked) {
		caton();
	}

	$('jform_showeventimage0').addEvent('click', evimageon);
	$('jform_showeventimage1').addEvent('click', evimageoff);

	if($('jform_showeventimage0').checked) {
		evimageon();
	}

	$('jform_gddisabled0').addEvent('click', lbon);
	$('jform_gddisabled1').addEvent('click', lboff);

	if($('jform_gddisabled0').checked) {
		lbon();
	}

	$("jform_globalattribs_event_show_mapserv").addEvent('change', testmap);

	var mapserv = $("jform_globalattribs_event_show_mapserv");
	var nrmapserv = mapserv.options[mapserv.selectedIndex].value;

	if (nrmapserv == 1 || nrmapserv == 2) {
		eventmapon();
	} else {
		eventmapoff();
	}


	$("jform_globalattribs_global_show_mapserv").addEvent('change', testmap);

	var mapserv = $("jform_globalattribs_global_show_mapserv");
	var nrmapserv = mapserv.options[mapserv.selectedIndex].value;

	if (nrmapserv == 1 || nrmapserv == 2) {
		globalmapon();
	} else {
		globalmapoff();
	}

	$("jform_oldevent").addEvent('change', testevhandler);

	var evhandler = $("jform_oldevent");
	var nrevhandler = evhandler.options[evhandler.selectedIndex].value;

	if (nrevhandler == 1 || nrevhandler == 2 || nrevhandler == 3) {
		evhandleron();
	} else {
		evhandleroff();
	}

	$('jform_globalattribs_event_comunsolution').addEvent('change', testcomm);

	var commhandler = $("jform_globalattribs_event_comunsolution");
	var nrcommhandler = commhandler.options[commhandler.selectedIndex].value;

	if (nrcommhandler == 1) {
		common();
	} else {
		commoff();
	}


	var ObjArray = $$('input.colorpicker').get('id').sort();

	var arrayLength = ObjArray.length;
	for (var i = 0; i < arrayLength; i++) {
	    var Obj 	= $(ObjArray[i]);
		var color = testcolor(Obj.value);
		if (color) {
			Obj.style.color = color;
		}
	}

	$("jform_showfroregistra").addEvent('change', testregistra);

	var registra = $("jform_showfroregistra");
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
	var commhandler = $("jform_globalattribs_event_comunsolution");
	var nrcommhandler = commhandler.options[commhandler.selectedIndex].value;

	if (nrcommhandler == 1) {
		common();
	} else {
		commoff();
	}
}

function testmap()
{
	var mapserv = $("jform_globalattribs_event_show_mapserv");
	var nrmapserv = mapserv.options[mapserv.selectedIndex].value;

	if (nrmapserv == 1 || nrmapserv == 2) {
		eventmapon();
	} else {
		eventmapoff();
	}

	var mapserv2 = $("jform_globalattribs_global_show_mapserv");
	var nrmapserv2 = mapserv2.options[mapserv2.selectedIndex].value;

	if (nrmapserv2 == 1 || nrmapserv2 == 2) {
		globalmapon();
	} else {
		globalmapoff();
	}
}

function testevhandler()
{
	var evhandler = $("jform_oldevent");
	var nrevhandler = evhandler.options[evhandler.selectedIndex].value;

	if (nrevhandler == 1 || nrevhandler == 2 || nrevhandler == 3) {
		evhandleron();
	} else {
		evhandleroff();
	}
}

function testregistra()
{
	var registra = $("jform_showfroregistra");
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
{
	document.getElementById('froreg1').style.display = 'none';
	document.getElementById('froreg2').style.display = 'none';
}
</script>

<script type="text/javascript">
	Joomla.submitbutton = function(task)
	{
		if (task == 'settings.cancel' || document.formvalidator.isValid(document.id('settings-form'))) {
			Joomla.submitform(task, document.getElementById('settings-form'));
		}
	}
</script>

<form action="<?php echo JRoute::_('index.php?option=com_jem&view=settings'); ?>" method="post" id="settings-form" name="adminForm" class="form-validate">
	<?php if (isset($this->sidebar)) : ?>
	<div id="j-sidebar-container" class="span2">
		<?php echo $this->sidebar; ?>
	</div>
	<div id="j-main-container" class="span10">
	<?php endif; ?>
		<?php echo JHtml::_('tabs.start', 'settings-pane', array('useCookie'=>1)); ?>
		<?php echo JHtml::_('tabs.panel', JText::_( 'COM_JEM_BASIC_SETTINGS' ), 'settings-basic'); ?>

		<div class="width-50 fltlft">
			<?php echo $this->loadTemplate('basicdisplay'); ?>
			<?php echo $this->loadTemplate('basiceventhandling'); ?>
		</div>
		<div class="width-50 fltrt">
			<?php echo $this->loadTemplate('basicimagehandling'); ?>
			<?php echo $this->loadTemplate('basicmetahandling'); ?>
		</div>
		<div class="clr"></div>

		<?php echo JHtml::_('tabs.panel', JText::_( 'COM_JEM_EVENT_PAGE' ), 'layout2'); ?>

		<div class="width-50 fltlft">
			<?php echo $this->loadTemplate('evevents'); ?>
		</div>
		<div class="width-50 fltrt">
			<?php echo $this->loadTemplate('evvenues'); ?>
			<?php echo $this->loadTemplate('evregistration'); ?>
		</div>
		<div class="clr"></div>

		<?php echo JHtml::_('tabs.panel', JText::_( 'COM_JEM_LAYOUT' ), 'layout'); ?>
		<?php echo $this->loadTemplate('layout'); ?>

		<?php echo JHtml::_('tabs.panel', JText::_( 'COM_JEM_GLOBAL_PARAMETERS' ), 'parameters'); ?>
		<?php echo $this->loadTemplate('parameters'); ?>

		<?php echo JHtml::_('tabs.panel', JText::_( 'COM_JEM_USER_CONTROL' ), 'usercontrol'); ?>
		<?php echo $this->loadTemplate('usercontrol'); ?>
		<?php echo JHtml::_('tabs.panel', JText::_( 'COM_JEM_SETTINGS_TAB_CONFIGINFO' ), 'configinfo'); ?>
		<?php echo $this->loadTemplate('configinfo'); ?>

		<?php echo JHtml::_('tabs.end'); ?>

		<div class="clr"></div>
	<?php if (isset($this->sidebar)) : ?>
	</div>
	<?php endif; ?>

	<input type="hidden" name="task" value="">
	<input type="hidden" name="id" value="1">
	<input type="hidden" name="lastupdate" value="<?php $this->jemsettings->lastupdate; ?>">
	<input type="hidden" name="option" value="com_jem">
	<input type="hidden" name="controller" value="settings">
	<?php echo JHtml::_('form.token'); ?>
</form>
