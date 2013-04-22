/**
 * @version 1.1 $Id$
 * @package JEM
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 *
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

var eventscreen = new Class(  
{  
	options:  {
		id: "",
		script_url: "index.php?option=com_jem&controller=events&format=raw",
		task: ""
},

initialize: function( name, options )  
{  
	this.setOptions( options );
	this.name = name;
},  


fetchscreen: function( name, options )  
        {
                var doname = this.name;
                if(typeof name!="undefined") {
                        doname = name;
                }
                var dooptions = {
                        method: 'get',
                        update: doname,
                        evalScripts: false
                };
                if(typeof options!="undefined") {
                        dooptions = options;
                }
               
                var loader_html = '<p class="jem_centering"><img src="media/com_jem/images/ajax-loader.gif" align="center"></p>';
                var url_to_load = this.options.script_url + "&task=" + this.options.task + "&id=" + this.options.id;
               
                if (MooTools.version>='1.2.4') {
                        $(doname).set('html', loader_html);
                        if(this.options.id>0) {
                                new Request.HTML({
                                        url: url_to_load,
                                        method: 'get',
                                        update: $(this.name),
                                        evalScripts: false
                                }).send();
                        } else {
                                $(this.name).set('html', '0');
                        }
                } else {
                        $(doname).setHTML(loader_html);
                        var ajax = new Ajax(url_to_load, dooptions);
                        ajax.request.delay(300, ajax);
                }
               
        },




reseter: function( task, id, div )
{
	var url = 'index.php?option=com_jem&controller=events&task=' + task + '&id=' + id + '&format=raw';

	new Request.HTML({
                                url: url,
                                method: 'get',
                                update: $(div),
                                evalScripts: false
                        }).send();

}

});

eventscreen.implement( new Options, new Events );