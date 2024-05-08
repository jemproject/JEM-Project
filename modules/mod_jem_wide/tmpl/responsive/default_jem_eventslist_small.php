<?php
/**
 * @version    4.2.2
 * @package    JEM
 * @copyright  (C) 2013-2024 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

?>

<div class="jem-sort jem-sort-small">
  <div class="jem-list-row jem-small-list">
    <div id="jem-date" class="sectiontableheader"><i class="fa fa-clock" aria-hidden="true"></i>&nbsp;<?php echo Text::_('COM_JEM_TABLE_DATE'); ?></div>
    <?php if (!JemHelper::jemStringContains($params->get('moduleclass_sfx'), 'jem-notitle')) : ?>           
      <div id="jem-title" class="sectiontableheader"><i class="fa fa-comment-o" aria-hidden="true"></i>&nbsp;<?php echo Text::_('COM_JEM_TABLE_TITLE'); ?></div>
    <?php endif; ?> 
    <?php if (!JemHelper::jemStringContains($params->get('moduleclass_sfx'), 'jem-novenue')) : ?>
      <div id="jem-location" class="sectiontableheader"><i class="fa fa-map-marker" aria-hidden="true"></i>&nbsp;<?php echo Text::_('COM_JEM_TABLE_LOCATION'); ?></div>
    <?php endif; ?>
    <?php if (!JemHelper::jemStringContains($params->get('moduleclass_sfx'), 'jem-nocity')) : ?>
      <div id="jem-city" class="sectiontableheader"><i class="fa fa-building-o" aria-hidden="true"></i>&nbsp;<?php echo Text::_('COM_JEM_TABLE_CITY'); ?></div>
    <?php endif; ?>
    <?php if (!JemHelper::jemStringContains($params->get('moduleclass_sfx'), 'jem-nostate')) : ?>
      <div id="jem-state" class="sectiontableheader"><i class="fa fa-map-o" aria-hidden="true"></i>&nbsp;<?php echo Text::_('COM_JEM_TABLE_STATE'); ?></div>
    <?php endif; ?>
    <?php if (!JemHelper::jemStringContains($params->get('moduleclass_sfx'), 'jem-nocats')) : ?>
      <div id="jem-category" class="sectiontableheader"><i class="fa fa-tag" aria-hidden="true"></i>&nbsp;<?php echo Text::_('COM_JEM_TABLE_CATEGORY'); ?></div>
    <?php endif; ?> 
  </div>
</div>

<ul class="eventlist">
      <?php
      // Safari has problems with the "onclick" element in the <li>. It covers the links to location and category etc.
      // This detects the browser and just writes the onclick attribute if the broswer is not Safari.
      $isSafari = false;
      if (strpos($_SERVER['HTTP_USER_AGENT'], 'Safari') && !strpos($_SERVER['HTTP_USER_AGENT'], 'Chrome')) {
        $isSafari = true;
      }
      ?>
			<?php foreach ($list as $item) : ?>
        <?php if (!empty($item->featured)) :   ?>
          <li class="jem-event jem-list-row jem-small-list jem-featured" <?php if ($params->get('linkevent') == 1 && (!$isSafari)) : echo 'onclick=location.href="'.$item->eventlink.'"'; endif; ?> >
				<?php else : ?>
          <li class="jem-event jem-list-row jem-small-list" <?php if ($params->get('linkevent') == 1 && (!$isSafari)) : echo 'onclick=location.href="'.$item->eventlink.'"'; endif; ?> >
				<?php endif; ?>              
              <div class="jem-event-info-small jem-event-date" title="<?php echo Text::_('COM_JEM_TABLE_DATE').': '.strip_tags($item->dateinfo); ?>">
                <i class="fa fa-clock" aria-hidden="true"></i>
                <?php 
                if ($item->date && $params->get('datemethod', 1) == 2) :
                  echo $item->date;
                elseif ($item->date && $params->get('datemethod', 1) == 1) : 
                  echo $item->dateinfo;
                endif; 
                ?>
                 <?php if (JemHelper::jemStringContains($params->get('moduleclass_sfx'), 'jem-notitle')) : ?>
                  <?php if (!empty($item->featured)) :?>
                    <i class="jem-featured-icon fa fa-exclamation-circle" aria-hidden="true"></i>
                  <?php endif; ?>
                 <?php endif; ?>
              </div>
              
              <?php if (!JemHelper::jemStringContains($params->get('moduleclass_sfx'), 'jem-notitle')) : ?>
                <div class="jem-event-info-small jem-event-title" title="<?php echo Text::_('COM_JEM_TABLE_TITLE').': '.$item->fulltitle; ?>">
                  <i class="fa fa-comment" aria-hidden="true"></i>
                  <?php if ($params->get('linkevent') == 1) : ?>
                  <a href="<?php echo $item->eventlink; ?>">
                    <?php echo $item->title; ?>
                  </a>
                  <?php else : ?>
                    <?php echo $item->title; ?>
                  <?php endif; ?>
                  <?php if (!empty($item->featured)) :?>
                    <i class="jem-featured-icon fa fa-exclamation-circle" aria-hidden="true"></i>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
              
              <?php if (!JemHelper::jemStringContains($params->get('moduleclass_sfx'), 'jem-novenue')) : ?>
                <?php if (!empty($item->venue)) : ?>
                  <div class="jem-event-info-small jem-event-venue" title="<?php echo Text::_('COM_JEM_TABLE_LOCATION').': '.$item->venue; ?>">
                    <i class="fa fa-map-marker" aria-hidden="true"></i>
                    <?php if ($params->get('linkvenue') == 1) : ?>
                      <?php echo "<a href='".$item->venuelink."'>".$item->venue."</a>"; ?>
                    <?php else : ?>
                      <?php echo $item->venue; ?>
                    <?php endif; ?>                  
                  </div>
                <?php else : ?>
                  <div class="jem-event-info-small jem-event-venue"><i class="fa fa-map-marker" aria-hidden="true"></i> -</div>
                <?php endif; ?>                
              <?php endif; ?>

              <?php if (!JemHelper::jemStringContains($params->get('moduleclass_sfx'), 'jem-nocity')) : ?>
                <?php if (!empty($item->city)) : ?>
                  <div class="jem-event-info-small jem-event-city" title="<?php echo Text::_('COM_JEM_TABLE_CITY').': '.$item->city; ?>">
                    <i class="fa fa-building" aria-hidden="true"></i>
                    <?php echo $item->city; ?>
                  </div>
                <?php else : ?>
                  <div class="jem-event-info-small jem-event-city"><i class="fa fa-building-o" aria-hidden="true"></i> -</div>
                <?php endif; ?>
              <?php endif; ?>
              
              <?php if (!JemHelper::jemStringContains($params->get('moduleclass_sfx'), 'jem-nostate')) : ?>
                <?php if (!empty($item->state)) : ?>
                  <div class="jem-event-info-small jem-event-state" title="<?php echo Text::_('COM_JEM_TABLE_STATE').': '.$item->state; ?>">
                    <i class="fa fa-map" aria-hidden="true"></i>
                    <?php echo $item->state; ?>
                  </div>
                <?php else : ?>
                  <div class="jem-event-info-small jem-event-state"><i class="fa fa-map-o" aria-hidden="true"></i> -</div>
                <?php endif; ?>
              <?php endif; ?>
              
              <?php if (!JemHelper::jemStringContains($params->get('moduleclass_sfx'), 'jem-nocats')) : ?>
                <div class="jem-event-info-small jem-event-category" title="<?php echo strip_tags(Text::_('COM_JEM_TABLE_CATEGORY').': '.$item->catname); ?>">
                  <i class="fa fa-tag" aria-hidden="true"></i>
                  <?php echo $item->catname; ?>
                </div>
              <?php endif; ?>
        </li>
			<?php endforeach; ?>
</ul>