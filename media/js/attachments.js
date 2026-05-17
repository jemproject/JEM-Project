/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

/**
 * this file manages the js script for adding/removing attachements in event
 */
// window.addEvent('domready', function() {
jQuery(document).ready(function ($) {

    $(document).on('change', '.attach-field', addattach);
    $(document).on('click', '.clear-attach-field', clearattach);
    $(document).on('click', '.attachment-add', addAttachmentRow);
    $(document).on('click', '.attachment-remove-row', removeAttachmentRow);
    $(document).on('click', '.attachment-move-up', function (event) {
        moveAttachmentRow(event, -1);
    });
    $(document).on('click', '.attachment-move-down', function (event) {
        moveAttachmentRow(event, 1);
    });

    $(document).on('click', '.attach-remove', function (event) {
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
        // url : url,
        // method: 'post',
        // postBody : ''
        // });

        // theAjax.addEventListener('onSuccess', function(response) {
        // /* server sends 1 on success, 0 on error */
        // if (response.indexOf('1') > -1) {
        // $(clickednode).getParent().getParent().dispose();
        // } else {
        // $(clickednode).style.cursor = 'not-allowed'; /* remove failed - how to show? */
        // }
        // }.bind(this));
        // theAjax.send();

        $.ajax({
            url: url,
            method: 'post',
            data: '',
            success: function (response) {

                if (response.indexOf('1') > -1) {
                    $(clickednode).closest('tr, .jem-attachment-card').remove();
                    updateAttachmentOrdering();
                } else {
                    // $(clickednode).style.cursor = 'not-allowed'; /* remove failed - how to show? */
                    $(clickednode).css({'cursor': 'not-allowed'})
                }
            }
        })
    });
});

function addattach() {
    updateAttachmentOrdering();
}

function addAttachmentRow(event) {
    var tbody = $('#el-attachments tbody');
    appendAttachmentRow(tbody);
    updateAttachmentOrdering();
}

function appendAttachmentRow(tbody) {
    var template = tbody.find('tr.jem-attachment-template-row').last();

    if (!template.length) {
        return;
    }

    var row = template.clone();
    row.removeClass('jem-attachment-template-row d-none hidden');
    row.removeAttr('hidden');
    row.attr('aria-hidden', 'false');
    row.find(':input').prop('disabled', false);
    row.find('.attach-field').val('');
    row.find('.attach-name').val('');
    row.find('.attach-desc').val('');
    row.find('.attachment-order').val('');
    row.find('.attachment-published').val('1');
    row.insertBefore(template);
}

function removeAttachmentRow(event) {
    var row = $(event.target).closest('tr, .jem-attachment-card');
    row.remove();

    updateAttachmentOrdering();
}

function moveAttachmentRow(event, direction) {
    var row = $(event.target).closest('tr, .jem-attachment-card');

    if (direction < 0) {
        var previous = row.prevAll('tr:not(.jem-attachment-template-row), .jem-attachment-card:not(.jem-attachment-template-row)').first();
        if (previous.length) {
            row.insertBefore(previous);
        }
    } else {
        var next = row.nextAll('tr:not(.jem-attachment-template-row), .jem-attachment-card:not(.jem-attachment-template-row)').first();
        if (next.length) {
            row.insertAfter(next);
        }
    }

    updateAttachmentOrdering();
}

function updateAttachmentOrdering() {
    $('#el-attachments tbody').find('tr:not(.jem-attachment-template-row), .jem-attachment-card:not(.jem-attachment-template-row)').each(function (index) {
        $(this).find('.attachment-order').val(index);
    });
}

function clearattach(event) {
    var event = event || window.event;

    // var grandpa = $(event.target).getParent().getParent();
    var grandpa = $(event.target).closest('tr, .jem-attachment-card');
    clearAttachmentRow(grandpa);
}

function clearAttachmentRow(grandpa) {
    var af = grandpa.find('.attach-field')[0];
    if (af) af.value = '';
    var an = grandpa.find('.attach-name')[0];
    if (an) an.value = '';
    var ad = grandpa.find('.attach-desc')[0];
    if (ad) ad.value = '';
}
