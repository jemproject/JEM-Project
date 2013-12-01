<?php
/**
 * @version 1.9.5
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
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

	$('jform_showcity0').addEvent('click', cityoff);
	$('jform_showcity1').addEvent('click', cityon);

	if($('jform_showcity1').checked) {
		cityon();
	}

	$('jform_showatte0').addEvent('click', atteoff);
	$('jform_showatte1').addEvent('click', atteon);

	if($('jform_showatte1').checked) {
		atteon();
	}

	$('jform_showtitle0').addEvent('click', titleoff);
	$('jform_showtitle1').addEvent('click', titleon);

	if($('jform_showtitle1').checked) {
		titleon();
	}

	$('jform_showlocate0').addEvent('click', locoff);
	$('jform_showlocate1').addEvent('click', locon);

	if($('jform_showlocate1').checked) {
		locon();
	}

	$('jform_showstate0').addEvent('click', stateoff);
	$('jform_showstate1').addEvent('click', stateon);

	if($('jform_showstate1').checked) {
		stateon();
	}

	$('jform_showcat0').addEvent('click', catoff);
	$('jform_showcat1').addEvent('click', caton);

	if($('jform_showcat1').checked) {
		caton();
	}

	$('jform_showeventimage0').addEvent('click', evimageoff);
	$('jform_showeventimage1').addEvent('click', evimageon);

	if($('jform_showeventimage1').checked) {
		evimageon();
	}

	$('jform_gddisabled0').addEvent('click', lboff);
	$('jform_gddisabled1').addEvent('click', lbon);

	if($('jform_gddisabled1').checked) {
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

	if (nrevhandler == 1 || nrevhandler == 2) {
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
});

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

	if (nrevhandler == 1 || nrevhandler == 2) {
		evhandleron();
	} else {
		evhandleroff();
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
</script>

<script type="text/javascript">
	Joomla.submitbutton = function(task)
	{
		if (task == 'settings.cancel' || document.formvalidator.isValid(document.id('settings-form'))) {
			Joomla.submitform(task, document.getElementById('settings-form'));
		}
	}
</script>

<form action="index.php" method="post" id="settings-form" name="adminForm" class="form-validate">
	<?php echo JHtml::_('tabs.start', 'settings-pane', array('useCookie'=>1)); ?>
	<?php echo JHtml::_('tabs.panel', JText::_( 'COM_JEM_BASIC_SETTINGS' ), 'settings-basic'); ?>

	<?php echo $this->loadTemplate('basicdisplay'); ?>
	<?php echo $this->loadTemplate('basiceventhandling'); ?>
	<?php echo $this->loadTemplate('basicimagehandling'); ?>
	<?php echo $this->loadTemplate('basicmetahandling'); ?>

	<?php echo JHtml::_('tabs.panel', JText::_( 'COM_JEM_EVENT_PAGE' ), 'layout2'); ?>

	<?php echo $this->loadTemplate('evvenues'); ?>
	<?php echo $this->loadTemplate('evevents'); ?>
	<?php echo $this->loadTemplate('evregistration'); ?>

	<?php echo JHtml::_('tabs.panel', JText::_( 'COM_JEM_LAYOUT' ), 'layout'); ?>

	<?php echo $this->loadTemplate('layout'); ?>

	<?php echo JHtml::_('tabs.panel', JText::_( 'COM_JEM_GLOBAL_PARAMETERS' ), 'parameters'); ?>

	<?php echo $this->loadTemplate('parameters'); ?>

	<?php echo JHtml::_('tabs.panel', JText::_( 'COM_JEM_USER_CONTROL' ), 'usercontrol'); ?>

	<?php echo $this->loadTemplate('usercontrol'); ?>

	<?php echo JHtml::_('tabs.end'); ?>

	<div class="clr"></div>

	<input type="hidden" name="task" value="">
	<input type="hidden" name="id" value="1">
	<input type="hidden" name="lastupdate" value="<?php $this->jemsettings->lastupdate; ?>">
	<input type="hidden" name="option" value="com_jem">
	<input type="hidden" name="controller" value="settings">
	<?php echo JHtml::_('form.token'); ?>
</form>
