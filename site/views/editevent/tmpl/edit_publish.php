<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;

$max_custom_fields = $this->settings->get('global_editevent_maxnumcustomfields', -1); // default to All
?>
<!--START PUBLISHING FIELDSET -->
<fieldset class="adminform">
    <legend><?php echo Text::_('COM_JEM_EDITEVENT_PUBLISH_TAB'); ?></legend>
    <dl class="jem-dl">

        <dt><?php echo $this->form->getLabel('published'); ?></dt>
        <dd><?php echo $this->form->getInput('published'); ?></dd>
        <dt><?php echo $this->form->getLabel('event_status'); ?></dt>
        <dd><?php echo $this->form->getInput('event_status'); ?></dd>
        <dt><?php echo $this->form->getLabel('ticket_availability'); ?></dt>
        <dd><?php echo $this->form->getInput('ticket_availability'); ?></dd>
        <dt><?php echo $this->form->getLabel('featured'); ?></dt>
        <dd><?php echo $this->form->getInput('featured'); ?></dd>
        <dt><?php echo $this->form->getLabel('publish_up'); ?></dt>
        <dd><?php echo $this->form->getInput('publish_up'); ?></dd>
        <dt><?php echo $this->form->getLabel('publish_down'); ?></dt>
        <dd><?php echo $this->form->getInput('publish_down'); ?></dd>
        <dt><?php echo $this->form->getLabel('access'); ?></dt>
        <dd><?php
            echo HTMLHelper::_('select.genericlist', $this->access, 'jform[access]',
                array('list.attr' => ' class="form-select inputbox" size="1"', 'list.select' => $this->item->access, 'option.attr' => 'disabled', 'id' => 'access'));
            ?>
        </dd>

    </dl>
</fieldset>


<!-- START META FIELDSET -->
<fieldset class="">
    <legend><?php echo Text::_('COM_JEM_META_HANDLING'); ?></legend>
    <div class="formelm-area">
        <input class="inputbox btn" type="button" onclick="insert_keyword('[title]')" value="<?php echo Text::_ ( 'COM_JEM_TITLE' );    ?>" />
        <input class="inputbox btn" type="button" onclick="insert_keyword('[a_name]')" value="<?php    echo Text::_ ( 'COM_JEM_VENUE' );?>" />
        <input class="inputbox btn" type="button" onclick="insert_keyword('[categories]')" value="<?php    echo Text::_ ( 'COM_JEM_CATEGORIES' );?>" />
        <input class="inputbox btn" type="button" onclick="insert_keyword('[dates]')" value="<?php echo Text::_ ( 'COM_JEM_DATE' );?>" />
        <input class="inputbox btn" type="button" onclick="insert_keyword('[times]')" value="<?php echo Text::_ ( 'COM_JEM_TIME' );?>" />
        <input class="inputbox btn" type="button" onclick="insert_keyword('[enddates]')" value="<?php echo Text::_ ( 'COM_JEM_ENDDATE' );?>" />
        <input class="inputbox btn" type="button" onclick="insert_keyword('[endtimes]')" value="<?php echo Text::_ ( 'COM_JEM_ENDTIME' );?>" />
        <br><br>
        <label for="meta_keywords">
            <?php echo Text::_('COM_JEM_META_KEYWORDS').':';?>
        </label>
        <?php
        if (! empty ( $this->item->meta_keywords )) {
            $meta_keywords = $this->item->meta_keywords;
        } else {
            $meta_keywords = $this->jemsettings->meta_keywords;
        }
        ?>
        <textarea class="inputbox" name="meta_keywords" id="meta_keywords" rows="5" cols="40" maxlength="150" onfocus="get_inputbox('meta_keywords')" onblur="change_metatags()"><?php echo $meta_keywords; ?></textarea>
    </div>
    <div class="formelm-area">
        <label for="meta_description">
            <?php echo Text::_ ( 'COM_JEM_META_DESCRIPTION' ) . ':';?>
        </label>
        <?php
        if (! empty ( $this->item->meta_description )) {
            $meta_description = $this->item->meta_description;
        } else {
            $meta_description = $this->jemsettings->meta_description;
        }
        ?>
        <textarea class="inputbox" name="meta_description" id="meta_description" rows="5" cols="40" maxlength="200"    onfocus="get_inputbox('meta_description')" onblur="change_metatags()"><?php echo $meta_description;?></textarea>
    </div>
    <!-- include the metatags end-->

    <script>
        <!--
        starter("<?php
            echo Text::_ ( 'COM_JEM_META_ERROR' );
            ?>");    // window.onload is already in use, call the function manualy instead
        -->
    </script>
</fieldset>
<!--  END META FIELDSET -->

