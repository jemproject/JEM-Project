/**
 * @version 2.0.0
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
		var di = document.getElementById('datimage');
		if (di) { di.style.display = 'none'; }
		var li = document.getElementById('locimage');
		if (li) { li.style.display = 'none'; }
		var ufr = document.getElementById('userfile-remove');
		if (ufr) { ufr.style.display = 'none'; }
		var ri = document.getElementById('removeimage');
		if (ri) { ri.value = '1'; }
	});
 }); 