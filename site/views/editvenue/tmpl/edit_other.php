<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

require_once JPATH_SITE . '/components/com_jem/classes/customfields.class.php';

$max_custom_fields = $this->settings->get('global_editvenue_maxnumcustomfields', -1); // default to All
?>

<!-- CUSTOM FIELDS -->
<?php if ($max_custom_fields != 0) : ?>
    <fieldset class="panelform">
        <legend><?php echo Text::_('COM_JEM_EDITVENUE_CUSTOMFIELDS'); ?></legend>
        <ul class="adminformlist jem-customfields-edit">
            <?php
            $fields = array();
            foreach ($this->form->getFieldset('custom') as $field) {
                $fields[$field->fieldname] = $field;
            }
            $orderedFields = JemCustomFields::getOrderedFields('venue', 'frontend_edit');
            if ($max_custom_fields < 0) :
                $max_custom_fields = count($orderedFields);
            endif;
            $cnt = 0;
            foreach($orderedFields as $fieldName) :
                if (empty($fields[$fieldName])) :
                    continue;
                endif;
                $field = $fields[$fieldName];
                if (++$cnt <= $max_custom_fields) :
                    ?>
                    <li>
                        <span class="jem-customfield-label"><?php echo $field->label; ?></span>
                        <span class="jem-customfield-input"><?php echo $field->input; ?></span>
                    </li>
                    <?php
                endif;
            endforeach;
            ?>
        </ul>
    </fieldset>
<?php endif; ?>

