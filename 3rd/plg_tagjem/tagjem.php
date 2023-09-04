<?php
/**
 * @version    4.1.0
 * @JEM Tag Plugin for AcyMailing 5.x
 * @copyright  (C) 2014 Ghost Art digital media.
 * @copyright  (C) 2013 - 2023 joomlaeventmanager.net. All rights reserved.
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 * Based on Eventlist tag and JEM specific code by JEM Community
 */

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\String\StringHelper;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Language\Text;

include_once(JPATH_SITE.'/components/com_jem/helpers/helper.php');
include_once(JPATH_SITE.'/components/com_jem/classes/image.class.php');
include_once(JPATH_SITE.'/components/com_jem/classes/output.class.php');

class plgAcymailingTagjem extends CMSPlugin
{

    protected $searchFields = array('a.id', 'a.title', 'a.alias', 'a.introtext', 'l.venue');
    protected $selectedFields = array('a.*', 'l.venue');

    //public function __construct(&$subject, $config)
    function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);
        if (!isset($this->params)) {

            $plugin = PluginHelper::getPlugin('acymailing', 'tagjem');

            //$this->params = new JRegistry($plugin->params);
            $this->params = new acyParameter($plugin->params);
        }
		$this->loadLanguage();
        $this->loadLanguage('com_jem', JPATH_ADMINISTRATOR.'/components/com_jem');
    }

    //! public function acymailing_getPluginType()
    function onAcymailing_getPluginType(){
        return $this->acymailing_getPluginType();
    }

    function acymailing_getPluginType(){
        $onePlugin = new stdClass();
        $onePlugin->name = Text::_('JOOMEXT_EVENT'). ' <small>(JEM)</small>';
        $onePlugin->function = 'acymailingtagjem_show';
        $onePlugin->help = 'plugin-tagjem';

        return $onePlugin;
    }

    //public function acymailingtagjem_show()
    function onAcymailingtagjem_show(){

        return $this->acymailingtagjem_show();
    }

    function acymailingtagjem_show(){
        $config = acymailing_config();
        if ($config->get('version') < '4.0.0') {
            acymailing_display('Please download and install the latest AcyMailing version otherwise this plugin will NOT work', 'error');
            return;
        }

        $app = Factory::getApplication();

        $pageInfo = new stdClass();
        $pageInfo->filter = new stdClass();
        $pageInfo->filter->order = new stdClass();
        $pageInfo->limit = new stdClass();
        $pageInfo->elements = new stdClass();

        $paramBase = ACYMAILING_COMPONENT.'.tagjem';
        $pageInfo->filter->order->value = $app->getUserStateFromRequest($paramBase.".filter_order", 'filter_order', 'a.id', 'cmd');
        $pageInfo->filter->order->dir   = $app->getUserStateFromRequest($paramBase.".filter_order_Dir", 'filter_order_Dir', 'desc', 'word');
        if(strtolower($pageInfo->filter->order->dir) !== 'desc') $pageInfo->filter->order->dir = 'asc';
        $pageInfo->search = $app->getUserStateFromRequest($paramBase.".search", 'search', '', 'string');
        $pageInfo->search = StringHelper::strtolower($pageInfo->search);
        $pageInfo->filter_cat = $app->getUserStateFromRequest($paramBase.".filter_cat", 'filter_cat', '', 'int');
        $pageInfo->featured = $app->getUserStateFromRequest($paramBase.".featured", 'featured', $this->params->get('show_featured', 0), 'int');
        $pageInfo->opendates = '0';
        $pageInfo->pict = $app->getUserStateFromRequest($paramBase.".pict", 'pict', $this->params->get('show_images', 1), 'string');
        $pageInfo->pictwidth = $app->getUserStateFromRequest($paramBase.".pictwidth", 'pictwidth', $this->params->get('img_width', 160), 'string');
        $pageInfo->pictheight = $app->getUserStateFromRequest($paramBase.".pictheight", 'pictheight', $this->params->get('img_height', 160), 'string');

        $pageInfo->limit->value = $app->getUserStateFromRequest($paramBase.'.list_limit', 'limit', $app->getCfg('list_limit'), 'int');
        $pageInfo->limit->start = $app->getUserStateFromRequest($paramBase.'.limitstart', 'limitstart', 0, 'int');

        $picts = array();
        $picts[] = JHtml::_('select.option', '1', Text::_('JOOMEXT_YES'));
        $picts[] = JHtml::_('select.option', 'resized', Text::_('PLG_TAGJEM_RESIZE'));
        $picts[] = JHtml::_('select.option', '0', Text::_('JOOMEXT_NO'));

        $yesno = array();
        $yesno[] = JHtml::_('select.option', '1', Text::_('JOOMEXT_YES'));
        $yesno[] = JHtml::_('select.option', '0', Text::_('JOOMEXT_NO'));

        $opendates = array();
        $opendates[] = JHtml::_('select.option', 'also', Text::_('COM_JEM_SHOW_OPENDATES_TOO'));
        $opendates[] = JHtml::_('select.option', 'only', Text::_('COM_JEM_SHOW_OPENDATES_ONLY'));
        $opendates[] = JHtml::_('select.option', '0', Text::_('JOOMEXT_NO'));

        $db = Factory::getContainer()->get('DatabaseDriver');

        if (!empty($pageInfo->search)) {
            $searchVal = '\'%'.acymailing_getEscaped($pageInfo->search, true).'%\'';
            $filters[] = implode(" LIKE $searchVal OR ", $this->searchFields)." LIKE $searchVal";
        }

        if(!empty($pageInfo->filter_cat)){
            $filters[] = "c.id = ".$pageInfo->filter_cat;
        }

        // we hide past events but we remove one day just to make sure we won't hide something we should not!
        // because it's a newsletter we focus on events starting in the future; already running (multi-day) events are not new
        // but if someone really needs to explicitely select a running event provide them on the list.
        if ($this->params->get('hide_past_events', '1') === '1') {
            $filters[] = '(IFNULL(a.enddates, a.dates) IS NULL OR IFNULL(a.enddates, a.dates) >= '.$db->Quote(date('Y-m-d', time() - 86400)) . ')';
        }

        $filters[] = 'a.published = 1'; // prevent unpublished and trashed events, but also archived
        $filters[] = 'c.published = 1'; // also limit to published categories

        $whereQuery = '';
        if (!empty($filters)) {
            $whereQuery = ' WHERE ('.implode(') AND (', $filters).')';
        }

        $query = 'SELECT SQL_CALC_FOUND_ROWS '.implode(',', $this->selectedFields).' FROM `#__jem_events` as a';
        $query .= ' LEFT JOIN `#__jem_venues` AS l ON l.id = a.locid';
        $query .= ' LEFT JOIN `#__jem_cats_event_relations` AS rel ON rel.itemid = a.id';
        $query .= ' LEFT JOIN `#__jem_categories` AS c ON c.id = rel.catid';
        if (!empty($whereQuery)) {
            $query .= $whereQuery;
        }

        $query .= ' GROUP BY a.id';

        if (!empty($pageInfo->filter->order->value)) {
            $query .= ' ORDER BY '.$pageInfo->filter->order->value.' '.$pageInfo->filter->order->dir;
            if ($pageInfo->filter->order->value === 'a.dates') {
                $query .= ', a.times '.$pageInfo->filter->order->dir;
            }
        }

        $db->setQuery($query, $pageInfo->limit->start, $pageInfo->limit->value);
        $rows = $db->loadObjectList();

        if (!empty($pageInfo->search)) {
            $rows = acymailing_search($pageInfo->search, $rows);
        }

        $db->setQuery('SELECT FOUND_ROWS()');
        $pageInfo->elements->total = $db->loadResult();
        $pageInfo->elements->page = count($rows);

        $db->setQuery('SELECT c.* FROM `#__jem_categories` as c WHERE c.published IN (0, 1) AND c.alias NOT LIKE "root" ORDER BY c.lft ASC');
        $categories = $db->loadObjectList('id');
        $categoriesValues = array();
        $categoriesValues[] = JHtml::_('select.option', '', Text::_('ACY_ALL'));
        foreach ($categories as $oneCat) {
            $categoriesValues[] = JHtml::_('select.option', $oneCat->id, str_repeat('-&nbsp;', $oneCat->level) . $oneCat->catname, ($oneCat->published != 1) ? array('disable' => true) : array());
        }

        // Before AcyMailing 5.0 we have to use Joomla's css classes.
        if ($config->get('version') < '5.0.0') {
            $class_options = 'adminform';
            $class_list    = 'adminlist';
        } else {
            $class_options = 'acymailing_table';
            $class_list    = 'acymailing_table';
        }

        $pagination = new Pagination($pageInfo->elements->total, $pageInfo->limit->start, $pageInfo->limit->value);

        $tabs = acymailing_get('helper.acytabs');
        echo $tabs->startPane('jem_tab');
        echo $tabs->startPanel(Text::_('JOOMEXT_EVENT'), 'jem_event');
        ?>
        <script type="text/javascript">
            <!--
            var selectedContents = new Array();
            function applyContent(contentid, rowClass){
                var tmp = selectedContents.indexOf(contentid)
                if(tmp != -1){
                    window.document.getElementById('content' + contentid).className = rowClass;
                    delete selectedContents[tmp];
                }else{
                    window.document.getElementById('content' + contentid).className = 'selectedrow';
                    selectedContents.push(contentid);
                }
                updateTag();
            }

            function updateTag()
            {
                var tag = '';
                var resizeinfo = '';

                for(var i = 0; i < document.adminForm.pict.length; i++){
                    if(document.adminForm.pict[i].checked){
                        resizeinfo += '| images: ' + document.adminForm.pict[i].value;
                        if(document.adminForm.pict[i].value == 'resized'){
                            document.getElementById('pictsize').style.display = '';
                            //	resizeinfo += '|images: ' + document.adminForm.pictwidth.value + 'x' + document.adminForm.pictheight.value;
                            if(document.adminForm.pictwidth.value) resizeinfo += '| imgwidth:' + document.adminForm.pictwidth.value;
                            if(document.adminForm.pictheight.value) resizeinfo += '| imgheight:' + document.adminForm.pictheight.value;
                        }else{
                            document.getElementById('pictsize').style.display = 'none';
                        }
                    }
                }

                var events = [];
                for(var i in selectedContents){
                    if(selectedContents[i] && !isNaN(i)){
                        events.push(selectedContents[i]);
                    }
                }

                if (events.length > 0) {
                    tag = '{jem: ' + events.join('-') + resizeinfo;
                    tag += '| template: ' + document.adminForm.templ.value;
                    tag += '}';
                }

                setTag(tag);
            }
            //-->
        </script>

        <div class="onelineblockoptions">
            <table class="<?php echo $class_options;?>" style="width:100%;">
                <tr id="format" class="acyplugformat">
                    <td style="vertical-align: baseline; width:25%;"><?php echo Text::_('PLG_TAGJEM_SHOW_IMAGES'); ?></td>
                    <td style="vertical-align: baseline; width:25%;"><?php echo JHtml::_('acyselect.radiolist', $picts, 'pict', 'size="1" onclick="updateTag();"', 'value', 'text', $pageInfo->pict); ?>
                    </td>
                    <td style="vertical-align: baseline; width:50%;" colspan="2">
						<span id="pictsize" <?php if($pageInfo->pict != 'resized') echo 'style="display:none;"'; ?>><!--br/--><?php echo Text::_('PLG_TAGJEM_IMAGE_WIDTH') ?>
							<input name="pictwidth" type="text" onchange="updateTag();" value="<?php echo $pageInfo->pictwidth; ?>" style="width:30px;"/>
							x <?php echo Text::_('PLG_TAGJEM_IMAGE_HEIGHT') ?>
							<input name="pictheight" type="text" onchange="updateTag();" value="<?php echo $pageInfo->pictheight; ?>" style="width:30px;"/>
						</span>
                    </td>
                </tr>
                <tr>
                    <td style="vertical-align: baseline; width:25%;"><?php echo Text::_('PLG_TAGJEM_TEMPLATE'); ?></td>
                    <td style="vertical-align: baseline; width:25%;">
                        <?php /* TODO: Collect templates dynamically. */ ?>
                        <select name="templ" size="1" onchange="updateTag();">
                            <option value="event">Event</option>
                            <option value="list">List</option>
                            <option value="summary">Summary</option>
                        </select>
                    </td>
                    <td style="vertical-align: baseline; width:25%;"></td>
                    <td style="vertical-align: baseline; width:25%;"></td>
                </tr>
            </table>
        </div>
        <div class="onelineblockoptions">
            <table class="acymailing_table_options">
                <tr>
                    <td style="width:100%;">
                        <?php acymailing_listingsearch($pageInfo->search); ?>
                    </td>
                    <td nowrap="nowrap">
                        <?php echo JHtml::_('select.genericlist', $categoriesValues, 'filter_cat', 'class="inputbox" size="1" onchange="document.adminForm.submit( );"', 'value', 'text', (int)$pageInfo->filter_cat); ?>
                    </td>
                </tr>
            </table>

            <table class="<?php echo $class_list; ?>" style="border-collapse: 1px; width:100%;">
                <thead>
                <tr>
                    <th class="title">
                    </th>
                    <th class="title">
                        <?php echo JHTML::_('grid.sort', Text::_('PLG_TAGJEM_TITLE'), 'a.title', $pageInfo->filter->order->dir, $pageInfo->filter->order->value); ?>
                    </th>
                    <th class="title">
                        <?php echo JHTML::_('grid.sort', Text::_('PLG_TAGJEM_DESCRIPTION'), 'a.introtext', $pageInfo->filter->order->dir, $pageInfo->filter->order->value); ?>
                    </th>
                    <th class="title">
                        <?php echo JHTML::_('grid.sort', Text::_('PLG_TAGJEM_DATE'), 'a.dates', $pageInfo->filter->order->dir, $pageInfo->filter->order->value); ?>
                    </th>
                    <th class="title">
                        <?php echo JHTML::_('grid.sort', Text::_('PLG_TAGJEM_VENUE'), 'l.venue', $pageInfo->filter->order->dir, $pageInfo->filter->order->value); ?>
                    </th>
                    <th class="title titleid">
                        <?php echo JHTML::_('grid.sort', Text::_('PLG_TAGJEM_ID'), 'a.id', $pageInfo->filter->order->dir, $pageInfo->filter->order->value); ?>
                    </th>
                </tr>
                </thead>
                <tfoot>
                <tr>
                    <td colspan="6">
                        <?php echo $pagination->getListFooter(); ?>
                        <?php echo $pagination->getResultsCounter(); ?>
                    </td>
                </tr>
                </tfoot>
                <tbody>
                <?php
                $k = 0;
                for ($i = 0, $a = count($rows); $i < $a; $i++) {
                    $row =& $rows[$i];
                    ?>
                    <tr id="content<?php echo $row->id?>" class="<?php echo "row$k"; ?>" style="cursor:pointer;"
                        onclick="applyContent(<?php echo $row->id.",'row$k'" ?>);" eventid="<?php echo $row->id ?>" >
                        <td class="acytdcheckbox"></td>
                        <td>
                            <?php echo $row->title; ?>
                        </td>
                        <td>
                            <?php echo strip_tags($row->introtext); ?>
                        </td>
                        <td align="center">
                            <?php echo JemOutput::formatShortDateTime($row->dates, $row->times, $row->enddates, $row->endtimes); ?>
                        </td>
                        <td>
                            <?php echo $row->venue; ?>
                        </td>
                        <td align="center">
                            <?php echo $row->id; ?>
                        </td>
                    </tr>
                    <?php
                    $k = 1 - $k;
                }
                ?>
                </tbody>
            </table>
        </div>

        <input type="hidden" name="boxchecked" value="0" />
        <input type="hidden" name="filter_order" value="<?php echo $pageInfo->filter->order->value; ?>" />
        <input type="hidden" name="filter_order_Dir" value="<?php echo $pageInfo->filter->order->dir; ?>" />
        <?php
        echo $tabs->endPanel();

        echo $tabs->startPanel(Text::_('UPCOMING_EVENTS'), 'jem_auto');
        $type = $app->input->request->getString('type');
        ?>
        <script type="text/javascript">
            <!--
            var selectedCat = new Array();
            function applyAutoEvent(catid,rowClass){
                if(selectedCat[catid]){
                    window.document.getElementById('event_cat'+catid).className = rowClass;
                    delete selectedCat[catid];
                }else{
                    window.document.getElementById('event_cat'+catid).className = 'selectedrow';
                    selectedCat[catid] = 'event';
                }

                updateAutoTag();
            }

            function updateAutoTag()
            {
                var tag = '';
                var resizeinfo = '';

                var cats = [];
                for(var icat in selectedCat){
                    if(selectedCat[icat] == 'event'){
                        cats.push(icat);
                    }
                }

                for(var i = 0; i < document.adminForm.pictauto.length; i++){
                    if(document.adminForm.pictauto[i].checked){
                        resizeinfo += '| images: ' + document.adminForm.pictauto[i].value;
                        if(document.adminForm.pictauto[i].value == 'resized'){
                            document.getElementById('pictsizeauto').style.display = '';
                            if(document.adminForm.pictwidthauto.value) resizeinfo += '| imgwidth:' + document.adminForm.pictwidthauto.value;
                            if(document.adminForm.pictheightauto.value) resizeinfo += '| imgheight:' + document.adminForm.pictheightauto.value;
                        }else{
                            document.getElementById('pictsizeauto').style.display = 'none';
                        }
                    }
                }

                if (cats.length > 0) {
                    tag += '{autojem:' + cats.join('-') + resizeinfo;
                    if(document.adminForm.min_article && document.adminForm.min_article.value != 0) {
                        tag += '| min: ' + document.adminForm.min_article.value;
                    }
                    if(document.adminForm.max_article && document.adminForm.max_article.value != 0) {
                        tag += '| max: ' + document.adminForm.max_article.value;
                    }
                    if(document.adminForm.delayevent && document.adminForm.delayevent.value > 0) {
                        tag += '| delay: ' + document.adminForm.delayevent.value;
                    }
                    if(document.adminForm.featured) {
                        tag += '| featured: ' + document.adminForm.featured.value;
                    }
                    tag += '| template: ' + document.adminForm.template.value;
                    tag += '| opendates: ' + document.adminForm.opendates.value;
                    tag += '}';
                }

                setTag(tag);
            }
            //-->
        </script>
        <div class="onelineblockoptions">
            <table width="100%" class="jem_auto <?php echo $class_options; ?>">
                <tr id="format" class="acyplugformat">
                    <td style="vertical-align: baseline; width:25%;"><?php echo Text::_('PLG_TAGJEM_SHOW_IMAGES'); ?></td>
                    <td style="vertical-align: baseline; width:25%;"><?php echo JHtml::_('acyselect.radiolist', $picts, 'pictauto', 'size="1" onclick="updateAutoTag();"', 'value', 'text', $pageInfo->pict); ?>
                    </td>
                    <td style="vertical-align: baseline; width:50%;" colspan="2">
						<span id="pictsizeauto" <?php if($pageInfo->pict != 'resized') echo 'style="display:none;"'; ?>><?php echo Text::_('PLG_TAGJEM_IMAGE_WIDTH') ?>
							<input name="pictwidthauto" type="text" onchange="updateAutoTag();" value="<?php echo $pageInfo->pictwidth; ?>" style="width:30px;"/>
							x <?php echo Text::_('PLG_TAGJEM_IMAGE_HEIGHT') ?>
							<input name="pictheightauto" type="text" onchange="updateAutoTag();" value="<?php echo $pageInfo->pictheight; ?>" style="width:30px;"/>
						</span>
                    </td>
                </tr>
                <tr>
                    <td style="vertical-align: baseline; width:25%;">
						<span title='<?php echo Text::_('COM_JEM_GLOBAL_FIELD_SHOW_OPENDATES_DESC'); ?>'>
							<?php echo Text::_('COM_JEM_GLOBAL_FIELD_SHOW_OPENDATES'); ?>
						</span>
                    </td>
                    <td style="vertical-align: baseline; width:25%;">
                        <?php echo JHtml::_('acyselect.radiolist', $opendates, 'opendates', 'size="1" onclick="updateAutoTag();"', 'value', 'text', $pageInfo->opendates); ?>
                        <!--select name="opendates" size="1" onchange="updateAutoTag();">
							<option value="no"><?php echo Text::_('JNo');?></option>
							<option value="also"><?php echo Text::_('COM_JEM_SHOW_OPENDATES_TOO');?></option>
							<option value="only"><?php echo Text::_('COM_JEM_SHOW_OPENDATES_ONLY');?></option>
						</select-->
                    </td>
                    <td style="vertical-align: baseline; width:25%;"><?php echo Text::_('PLG_TAGJEM_FEATURED_EVENTS'); ?></td>
                    <td style="vertical-align: baseline; width:25%;">
                        <?php echo JHtml::_('acyselect.radiolist', $yesno, 'featured', 'size="1" onclick="updateAutoTag();"', 'value', 'text', $pageInfo->featured); ?>
                        <!--select name="featured" size="1" onchange="updateAutoTag();">
							<option value="0"><?php echo Text::_('JNo');?></option>
							<option value="1"><?php echo Text::_('JYes');?></option>
						</select-->
                    </td>
                </tr>
                <tr>
                    <td style="vertical-align: baseline; width:25%;"><?php echo Text::_('MAX_ARTICLE'); ?></td>
                    <td style="vertical-align: baseline; width:25%;">
                        <input type="text" name="max_article" style="width:50px" value="" onchange="updateAutoTag();"/>
                    </td>
                    <td style="vertical-align: baseline; width:25%;"><?php echo Text::_('MAX_STARTING_DATE'); ?></td>
                    <td style="vertical-align: baseline; width:25%;">
                        <?php $delayType = acymailing_get('type.delay'); $delayType->onChange = "updateAutoTag();"; echo $delayType->display('delayevent', 7776000, 3); ?>
                    </td>
                </tr>
                <tr>
                    <td style="vertical-align: baseline; width:25%;"><?php echo Text::_('PLG_TAGJEM_TEMPLATE'); ?></td>
                    <td style="vertical-align: baseline; width:25%;">
                        <?php /* TODO: Collect templates dynamically. */ ?>
                        <select name="template" size="1" onchange="updateAutoTag();">
                            <option value="event">Event</option>
                            <option value="list">List</option>
                            <option value="summary">Summary</option>
                        </select>
                    </td>
                    <?php if($type === 'autonews') { ?>
                        <td style="vertical-align: baseline; width:25%;"><?php echo Text::_('MIN_ARTICLE'); ?></td>
                        <td style="vertical-align: baseline; width:25%;">
                            <input name="min_article" size="10" value="1" onchange="updateAutoTag();"/>
                        </td>
                    <?php } ?>
                </tr>
            </table>
        </div>

        <div class="onelineblockoptions">
            <table class="jem_auto <?php echo $class_list; ?>" cellpadding="1" width="100%">
                <thead>
                <tr>
                    <th class="title"></th>
                    <th class="title">
                        <?php echo Text::_('TAG_CATEGORIES'); ?>
                    </th>
                </tr>
                </thead>
                <tbody>
                <?php $k = 0; foreach ($categories as $oneCat) { ?>
                    <tr id="event_cat<?php echo $oneCat->id ?>" class="<?php echo "row$k"; ?>" onclick="applyAutoEvent(<?php echo $oneCat->id ?>,'<?php echo "row$k" ?>');" style="cursor:pointer;">
                        <td class="acytdcheckbox"></td>
                        <td>
                            <?php echo str_repeat('- - ', $oneCat->level) . $oneCat->catname; ?>
                        </td>
                    </tr>
                    <?php $k = 1 - $k; } ?>
                </tbody>
            </table>
        </div>
        <?php

        echo $tabs->endPanel();
        echo $tabs->endPane();
    } //End of the function

    //public function acymailing_replacetags(&$email, $send = true)
    function onAcymailing_replacetags(&$email, $send){
        return $this->acymailing_replacetags($email, $send);
    }

    function acymailing_replacetags(&$email, $send = true){

        $this->_replaceAuto($email);
        $this->_replaceEvents($email);
    }

    //protected function _replaceEvents(&$email)
    function _replaceEvents(&$email)
    {
        $match = '#{jem:(.*)}#Ui';
        $variables = array('body','altbody');
        $found = false;
        foreach ($variables as $var) {
            if (empty($email->$var)) {
                continue;
            }
            $found = preg_match_all($match, $email->$var, $results[$var]) || $found;
            // we unset the results so that we won't handle it later... it will save some memory and processing
            if (empty($results[$var][0])) {
                unset($results[$var]);
            }
        }

        // If we didn't find anything...
        if (!$found) {
            return;
        }

        $mailerHelper = acymailing_get('helper.mailer');

        $resultshtml = array();
        $resultstext = array();
        foreach ($results as $var => $allresults) {
            foreach ($allresults[0] as $i => $oneTag) {
                // Don't need to process twice a tag we already have!
                if (isset($resultshtml[$oneTag])) {
                    continue;
                }

                $resultshtml[$oneTag] = $this->_replaceEvent($allresults, $i);
                $resultstext[$oneTag] = $mailerHelper->textVersion($resultshtml[$oneTag]);
            }
        }

        $email->body    = str_replace(array_keys($resultshtml), $resultshtml, $email->body);
        $email->altbody = str_replace(array_keys($resultstext), $resultstext, $email->altbody);
    }

    //protected function _replaceEvent(&$allresults, $i)
    function _replaceEvent(&$allresults, $i)
    {
        // Transform the tag properly...
        $arguments = explode('|', $allresults[1][$i]);
        if (strlen($arguments[0]) === 0) {
            return '';
        }

        $result = '';
        $tag = new stdClass();
        $tag->itemid = intval($this->params->get('itemid'));
        for ($i = 1, $a = count($arguments); $i < $a; $i++) {
            $args = explode(':', $arguments[$i]);
            $arg0 = trim($args[0]);
            if (isset($args[1])) {
                $tag->$arg0 = trim($args[1]);
            } else {
                $tag->$arg0 = true;
            }
        }

        // old style: "images: w x h" == resize, "" == show full
        // new style: "images: 0" == hide, "images: 1" == show full,
        //            "images: resized| imgwidth: w| imgheight: h" == resize
        $showimages = !isset($tag->images) || ($tag->images !== '0');

        // The first argument is a list of events...
        $allEvents = explode('-', $arguments[0]);

        $db = Factory::getContainer()->get('DatabaseDriver');
        foreach ($allEvents as $oneEvent) {
            $tag->id = (int)$oneEvent;

            // Load the informations of the product...
            $query = ' SELECT a.id, a.alias, a.dates, a.registra, a.featured, a.datimage, a.enddates, a.times, a.endtimes, a.title, a.created, a.locid, a.maxplaces, a.waitinglist, a.fulltext,'
                . ' a.introtext, a.custom1, a.custom2, a.custom3, a.custom4, a.custom5, a.custom6, a.custom7, a.custom8, a.custom9, a.custom10, '
                . ' l.venue, l.city, l.state, l.url, l.street, ct.name AS countryname, l.postalcode, '
                . ' c.catname, c.id AS catid,'
                // TODO: get contact params and ensure not to show private things! Until that only show name and link to contact view.
                . ' cn.id as conid, cn.name as conname,' // cn.telephone as contelephone, cn.mobile as conmobile, cn.email_to as conemail_to,'
                . ' CASE WHEN CHAR_LENGTH(a.alias) THEN CONCAT_WS(\':\', a.id, a.alias) ELSE a.id END as slug,'
                . ' CASE WHEN CHAR_LENGTH(l.alias) THEN CONCAT_WS(\':\', a.locid, l.alias) ELSE a.locid END as venueslug,'
                . ' CASE WHEN CHAR_LENGTH(cn.alias) THEN CONCAT_WS(\':\', a.contactid, cn.alias) ELSE a.contactid END as contactslug'
                . ' FROM #__jem_events AS a'
                . ' LEFT JOIN #__jem_venues AS l ON l.id = a.locid'
                . ' LEFT JOIN #__jem_countries AS ct ON ct.iso2 = l.country '
                . ' LEFT JOIN #__jem_cats_event_relations AS rel ON rel.itemid = a.id'
                . ' LEFT JOIN #__jem_categories AS c ON c.id = rel.catid'
                . ' LEFT JOIN #__contact_details AS cn ON cn.id = a.contactid ';
            $query .= 'WHERE a.id = '.(int)$tag->id.' LIMIT 1';

            $db->setQuery($query);
            $event = $db->loadObject();

            if (empty($event)) {
                $app = Factory::getApplication();
                if ($app->isAdmin()) {
                    $app->enqueueMessage('The event "'.$tag->id.'" could not be loaded', 'notice');
                }
                continue;
            }

            $date = JemOutput::formatLongDateTime($event->dates, $event->times,
                $event->enddates, $event->endtimes);

            $link = 'index.php?option=com_jem&view=event&id='.$event->id.':'.$event->alias;
            if (!empty($tag->itemid)) {
                $link .= '&Itemid='.$tag->itemid;
            }
            if (!empty($tag->autologin)) {
                $link .= (strpos($link,'?') ? '&' : '?') . 'user={usertag:username|urlencode}&passw={usertag:password|urlencode}';
            }
            $link = acymailing_frontendLink($link);

            if (!$showimages) {
                $event->datimage = ''; // otherwise we have to adapt ALL templates, incl. user-made
            }
            /* on JEM 2 we have filename only, on JEM it already contains the path */
            if (!empty($event->datimage)) {
                $filename = basename($event->datimage);
                $dirname = dirname($event->datimage);
                if (empty($dirname) or $dirname === '.') { // JEM 2.x, fix path
                    $dirname = 'images/jem/events';
                }
                if (file_exists(ACYMAILING_ROOT . $dirname . '/small/' . $filename)) {
                    $event->datimage = $dirname . '/small/' . $filename;
                } elseif (file_exists(ACYMAILING_ROOT . $dirname . '/' . $filename)) {
                    $event->datimage = $dirname . '/' . $filename;
                } else {
                    $event->datimage = '';
                }
            }

            // Check if the template exists...
            $template = '';
            if (!empty($tag->template)) {
                $template = '_'.$tag->template;
            }

            if (file_exists(ACYMAILING_MEDIA.'plugins/tagjem'.$template.'.php')) {
                ob_start();
                require(ACYMAILING_MEDIA.'plugins/tagjem'.$template.'.php');
                $result .= ob_get_clean();
            } else {
                $result .= '<div class="acymailing_content" style="margin-top:12px">';
                if (!empty($event->datimage)) {
                    $imageFile = ACYMAILING_LIVE . $event->datimage;
                    $result .= '<table style="border-spacing: 5px; border-collapse: 0px; border: 0px;"><tr><td style="vertical-align: top;"><a style="text-decoration:none;border:0" target="_blank" href="'.$link.'" ><img src="'.$imageFile.'"/></a></td><td style="padding-left:5px" valign="top">';
                }
                $result .= '<a style="text-decoration:none;" title="event-'.$event->id.'" target="_blank" href="'.$link.'"><h2 class="acymailing_title" style="margin-top:0">'.$event->title;
                if (!empty($event->custom1)) {
                    $result .= '<br/><em>'.$event->custom1.'</em>';
                }
                $result .= '</h2></a>';
                $result .= '<p><span class="eventdate">'.$date.'</span></p>';
                if (!empty($event->venue)) {
                    $result .= '<p>'.$event->venue.'</p>';
                }
                if (!empty($event->datimage)) {
                    $result .= '</td></tr></table>';
                }
                $result .= '</div>';
            }
        } // foreach

        // Resize images
        if (!empty ($tag->images))
        {
            $size = explode('x', $tag->images);
            if (count($size) === 2)
            {
                $options = new stdClass();
                $options->maxWidth = max(intval($size[0]), 1);
                $options->maxHeight = max(intval($size[1]), 1);
                $result = $this->_resizeImages($result, $options);
            }
            else if ($tag->images === 'resized')
            {
                $options = new stdClass();
                $options->maxWidth = max((int)(isset($tag->imgwidth) ? $tag->imgwidth : $this->params->get('img_width', 160)), 1);
                $options->maxHeight = max((int)(isset($tag->imgheight) ? $tag->imgheight : $this->params->get('img_height', 160)), 1);
                $result = $this->_resizeImages($result, $options);
            }
        }

        return $result;
    }

    //protected function _replaceAuto(&$email)
    function _replaceAuto(&$email)
    {
        $this->acymailing_generateautonews($email);

        if (!empty($this->tags)) {
            $email->body = str_replace(array_keys($this->tags), $this->tags, $email->body);
            if (!empty($email->altbody)) {
                $email->altbody = str_replace(array_keys($this->tags), $this->tags, $email->altbody);
            }
        }
    }

    //public function acymailing_generateautonews(&$email)
    function acymailing_generateautonews(&$email)
    {
        $return = new stdClass();
        $return->status = true;
        $return->message = '';

        $time = time();
        // Check if we should generate the autoNewsletter or not...
        $match = '#{autojem:(.*)}#Ui';
        $variables = array('body', 'altbody');
        $found = false;
        foreach ($variables as $var) {
            if (empty($email->$var)) {
                continue;
            }
            $found = preg_match_all($match, $email->$var, $results[$var]) || $found;
            // we unset the results so that we won't handle it later... it will save some memory and processing
            if (empty($results[$var][0])) {
                unset($results[$var]);
            }
        }

        // If we didn't find anything... so we won't try to stop the generation
        if (!$found) {
            return $return;
        }

        $this->tags = array();
        $db = Factory::getContainer()->get('DatabaseDriver');

        foreach ($results as $var => $allresults) {
            foreach ($allresults[0] as $i => $oneTag) {
                if (isset($this->tags[$oneTag])) {
                    continue;
                }

                $arguments = explode('|', $allresults[1][$i]);
                // The first argument is a list of sections and cats...
                $allcats = explode('-', $arguments[0]);
                $parameter = new stdClass();
                for ($i = 1; $i < count($arguments); $i++) {
                    $args = explode(':', $arguments[$i]);
                    $arg0 = trim($args[0]);
                    if (isset($args[1])) {
                        $parameter->$arg0 = $args[1];
                    } else {
                        $parameter->$arg0 = true;
                    }
                }

                // Load the articles based on all arguments...
                $selectedArea = array();
                foreach ($allcats as $oneCat) {
                    if (empty($oneCat)) {
                        continue;
                    }
                    $selectedArea[] = 'b.`catid` = '.(int)$oneCat;
                }

                $query  = 'SELECT DISTINCT a.`id` FROM `#__jem_events` as a ';
                $query .= 'LEFT JOIN #__jem_cats_event_relations as b on a.id = b.itemid ';
                $where  = array();
                if (!empty($selectedArea)) {
                    $where[] = implode(' OR ',$selectedArea);
                }

                $where[] = 'a.published = 1';
                $features = !empty($parameter->features) ? $parameter->features : '0';
                if ($features == '1') {
                    $where[] = 'a.featured = 1';
                }

                // Open dates, date limits
                $opendates = !empty($parameter->opendates) ? $parameter->opendates : 'no';
                switch ($opendates) {
                    case 'no': // don't show events without start date
                    default:
                        $where[] = 'a.dates IS NOT NULL';
                        if (!empty($parameter->enddates)) {
                            $where[] = 'IF (a.enddates IS NOT NULL, a.enddates, a.dates) >= '.$db->Quote(date('Y-m-d', $time));
                        } else {
                            $where[] = 'a.dates >= '.$db->Quote(date('Y-m-d', $time));
                        }
                        if (!empty($parameter->delay)) {
                            $where[] = 'a.dates <= '.$db->Quote(date('Y-m-d', $time + $parameter->delay));
                        }
                        break;
                    case 'also': // show all events, with or without start date
                        if (!empty($parameter->enddates)) {
                            $where[] = '(a.dates IS NULL OR IF (a.enddates IS NOT NULL, a.enddates, a.dates) >= '.$db->Quote(date('Y-m-d', $time)).')';
                        } else {
                            $where[] = '(a.dates IS NULL OR a.dates >= '.$db->Quote(date('Y-m-d', $time)).')';
                        }
                        if (!empty($parameter->delay)) {
                            $where[] = '(a.dates IS NULL OR a.dates <= '.$db->Quote(date('Y-m-d', $time + $parameter->delay)).')';
                        }
                        break;
                    case 'only': // show only events without startdate
                        $where[] = 'a.dates IS NULL';
                        break;
                }

                $query .= ' WHERE ('.implode(') AND (',$where).')';
                $query .= ' ORDER BY a.dates ASC, a.times ASC';
                if (!empty($parameter->max)) {
                    $query .= ' LIMIT '.(int)$parameter->max;
                }

                $db->setQuery($query);
                $allArticles = acymailing_loadResultArray($db);

                if (!empty($parameter->min) AND (count($allArticles) < $parameter->min)) {
                    // We won't generate the Newsletter
                    $return->status = false;
                    $return->message = 'Not enough events for the tag '.$oneTag.' : '.count($allArticles).' / '.$parameter->min;
                }

                $stringTag = '';
                if (!empty($allArticles)) {
                    if (file_exists(ACYMAILING_MEDIA.'plugins/autojem.php')) {
                        ob_start();
                        require(ACYMAILING_MEDIA.'plugins/autojem.php');
                        $stringTag = ob_get_clean();
                    } else {
                        // we insert the article tag one after the other in a table as they are already sorted
                        $stringTag .= '<table style="border-spacing:0px; border-collapse:0px; border:0px">';
                        foreach ($allArticles as $oneArticleId) {
                            $stringTag .= '<tr><td>';
                            $args = array();
                            $args[] = 'jem:'.$oneArticleId;
                            if (!empty($parameter->images)) {
                                $args[] = 'images:'.$parameter->images;
                            }
                            if (!empty($parameter->imgwidth)) {
                                $args[] = 'imgwidth:'.$parameter->imgwidth;
                            }
                            if (!empty($parameter->imgheight)) {
                                $args[] = 'imgheight:'.$parameter->imgheight;
                            }
                            if (!empty($parameter->template)) {
                                $args[] = 'template:'.$parameter->template;
                            }
                            $stringTag .= '{'.implode('|',$args).'}';
                            $stringTag .= '</td></tr>';
                        }
                        $stringTag .= '</table>';
                    }
                }

                $this->tags[$oneTag] = $stringTag;
            }
        }

        return $return;
    }

    /**
     * Resize all images (img tag) contained in a text
     * @param $text html content to search images in
     * @param $option size beyond which image have to be resized
     * ($options->maxWidth, $options->maxHeight)
     */
    protected function _resizeImages($text, $options)
    {
        if (!empty($options->maxHeight) && !empty($options->maxWidth))
        {
            $pictureHelper = acymailing_get('helper.acypict');
            $pictureHelper->maxHeight = $options->maxHeight;
            $pictureHelper->maxWidth = $options->maxWidth;
            if ($pictureHelper->available()) {
                $text = $pictureHelper->resizePictures($text);
            }
        }

        return $text;
    }

}//endclass
