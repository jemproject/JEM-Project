/**
 * @version 1.1 $Id$
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

function changeoldMode()
{
	if(document.getElementById) {
		mode = window.document.adminForm.oldevent.selectedIndex;
		switch (mode) {
			case 0:
				document.getElementById('old').style.display = 'none';
			break;
			default:
				document.getElementById('old').style.display = '';
		} // switch
	} // if
}

function changeintegrateMode()
{
	if(document.getElementById) {
		mode = window.document.adminForm.comunsolution.selectedIndex;
		switch (mode) {
			case 0:
				document.getElementById('integrate').style.display = 'none';
				break;
			default:
				document.getElementById('integrate').style.display = '';
		} // switch
	} // if
}

function changegdMode(mode)
{
	if(document.getElementById) {
		switch (mode) {
			case 0:
				document.getElementById('gd1').style.display = 'none';
				break;
			default:
				document.getElementById('gd1').style.display = '';
		} // switch
	} // if
}

function changemapMode()
{
	if(document.getElementById) {
		mode = window.document.adminForm.showmapserv.selectedIndex;
		switch (mode) {
			case 0:
				document.getElementById('map24').style.display = 'none';
				document.getElementById('gapikey').style.display = 'none';
				break;
			case 1:
				document.getElementById('map24').style.display = '';
				document.getElementById('gapikey').style.display = 'none';
				break;
			case 2:
				document.getElementById('map24').style.display = 'none';
				document.getElementById('gapikey').style.display = '';
				break;
			default:
				document.getElementById('map24').style.display = '';
				document.getElementById('gapikey').style.display = '';
		} // switch
	} // if
}

function changetitleMode(mode)
{
	if(document.getElementById) {
		switch (mode) {
			case 0:
				document.getElementById('title1').style.display = 'none';
				document.adminForm.titlewidth.value='';
				document.getElementById('title2').style.display = 'none';
				break;
			default:
				document.getElementById('title1').style.display = '';
				document.getElementById('title2').style.display = '';
		} // switch
	} // if
}

function changelocateMode(mode)
{
	if(document.getElementById) {
		switch (mode) {
			case 0:
				document.getElementById('locate1').style.display = 'none';
				document.adminForm.locationwidth.value='';
				document.getElementById('locate2').style.display = 'none';
				document.getElementById('locate3').style.display = 'none';
				break;
			default:
				document.getElementById('locate1').style.display = '';
				document.getElementById('locate2').style.display = '';
				document.getElementById('locate3').style.display = '';
		} // switch
	} // if
}

function changecityMode(mode)
{
	if(document.getElementById) {
		switch (mode) {
			case 0:
				document.getElementById('city1').style.display = 'none';
				document.adminForm.citywidth.value='';
				document.getElementById('city2').style.display = 'none';
				break;
			default:
				document.getElementById('city1').style.display = '';
				document.getElementById('city2').style.display = '';
		} // switch
	} // if
}

function changestateMode(mode)
{
	if(document.getElementById) {
		switch (mode) {
			case 0:
				document.getElementById('state1').style.display = 'none';
				document.adminForm.statewidth.value='';
				document.getElementById('state2').style.display = 'none';
				break;
			default:
				document.getElementById('state1').style.display = '';
				document.getElementById('state2').style.display = '';
		} // switch
	} // if
}

function changecatMode(mode)
{
	if(document.getElementById) {
		switch (mode) {
			case 0:
				document.getElementById('cat1').style.display = 'none';
				document.adminForm.catfrowidth.value='';
				document.getElementById('cat2').style.display = 'none';
				document.getElementById('cat3').style.display = 'none';
				break;
			default:
				document.getElementById('cat1').style.display = '';
				document.getElementById('cat2').style.display = '';
				document.getElementById('cat3').style.display = '';
		} // switch
	} // if
}

function changeatteMode(mode)
{
	if(document.getElementById) {
		switch (mode) {
			case 0:
				document.getElementById('atte1').style.display = 'none';
				document.adminForm.attewidth.value='';
				document.getElementById('atte2').style.display = 'none';				
				break;
			default:
				document.getElementById('atte1').style.display = '';
				document.getElementById('atte2').style.display = '';				
		} // switch
	} // if
}

function changeregMode()
{
	if(document.getElementById) {
		mode = window.document.adminForm.showfroregistra.selectedIndex;
		switch (mode) {
			case 0:
				document.getElementById('froreg').style.display = 'none';
				break;
			default:
				document.getElementById('froreg').style.display = '';
		} // switch
	} // if
}

document.switcher = null;
if (MooTools.version == '1.11') {

	Window.onDomReady(function(){

		toggler = $('submenu')
  	element = $('elconfig-document')
  	if(element) {
  		document.switcher = new JSwitcher(toggler, element, {cookieName: toggler.getAttribute('class')});
  	}

	});

} else {
	window.addEvent('domready', function() {

		toggler = $('submenu')
  	element = $('elconfig-document')
  	if(element) {
  		document.switcher = new JSwitcher(toggler, element, {cookieName: toggler.getAttribute('class')});
  	}

	});

}