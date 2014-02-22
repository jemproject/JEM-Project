/**
 * @version 1.9.6
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

/**
 * this file manages the js script for adding/removing attachements in event
 */
window.addEvent('domready', function() {

	$$('.attach-field').addEvent('change', addattach);

	$$('.attach-remove').addEvent('click', function(event){
		var event = event || window.event;

		var id = event.target.id.substr(13);
		var url = 'index.php?option=com_jem&task=ajaxattachremove&format=raw&id='+id;
		var theAjax = new Request( {
			url : url,
			method: 'post',
			postBody : ''
			});

		theAjax.addEvent('onSuccess', function(response) {
			if (response.indexOf('1') > -1) {
				$(event.target).getParent().getParent().dispose();
			}
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
	row.inject(tbody);
}