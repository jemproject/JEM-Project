/**
 * @version 1.9.7
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
	/* Don't remove image file yet. Only if user saves changes.
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
	 */
		var di = document.getElementById('datimage');
		if (di) { di.style.display = 'none'; }
		var li = document.getElementById('locimage');
		if (li) { li.style.display = 'none'; }
		var ufr = document.getElementById('userfile-remove');
		if (ufr) { ufr.style.display = 'none'; }
		var ri = document.getElementById('removeimage');
		if (ri) { ri.value = '1'; }
	/* Moved to dedicated Clear buton
	  	var uf = document.getElementById('userfile');
		if (uf) { uf.value = ''; }
	 */
	});
 }); 