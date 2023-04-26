<?php
/**
 * @version 2.3.17
 * @package JEM
 * @subpackage JEM Module
 * @copyright (C) 2013-2023 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

?>

<div class="jemmodulebasic<?php echo $params->get('moduleclass_sfx')?>" id="jemmodulebasic">
<?php if (count($list)): ?>
	<ul class="jemmod">
		<?php foreach ($list as $item) : ?>
		<li>
			<?php if ($params->get('linkdet') == 1) : ?>
			<a href="<?php echo $item->link; ?>">
				<?php echo $item->dateinfo; ?>
			</a>
			<?php else :
				echo $item->dateinfo;
			endif;
			?>
			<br />

			<?php if ($params->get('showtitloc') == 0 && $params->get('linkloc') == 1) : ?>
				<a href="<?php echo $item->venueurl; ?>">
					<?php echo $item->text; ?>
				</a>
			<?php elseif ($params->get('showtitloc') == 1 && $params->get('linkdet') == 2) : ?>
				<a href="<?php echo $item->link; ?>">
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
<?php else : ?>
	<?php echo Text::_('MOD_JEM_NO_EVENTS'); ?>
<?php endif; ?>
</div>