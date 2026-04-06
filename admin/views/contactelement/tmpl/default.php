<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;

$function = Factory::getApplication()->input->getCmd('function', 'jSelectContact');
?>

<form action="index.php?option=com_jem&amp;view=contactelement&amp;tmpl=component" method="post" name="adminForm" id="adminForm">

    <table class="adminform">
        <tr>
            <td style="width: 100%;">
                <?php echo Text::_('COM_JEM_SEARCH').' '.$this->lists['filter']; ?>
                <input type="text" name="filter_search" id="filter_search" value="<?php echo htmlspecialchars($this->lists['search'], ENT_QUOTES, 'UTF-8'); ?>" class="text_area" onChange="document.adminForm.submit();" />
                <button class="buttonfilter" type="submit"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
                <button class="buttonfilter" type="button" onclick="document.getElementById('filter_search').value='';this.form.submit();"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></button>

                <button class="buttonfilter" type="button" onclick="jemGetSelectedContacts();" style="background-color: #28a745; color: white; font-weight: bold; margin-left: 10px;">
                    <?php echo Text::_('COM_JEM_SELECT_CHECKED'); ?> (Confirmar Selección)
                </button>

                <button class="buttonfilter" type="button" onclick="if (window.parent) window.parent.<?php echo $this->escape($function);?>('', '<?php echo Text::_('COM_JEM_SELECTCONTACT') ?>');"><?php echo Text::_('COM_JEM_NOCONTACT')?></button>
            </td>
        </tr>
    </table>

    <table class="table table-striped" id="articleList">
        <thead>
        <tr>
            <th style="width: 20px" class="center">
                <input type="checkbox" name="checkall-toggle" value="" title="<?php echo Text::_('JGLOBAL_CHECK_ALL'); ?>" onclick="if (window.Joomla) { Joomla.checkAll(this); } else { /* fallback simple */ var cbs = document.getElementsByName('cid[]'); for(var i=0; i<cbs.length; i++) cbs[i].checked = this.checked; }" />
            </th>
            <th style="width: 7px" class="center"><?php echo Text::_('COM_JEM_NUM'); ?></th>
            <th style="text-align: left;" class="title"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_NAME', 'con.name', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
            <th style="text-align: left;" class="title"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_ADDRESS', 'con.address', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
            <th style="text-align: left;" class="title"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_CITY', 'con.suburb', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
            <th style="text-align: left;" class="title"><?php echo HTMLHelper::_('grid.sort', 'COM_JEM_STATE', 'con.state', $this->lists['order_Dir'], $this->lists['order'] ); ?></th>
            <th style="text-align: left;" class="title"><?php echo Text::_('COM_JEM_EMAIL'); ?></th>
            <th style="text-align: left;" class="title"><?php echo Text::_('COM_JEM_TELEPHONE'); ?></th>
        </tr>
        </thead>

        <tfoot>
        <tr>
            <td colspan="12">
                <?php echo (method_exists($this->pagination, 'getPaginationLinks') ? $this->pagination->getPaginationLinks() : $this->pagination->getListFooter()); ?>
            </td>
        </tr>
        </tfoot>

        <tbody>
        <?php foreach ($this->rows as $i => $row) : ?>
            <tr class="row<?php echo $i % 2; ?>">
                <td class="center">
                    <input type="checkbox" id="cb<?php echo $i; ?>" name="cid[]" value="<?php echo $row->id; ?>" data-name="<?php echo $this->escape(addslashes($row->name)); ?>" />
                </td>
                <td class="center"><?php echo $this->pagination->getRowOffset( $i ); ?></td>
                <td style="text-align: left;">
                <span <?php echo JEMOutput::tooltip(Text::_('COM_JEM_SELECT'), $row->name, 'editlinktip'); ?>>
                    <a style="cursor:pointer;" onclick="if (window.parent) window.parent.<?php echo $this->escape($function);?>('<?php echo $row->id; ?>', '<?php echo $this->escape(addslashes($row->name)); ?>');">
                        <?php echo $this->escape($row->name); ?>
                    </a>
                </span>
                </td>
                <td style="text-align: left;"><?php echo $this->escape($row->address); ?></td>
                <td style="text-align: left;"><?php echo $this->escape($row->suburb); ?></td>
                <td style="text-align: left;"><?php echo $this->escape($row->state); ?></td>
                <td style="text-align: left;"><?php echo $this->escape($row->email_to); ?></td>
                <td style="text-align: left;"><?php echo $this->escape($row->telephone); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <input type="hidden" name="task" value="" />
    <input type="hidden" name="function" value="<?php echo $this->escape($function); ?>" />
    <input type="hidden" name="filter_order" value="<?php echo $this->lists['order']; ?>" />
    <input type="hidden" name="filter_order_Dir" value="<?php echo $this->lists['order_Dir']; ?>" />
</form>

<script type="text/javascript">
    /**
     * Recolecta los IDs y nombres de los contactos seleccionados
     * y los envía al campo del formulario principal.
     */
    function jemGetSelectedContacts() {
        var checkboxes = document.getElementsByName('cid[]');
        var selectedIds = [];
        var selectedNames = [];

        for (var i = 0; i < checkboxes.length; i++) {
            if (checkboxes[i].checked) {
                selectedIds.push(checkboxes[i].value);
                // Obtenemos el nombre desde el atributo personalizado data-name
                selectedNames.push(checkboxes[i].getAttribute('data-name'));
            }
        }

        if (selectedIds.length > 0) {
            if (window.parent) {
                // Unimos los IDs con comas para la DB y los nombres para el input visual
                var idsString = selectedIds.join(',');
                var namesString = selectedNames.join(', ');

                // Ejecutamos la función de retorno en la ventana padre
                window.parent.<?php echo $this->escape($function); ?>(idsString, namesString);
            }
        } else {
            alert("<?php echo Text::_('JLIB_HTML_PLEASE_MAKE_A_SELECTION_FROM_THE_LIST'); ?>");
        }
    }
</script>