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
 	$$('#userfile-remove').addEvent('click', function(event){
		var event = event || window.event;

		var type = event.target.getAttribute('data-type');
		var id = event.target.getAttribute('data-id');
		var url = 'index.php?option=com_jem&task=ajaximageremove&format=raw&type='+type+'&id='+id;
		var theAjax = new Request({
			url : url,
			method: 'post',
			postBody : ''
		});

		theAjax.addEvent('onSuccess', function(response) {
			document.getElementById('datimage').style.display = 'none';
		}.bind(this));
		theAjax.send();

		document.getElementById('userfile').value = '';
	});
 });