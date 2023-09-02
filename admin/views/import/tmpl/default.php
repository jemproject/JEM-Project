<?php
/**
 * @version    4.1.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

?>
<?php if($this->progress->step > 1) : ?>
    <meta http-equiv="refresh" content="1; url=index.php?option=com_jem&amp;view=import&amp;task=import.eventlistimport&amp;step=<?php
    echo $this->progress->step; ?>&amp;table=<?php echo $this->progress->table; ?>&amp;current=<?php
    echo $this->progress->current; ?>&amp;total=<?php echo $this->progress->total; ?>" />
<?php endif; ?>

<?php if (isset($this->sidebar)) : ?>
<div id="j-sidebar-container" class="span2">
    <?php echo $this->sidebar; ?>
</div>
<div id="j-main-container" class="span10">
    <?php endif; ?>

    <div id="j-main-container" class="j-main-container">
        <form action="index.php" method="post" name="adminForm" enctype="multipart/form-data" id="adminForm">
            <div>
                <strong><?php echo Text::_('COM_JEM_IMPORT_INSTRUCTIONS') ?></strong><br /><br />
                <?php echo Text::_("COM_JEM_IMPORT_INSTRUCTIONS_DESC"); ?><br />
                <?php echo Text::_("COM_JEM_IMPORT_COLUMNNAMESVENUES"); ?><br />
                <?php echo Text::_("COM_JEM_IMPORT_FIRSTROW"); ?><br />
            </div>
            <hr />
            <div>
                <fieldset class="adminform">
                    <legend><strong><?php echo mb_strtoupper(Text::_('COM_JEM_IMPORT_VENUES'));?></strong></legend>
                    <?php echo Text::_("COM_JEM_IMPORT_VENUES_DESC"); ?><br />
                    <a onclick="return showblock(this);" style="cursor: pointer; color:blue;"> <?php echo Text::_("COM_JEM_IMPORT_SHOW_VENUE_COLUMNS");?></a><div style="display: none;"><div style="background-color:silver;border:1px solid #808080"><?php echo implode(", ",$this->venuefields); ?></div></div><br />

                    <div style="display:inline-block"><label for="replace_venues"><?php echo Text::_('COM_JEM_IMPORT_REPLACEIFEXISTS').':'; ?></label></div>
                    <div style="display:inline-block"><?php echo HTMLHelper::_('select.booleanlist', 'replace_venues', 'class="inputbox"', 0); ?></div><br/><br />

                    <label for="file"><?php echo Text::_('COM_JEM_IMPORT_SELECTCSV').':'; ?></label>
                    <input type="file" id="venue-file-upload" accept="text/*" name="Filevenues" />
                    <input type="submit" id="venue-file-upload-submit" value="<?php echo Text::_('COM_JEM_IMPORT_START'); ?>" onclick="document.getElementById('task1').value='import.csvvenuesimport';return true;"/>
                </fieldset>
            </div>
            <hr />
            <div>
                <fieldset class="adminform">
                    <legend><strong><?php echo mb_strtoupper(Text::_('COM_JEM_IMPORT_CATEGORIES'));?></strong></legend>
                    <?php echo Text::_("COM_JEM_IMPORT_CATEGORIES_DESC"); ?><br />
                    <a onclick="return showblock(this);" style="cursor: pointer; color:blue;"> <?php echo Text::_("COM_JEM_IMPORT_SHOW_CATEGORY_COLUMNS");?></a><div style="display: none;"><div style="background-color:silver;border:1px solid #808080"><?php echo implode(", ",$this->catfields); ?></div></div><br />

                    <div style="display:inline-block"><label for="replace_categories"><?php echo Text::_('COM_JEM_IMPORT_REPLACEIFEXISTS').':'; ?></label></div>
                    <div style="display:inline-block"><?php echo HTMLHelper::_('select.booleanlist', 'replace_categories', 'class="inputbox"', 0); ?></div><br/><br />

                    <label for="file"><?php echo Text::_('COM_JEM_IMPORT_SELECTCSV').':'; ?></label>
                    <input type="file" id="cat-file-upload" accept="text/*" name="Filecategories" />
                    <input type="submit" id="cat-file-upload-submit" value="<?php echo Text::_('COM_JEM_IMPORT_START'); ?>" onclick="document.getElementById('task1').value='import.csvcategoriesimport';return true;"/>

                </fieldset>
                <div class="clr"></div>
            </div>
            <hr />
            <div>
                <fieldset class="adminform">
                    <legend><strong><?php echo mb_strtoupper(Text::_('COM_JEM_IMPORT_EVENTS'));?></strong></legend>
                    <?php echo Text::_("COM_JEM_IMPORT_EVENTS_DESC"); ?><br />
                    <a onclick="return showblock(this);" style="cursor: pointer; color:blue;"> <?php echo Text::_("COM_JEM_IMPORT_SHOW_EVENT_COLUMNS");?></a><div style="display: none;"><div style="background-color:silver;border:1px solid #808080"><?php echo implode(", ",$this->eventfields) . ',categories'; ?></div></div><br />

                    <div style="display:inline-block"><label for="replace_events"><?php echo Text::_('COM_JEM_IMPORT_REPLACEIFEXISTS').':'; ?></label></div>
                    <div style="display:inline-block"><?php echo HTMLHelper::_('select.booleanlist', 'replace_events', 'class="inputbox"', 0); ?></div><br /><br/>

                    <label for="file"><?php echo Text::_('COM_JEM_IMPORT_SELECTCSV').':'; ?></label>
                    <input type="file" id="event-file-upload" accept="text/*" name="Fileevents" />
                    <input type="submit" id="event-file-upload-submit" value="<?php echo Text::_('COM_JEM_IMPORT_START'); ?>" onclick="document.getElementById('task1').value='import.csveventimport';return true;"/>
                </fieldset>
                <div class="clr"></div>
            </div>
            <hr />
            <div>
                <fieldset class="adminform">
                    <legend><strong><?php echo mb_strtoupper(Text::_('COM_JEM_IMPORT_CAT_EVENTS'));?></strong></legend>
                    <?php echo Text::_("COM_JEM_IMPORT_CAT_EVENTS_DESC"); ?><br />
                    <a onclick="return showblock(this);" style="cursor: pointer; color:blue;"> <?php echo Text::_("COM_JEM_IMPORT_SHOW_CATEVENT_COLUMNS");?></a><div style="display: none;"><div style="background-color:silver;border:1px solid #808080"><?php echo implode(", ",$this->cateventsfields); ?></div></div><br />

                    <div style="display:inline-block"><label for="replace_catevents"><?php echo Text::_('COM_JEM_IMPORT_REPLACEIFEXISTS').':'; ?></label></div>
                    <div style="display:inline-block"><?php echo HTMLHelper::_('select.booleanlist', 'replace_catevents', 'class="inputbox"', 0); ?></div><br /><br/>

                    <label for="file"><?php echo Text::_('COM_JEM_IMPORT_SELECTCSV').':'; ?></label>
                    <input type="file" id="catevents-file-upload" accept="text/*" name="Filecatevents" />
                    <input type="submit" id="catevents-file-upload-submit" value="<?php echo Text::_('COM_JEM_IMPORT_START'); ?>" onclick="document.getElementById('task1').value='import.csvcateventsimport';return true;"/>
                </fieldset>
                <div class="clr"></div>
            </div>

            <?php echo HTMLHelper::_('form.token'); ?>
            <input type="hidden" name="option" value="com_jem" />
            <input type="hidden" name="view" value="import" />
            <input type="hidden" name="controller" value="import" />
            <input type="hidden" name="task" id="task1" value="" />
        </form>
    </div>
</div>

<script type='text/JavaScript'>
    function showblock(blockcontent) {
        var c=blockcontent.nextSibling;
        if(c.style.display=='none') {
            c.style.display='block';
        } else {
            c.style.display='none';
        }
        return false;
    }
</script>