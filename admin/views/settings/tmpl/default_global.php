<?php
/**
 * @version 1.1 $Id$
 * @package Joomla
 * @subpackage EventList
 * @copyright (C) 2005 - 2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
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

defined('_JEXEC') or die('Restricted access');

?>
<table class="noshow">
	<tr>
		<td>
		<fieldset class="adminform"><legend><?php echo JText::_( 'ATTENTION' ); ?></legend>
		<table class="admintable" cellspacing="1">
			<tbody>
				<tr>
					<td><?php echo JText::_( 'GLOBAL PARAM DESC' ); ?></td>
				</tr>
			</tbody>
		</table>
		</fieldset>
    </td>
  </tr>
  <tr>
    <td>		
		<fieldset class="adminform"><legend><?php echo JText::_( 'GLOBAL PARAMETERS' ); ?></legend>
		<?php echo $this->globalparams->render('globalparams'); ?></fieldset>
		
		<?php foreach ($this->globalparams->getGroups() as $key => $groups): ?>
			<?php if (strtolower($key) != '_default'): ?>
				<fieldset class="adminform"><legend><?php echo JText::_( strtoupper($key) ); ?></legend>
				<?php echo $this->globalparams->render('globalparams', $key); ?></fieldset>
			<?php endif; ?>
		<?php endforeach; ?>
		</td>
	</tr>
</table>