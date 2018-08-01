<?php
/**
 * @version 2.0.0
 * @package JEM
 * @subpackage JEM Module
 * @copyright (C) 2013-2014 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */
defined('_JEXEC') or die;

?>


<?php if (count($items)): ?>
<ul class="jemmod<?php echo $params->get('moduleclass_sfx'); ?>">
	<?php foreach ($list as $item) : ?>
	<li class="jemmod<?php echo $params->get('moduleclass_sfx'); ?>">
		<?php if ($params->get('linkdet') == 1) : ?>
		<a href="<?php echo $item->link; ?>" class="jemmod<?php echo $params->get('moduleclass_sfx'); ?>">
			<?php echo $item->dateinfo; ?>
		</a>
		<?php else :
			echo $item->dateinfo;
		endif;
		?>
		<br />

		<?php if ($params->get('showtitloc') == 0 && $params->get('linkloc') == 1) : ?>
			<a href="<?php echo $item->venueurl; ?>" class="jemmod<?php echo $params->get('moduleclass_sfx'); ?>">
				<?php echo $item->text; ?>
			</a>
		<?php elseif ($params->get('showtitloc') == 1 && $params->get('linkdet') == 2) : ?>
			<a href="<?php echo $item->link; ?>" class="jemmod<?php echo $params->get('moduleclass_sfx'); ?>">
				<?php echo $item->text; ?>
			</a>
		<?php
			else :
				echo $item->text;
			endif;
		?>
	</li>
	<?php endforeach; ?>
</ul>
<?php endif; ?>