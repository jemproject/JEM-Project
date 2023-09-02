/**
 * @version    4.1.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

/**
 * this file manages the js script for adding/removing attachements in event
 */
// window.addEvent('domready', function() {
jQuery(document).ready(function ($) {

    $('.attach-field').on('change', addattach);
    $('.clear-attach-field').on('click', clearattach);

    $('.attach-remove').on('click', function (event) {
        var event = event || window.event;
        // $(event.target).style.cursor = 'wait'; /* indicate server request */
        // $(event.target).style.cursor = 'wait'; /* indicate server request */
        $(this).css({'cursor': 'wait'})
        var clickednode = event.target;
        if (!clickednode.hasAttribute('id')) {
            clickednode = $(this).parent();
        }
        var url = '';
        // var pos = clickednode.id.indexOf(':');
        var pos = $(this).attr('id').indexOf(':');
        if (pos >= 0) {
            // var id = clickednode.id.substring(13, pos);
            // var token = clickednode.id.substr(pos+1);
            var id = $(this).attr('id').substring(13, pos);
            var token = $(this).attr('id').substr(pos + 1);
            url = 'index.php?option=com_jem&task=ajaxattachremove&format=raw&id=' + id + '&' + token + '=1';
        } else {
            // var id = clickednode.id.substr(13);
            var id = $(this).attr('id').substr(13);
            url = 'index.php?option=com_jem&task=ajaxattachremove&format=raw&id=' + id;
        }

        // var theAjax = new Request( {
        // 	url : url,
        // 	method: 'post',
        // 	postBody : ''
        // 	});

        // theAjax.addEventListener('onSuccess', function(response) {
        // 	/* server sends 1 on success, 0 on error */
        // 	if (response.indexOf('1') > -1) {
        // 		$(clickednode).getParent().getParent().dispose();
        // 	} else {
        // 		$(clickednode).style.cursor = 'not-allowed'; /* remove failed - how to show? */
        // 	}
        // }.bind(this));
        // theAjax.send();

        $.ajax({
            url: url,
            method: 'post',
            data: '',
            success: function (response) {

                if (response.indexOf('1') > -1) {
                    // $(clickednode).getParent().getParent().dispose();
                    $(clickednode).parent().parent().remove();
                } else {
                    // $(clickednode).style.cursor = 'not-allowed'; /* remove failed - how to show? */
                    $(clickednode).css({'cursor': 'not-allowed'})
                }
            }
        })
    });
});

function addattach() {
    // var tbody = $('#el-attachments').getElement('tbody');
    var tbody = $('#el-attachments tbody');
    // var rows = tbody.getElements('tr');
    var rows = tbody.find('tr');
    var emptyRows = [];

    /* do we have empty rows? */
    for (var i = 0; i < rows.length; i++) {
        // var af = rows[i].getElement('.attach-field');
        var af = $(rows[i]).find('.attach-field')[0];
        if (af && !(af.files.length > 0)) {
            emptyRows.push(af);
            break; /* one is enough, so we can break */
        }
    }


    /* if not create one */
    if (emptyRows.length < 1) {
        var row = $(rows[rows.length - 1]).clone();
        // row.getElement('.attach-field').on('change', addattach).value = '';
        // row.getElement('.clear-attach-field').on('click', clearattach).value = '';
        row.find('.attach-field').on('change', addattach).val('');
        row.find('.clear-attach-field').on('click', clearattach).val('');
        // row.inject(tbody);
        tbody.append(row);
    }
}

function clearattach(event) {
    var event = event || window.event;

    // var grandpa = $(event.target).getParent().getParent();
    var grandpa = $(this).parent().parent();
    // var af = grandpa.getElement('.attach-field');
    var af = grandpa.find('.attach-field')[0];
    if (af) af.value = '';
    // var an = grandpa.getElement('.attach-name');
    var an = grandpa.find('.attach-name')[0];
    if (an) an.value = '';
    // var ad = grandpa.getElement('.attach-desc');
    var ad = grandpa.find('.attach-desc')[0];
    if (ad) ad.value = '';
}
