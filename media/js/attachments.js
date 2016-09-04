/**
 * @version 2.1.7
 * @package JEM
 * @copyright (C) 2013-2016 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

/**
 * this file manages the js script for adding/removing attachements in event
 */
window.addEvent('domready', function() {

	$$('.attach-field').addEvent('change', addattach);
	$$('.clear-attach-field').addEvent('click', clearattach);

	$$('.attach-remove').addEvent('click', function(event){
		var event = event || window.event;

		$(event.target).style.cursor = 'wait'; /* indicate server request */

		var url = '';
		var pos = event.target.id.indexOf(':');
		if (pos >= 0) {
			var id = event.target.id.substring(13, pos);
			var token = event.target.id.substr(pos+1);
			url = 'index.php?option=com_jem&task=ajaxattachremove&format=raw&id='+id+'&'+token+'=1';
		} else {
			var id = event.target.id.substr(13);
			url = 'index.php?option=com_jem&task=ajaxattachremove&format=raw&id='+id;
		}
		var theAjax = new Request( {
			url : url,
			method: 'post',
			postBody : ''
			});

		theAjax.addEvent('onSuccess', function(response) {
			/* server sends 1 on success, 0 on error */
			if (response.indexOf('1') > -1) {
				$(event.target).getParent().getParent().dispose();
			} else {
				$(event.target).style.cursor = 'not-allowed'; /* remove failed - how to show? */
			}
		}.bind(this));
		theAjax.send();
	});
});

function addattach()
{
	var tbody = $('el-attachments').getElement('tbody');
	var rows = tbody.getElements('tr');
	var emptyRows = [];

	/* do we have empty rows? */
	for(var i = 0; i < rows.length; i++) {
		var af = rows[i].getElement('.attach-field');
		if (af && !(af.files.length > 0)) {
			emptyRows.push(af);
			break; /* one is enough, so we can break */
		}
	};

	/* if not create one */
	if (emptyRows.length < 1) {
		var row = rows[rows.length-1].clone();
		row.getElement('.attach-field').addEvent('change', addattach).value = '';
		row.getElement('.clear-attach-field').addEvent('click', clearattach).value = '';
		row.inject(tbody);
	}
}

function clearattach(event) {
	var event = event || window.event;

	var grandpa = $(event.target).getParent().getParent();
	var af = grandpa.getElement('.attach-field');
	if (af) af.value = '';
	var an = grandpa.getElement('.attach-name');
	if (an) an.value = '';
	var ad = grandpa.getElement('.attach-desc');
	if (ad) ad.value = '';
}
