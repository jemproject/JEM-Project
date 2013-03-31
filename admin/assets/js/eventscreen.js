/**
 * @version 1.1 $Id$
 * @package Joomla
 * @subpackage EventList
 * @copyright (C) 2005 - 2009 Christoph Lukes
 * @license GNU/GPL 2, see LICENSE.php
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
 **/
var eventscreen = new Class(  
{  
	options:  {
		id: "",
		script_url: "index.php?option=com_eventlist&controller=events&format=raw",
		task: ""
},

initialize: function( name, options )  
{  
	this.setOptions( options );
	this.name = name;
},  

fetchscreen: function( name, options )  
{
	$(this.name).setHTML('<p class="el_centerimg"><img src="components/com_eventlist/assets/images/ajax-loader.gif" align="center"></p>');

  	var ajax = new Ajax(this.options.script_url + "&task=" + this.options.task + "&id=" + this.options.id, {
    	method: 'get',
    	update: this.name,
    	evalScripts: false
  	});
  	ajax.request.delay(3000, ajax);
},

reseter: function( task, id, div )
{
	var url = 'index.php?option=com_eventlist&controller=events&task=' + task + '&id=' + id + '&format=raw';

	var resetajax = new Ajax(url, {
		method: 'get',
		update: div,
		evalScripts: false
		});
	resetajax.request();
}

});

eventscreen.implement( new Options, new Events );