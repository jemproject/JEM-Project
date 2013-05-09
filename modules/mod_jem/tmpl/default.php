<?php
/**
 * @version 1.9 $Id$
 * @package JEM
 * @subpackage JEM Module
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 *
 * JEM is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.
 *
 * JEM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with JEM; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

defined('_JEXEC') or die;
?>

<ul class="jemmod<?php echo $params->get('moduleclass_sfx'); ?>">
<?php if (count($items)): ?>


<?php foreach ($list as $item) :  ?>
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