<?php
/**
 * @version 2.3.0-dev2
 * @package JEM
 * @subpackage JEM Module
 * @copyright (C) 2013-2018 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;

$document = JFactory::getDocument();
$module_name = 'mod_jem';
/*
$css_path = JPATH_THEMES. '/'.$document->template.'/css/'.$module_name;
if(file_exists($css_path.'/'.$module_name.'.css')) {
  unset($document->_styleSheets[JUri::base(true).'/modules/mod_jem_/tmpl/mod_jem.css']);
  $document->addStylesheet(JURI::base(true) . '/templates/'.$document->template.'/css/'. $module_name.'/'.$module_name.'.css');
} else {
  $document->addStyleSheet(JUri::base(true).'/modules/mod_jem/tmpl/mod_jem_responsive.css');
}
*/
?>

<div class="jemmodulebasic<?php echo $params->get('moduleclass_sfx')?>" id="jemmodulebasic">
<?php if (count($list)): ?>
  <ul>
    <?php foreach ($list as $item) : ?>
      <li>
        <span class="event-title">
          <?php if ($params->get('showtitloc') == 0 && $params->get('linkloc') == 1) : ?>
            <a href="<?php echo $item->venueurl; ?>">
              <?php echo $item->text; ?>
            </a>
          <?php elseif ($params->get('showtitloc') == 1 && $params->get('linkdet') == 2) : ?>
            <a href="<?php echo $item->link; ?>" title="<?php echo strip_tags($item->text); ?>">
              <?php echo $item->text; ?>
            </a>
          <?php
            else :
              echo $item->text;
            endif;
          ?>    
        </span>
        <br />
        <?php if ($params->get('linkdet') == 1) : ?>
        <a href="<?php echo $item->link; ?>" title="<?php echo strip_tags($item->dateinfo); ?>">
          <?php echo $item->dateinfo; ?>
        </a>
        <?php else :
          echo $item->dateinfo;
        endif;
        ?>
      </li>
    <?php endforeach; ?>
  </ul>
<?php else : ?>
  <?php echo JText::_('COM_JEM_NO_EVENTS'); ?>
<?php endif; ?>
</div>
