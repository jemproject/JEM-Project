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

$articleAutoInfo = htmlspecialchars(Text::_('COM_JEM_EVENT_ARTICLE_AUTO_INFO'), ENT_QUOTES, 'UTF-8');
?>

<fieldset class="adminform">
    <legend><?php echo Text::_('COM_JEM_ADVANCED'); ?></legend>
    <dl class="jem-dl">

        <dt><?php echo $this->form->getLabel('access'); ?></dt>
        <dd><?php
            echo HTMLHelper::_(
                'select.genericlist',
                $this->access,
                'jform[access]',
                array('list.attr' => ' class="form-select inputbox" size="1"', 'list.select' => $this->item->access, 'option.attr' => 'disabled', 'id' => 'access')
            );
            ?>
        </dd>
        <dt><?php echo $this->form->getLabel('published'); ?></dt>
        <dd><?php echo $this->form->getInput('published'); ?></dd>
        <dt><?php echo $this->form->getLabel('event_status'); ?></dt>
        <dd><?php echo $this->form->getInput('event_status'); ?></dd>
        <dt><?php echo $this->form->getLabel('ticket_availability'); ?></dt>
        <dd><?php echo $this->form->getInput('ticket_availability'); ?></dd>
        <dt><?php echo $this->form->getLabel('publish_up'); ?></dt>
        <dd><?php echo $this->form->getInput('publish_up'); ?></dd>
        <dt><?php echo $this->form->getLabel('publish_down'); ?></dt>
        <dd><?php echo $this->form->getInput('publish_down'); ?></dd>
        <?php if ($this->form->getField('article_id')) : ?>
            <dt><?php echo $this->form->getLabel('article_id'); ?></dt>
            <dd><?php echo $this->form->getInput('article_id'); ?></dd>
            <dt><?php echo $this->form->getLabel('create_article'); ?></dt>
            <dd>
                <span class="jem-inline-info-control">
                    <?php echo $this->form->getInput('create_article'); ?>
                    <span class="jem-info-tooltip hasTooltip" title="<?php echo $articleAutoInfo; ?>" aria-label="<?php echo $articleAutoInfo; ?>">
                        <svg aria-hidden="true" viewBox="0 0 16 16" focusable="false">
                            <circle cx="8" cy="8" r="7"></circle>
                            <path d="M8 7v4"></path>
                            <path d="M8 4.75h.01"></path>
                        </svg>
                    </span>
                </span>
            </dd>
            <dt><?php echo $this->form->getLabel('article_target_category_id'); ?></dt>
            <dd><?php echo $this->form->getInput('article_target_category_id'); ?></dd>
        <?php endif; ?>
    </dl>
</fieldset>
<!-- START META FIELDSET -->
<fieldset class="adminform">
    <legend><?php echo Text::_('COM_JEM_METADATA'); ?></legend>
    <div class="formelm-area">
        <input class="inputbox btn btn-secondary" type="button" onclick="insert_keyword('[title]')" value="<?php echo Text::_('COM_JEM_TITLE');    ?>" />
        <input class="inputbox btn btn-secondary" type="button" onclick="insert_keyword('[a_name]')" value="<?php echo Text::_('COM_JEM_VENUE'); ?>" />
        <input class="inputbox btn btn-secondary" type="button" onclick="insert_keyword('[categories]')" value="<?php echo Text::_('COM_JEM_CATEGORIES'); ?>" />
        <input class="inputbox btn btn-secondary" type="button" onclick="insert_keyword('[dates]')" value="<?php echo Text::_('COM_JEM_DATE'); ?>" />
        <input class="inputbox btn btn-secondary" type="button" onclick="insert_keyword('[times]')" value="<?php echo Text::_('COM_JEM_TIME'); ?>" />
        <input class="inputbox btn btn-secondary" type="button" onclick="insert_keyword('[enddates]')" value="<?php echo Text::_('COM_JEM_ENDDATE'); ?>" />
        <input class="inputbox btn btn-secondary" type="button" onclick="insert_keyword('[endtimes]')" value="<?php echo Text::_('COM_JEM_ENDTIME'); ?>" />
        <br>
        <br>
        <?php
        if (!empty($this->item->meta_keywords)) {
            $meta_keywords = $this->item->meta_keywords;
        } else {
            $meta_keywords = $this->jemsettings->meta_keywords;
        }
        ?>
        <dl class="jem-dl">
            <dt>
                <label for="meta_keywords">
                    <?php echo Text::_('COM_JEM_META_KEYWORDS') . ':'; ?>
                </label>
            </dt>
            <dd><textarea class="inputbox" name="meta_keywords" id="meta_keywords" rows="5" cols="40" maxlength="150" onfocus="get_inputbox('meta_keywords')" onblur="change_metatags()"><?php echo $meta_keywords; ?></textarea></dd>
        </dl>
    </div>
    <div class="formelm-area">
        <?php
        if (!empty($this->item->meta_description)) {
            $meta_description = $this->item->meta_description;
        } else {
            $meta_description = $this->jemsettings->meta_description;
        }
        ?>
        <dl class="jem-dl">
            <dt>
                <label for="meta_description">
                    <?php echo Text::_('COM_JEM_META_DESCRIPTION') . ':'; ?>
                </label>
            </dt>
            <dd><textarea class="inputbox" name="meta_description" id="meta_description" rows="5" cols="40" maxlength="200" onfocus="get_inputbox('meta_description')" onblur="change_metatags()"><?php echo $meta_description; ?></textarea></dd>
        </dl>
    </div>
    <!-- include the metatags end-->

    <script>
        <!--
        starter("<?php
            echo Text::_('COM_JEM_META_ERROR');
            ?>"); // window.onload is already in use, call the function manualy instead
        -->
    </script>

</fieldset>
