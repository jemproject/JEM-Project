/**
 * @version 1.9 $Id$
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

/**
 * this file manages the js script for adding/removing attachements in event
 */
window.addEvent('domready', function() {	
	
	$$('.attach-field').addEvent('change', addattach);
	
	$$('.attach-remove').addEvent('click', function(event){
		event = new Event(event); // for IE !
		
		id = event.target.id.substr(13);
		var url = 'index.php?option=com_jem&task=ajaxattachremove&format=raw&id='+id;
		var theAjax = new Request( {
			url : url,
			method: 'post',
			postBody : ''
			});
		
		theAjax.addEvent('onSuccess', function(response) {
			if (response == "1") {
				$(event.target).getParent().getParent().dispose();
			}
			//this.venue = eval('(' + response + ')');
		}.bind(this));
		theAjax.send();
	});
});

function addattach()
{
	var tbody = $('el-attachments').getElement('tbody');
	var rows = tbody.getElements('tr');
	var row = rows[rows.length-1].clone();
	row.getElement('.attach-field').addEvent('change', addattach).value = '';
	row.injectInside(tbody);
}