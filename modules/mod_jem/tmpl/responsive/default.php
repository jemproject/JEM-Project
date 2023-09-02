<?php
/**
 * @version    4.1.0
 * @package    JEM
 * @subpackage JEM Module
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

$app = Factory::getApplication();
$document = $app->getDocument();
$module_name = 'mod_jem';

/*
$uri = Uri::getInstance();
$css_path = JPATH_THEMES. '/'.$document->template.'/css/'.$module_name;
if(file_exists($css_path.'/'.$module_name.'.css')) {
  unset($document->_styleSheets[$uri->base(true).'/modules/mod_jem_/tmpl/mod_jem.css']);
  $document->addStylesheet($uri->base(true) . '/templates/'.$document->template.'/css/'. $module_name.'/'.$module_name.'.css');
} else {
  $document->addStyleSheet($uri->base(true).'/modules/mod_jem/tmpl/mod_jem_responsive.css');
}
*/

$highlight_featured = $params->get('highlight_featured');
$showtitloc = $params->get('showtitloc');
$linkloc = $params->get('linkloc');
$linkdet = $params->get('linkdet');
$showiconcountry = $params->get('showiconcountry');
$settings = JemHelper::config();
?>

<div class="jemmodulebasic<?php echo $params->get('moduleclass_sfx')?>" id="jemmodulebasic">
<?php if (count($list)): ?>
  <ul>
    <?php foreach ($list as $item) : ?>
      <li>
        <i class="far fa-calendar-alt"></i>
          <?php if($highlight_featured && $item->featured): ?>
            <span class="event-title highlight_featured">
          <?php else : ?>
            <span class="event-title">
          <?php endif; ?>
          <?php if (($showiconcountry == 1) && !empty($item->country)) : ?>
		    <?php $flagpath = $settings->flagicons_path . (str_ends_with($settings->flagicons_path, '/')?'':'/');
		  	$flagext = substr($flagpath, strrpos($flagpath,"-")+1,-1) ;
			$flagfile = Uri::getInstance()->base() . $flagpath . strtolower($item->country) . '.' . $flagext;
			echo '<img src="' . $flagfile . '" alt="' . $item->country . ' ' , Text::_('MOD_JEM_SHOW_FLAG_ICON') . '">' ?>
          <?php endif; ?>
          <?php if ($showtitloc == 0 && $linkloc == 1) : ?>
            <a href="<?php echo $item->venueurl; ?>">
              <?php echo $item->text; ?>
            </a>
          <?php elseif ($showtitloc == 1 && $linkdet == 2) : ?>
            <a href="<?php echo $item->link; ?>" title="<?php echo strip_tags($item->text); ?>">
              <?php echo $item->text; ?>
            </a>
                        <?php else :
              echo $item->text;
                        endif; ?>
        </span>
        <br />
        <?php if($highlight_featured && $item->featured): ?>
            <span class="event-title highlight_featured">
        <?php else : ?>
            <span class="event-title">
        <?php endif; ?>
        <?php if ($linkdet == 1) : ?>
        <a href="<?php echo $item->link; ?>" title="<?php echo strip_tags($item->dateinfo); ?>">
          <?php echo $item->dateinfo; ?>
        </a>
        <?php else :
          echo $item->dateinfo;
                        endif; ?>
        </span>
      </li>
    <?php endforeach; ?>
  </ul>
<?php else : ?>
  <?php echo Text::_('COM_JEM_NO_EVENTS'); ?>
<?php endif; ?>
</div>
