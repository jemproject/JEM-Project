<?php
/**
 * @version 1.0 $Id$
 * @package Joomla
 * @subpackage EventList
 * @copyright (C) 2008 Christoph Lukes
 * @license GNU/GPL, see LICENCE.php
 * EventList is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License 2
 * as published by the Free Software Foundation.

 * EventList is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.

 * You should have received a copy of the GNU General Public License
 * along with EventList; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

defined( '_JEXEC' ) or die;
?>

<div class="subcategories">
<?php echo JText::_('COM_JEM_SUBCATEGORIES'); ?>
</div>
<?php
$n = count($this->categories);
$i = 0;
?>
<div class="subcategorieslist">
	<?php foreach ($this->categories as $sub) : ?>
    <?php if ($this->params->get('show_empty', 1) || $sub->assignedevents != null): ?>
			<strong><a href="<?php echo JRoute::_( 'index.php?view=categoryevents&id='. $sub->slug ); ?>"><?php echo $this->escape($sub->catname); ?></a></strong> (<?php echo $sub->assignedevents != null ? $sub->assignedevents : 0; ?>)
			<?php 
			$i++;
			if ($i != $n) :
				echo ',';
			endif;
		endif;
	endforeach; ?>
</div>