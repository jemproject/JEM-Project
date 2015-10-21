<?php
/**
 * @version 2.1.5
 * @package JEM
 * @copyright (C) 2013-2015 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 *
 * @todo add check if CB does exists and if so perform action
 */

defined('_JEXEC') or die;
?>

<div class="register">
	<dl class="floattext">
	<?php if ($this->item->maxplaces > 0) : ?>
		<dt class="register"><?php echo JText::_('COM_JEM_MAX_PLACES'); ?>:</dt>
		<dd class="register"><?php echo $this->item->maxplaces; ?></dd>
		<dt class="register"><?php echo JText::_('COM_JEM_BOOKED_PLACES'); ?>:</dt>
		<dd class="register"><?php echo $this->item->booked; ?></dd>
	<?php endif; ?>
	<?php if ($this->item->maxplaces > 0) : ?>
		<dt class="register"><?php echo JText::_('COM_JEM_AVAILABLE_PLACES'); ?>:</dt>
		<dd><?php echo ($this->item->maxplaces - $this->item->booked); ?></dd>
	<?php endif; ?>
	<?php
		// only set style info if users already have registered and user is allowed to see it
		if ($this->registers) :
	?>
		<dt class="register"><?php echo JText::_('COM_JEM_REGISTERED_USERS'); ?>:</dt>
		<dd class="register">
			<ul class="user floattext">
			<?php
			if ($this->settings->get('event_comunsolution', '0') == 1) :
				if ($this->settings->get('event_comunoption', '0') == 1) :
					//$cparams = JComponentHelper::getParams('com_media');
					//$imgpath = $cparams->get('image_path'); // mostly 'images'
					$imgpath = 'images'; // CB does NOT respect path set in Media Manager, so we have to ignore this too
					if (JFile::exists(JPATH_ROOT . '/components/com_comprofiler/plugin/templates/default/images/avatar/tnnophoto_n.png')) {
						$noimg = 'components/com_comprofiler/plugin/templates/default/images/avatar/tnnophoto_n.png';
					} elseif (JFile::exists(JPATH_ROOT . '/components/com_comprofiler/images/english/tnnophoto.jpg')) {
						$noimg = 'components/com_comprofiler/images/english/tnnophoto.jpg';
					} else {
						$noimg = '';
					}
				endif;
			endif;

			// loop through attendees
			foreach ($this->registers as $register) :
				$text = '';
				// is a plugin catching this ?
				if ($res = $this->dispatcher->trigger('onAttendeeDisplay', array($register->uid, &$text))) :
					echo '<li>'.$text.'</li>';
				endif;
				// if CB
				if ($this->settings->get('event_comunsolution', '0') == 1) :
					if ($this->settings->get('event_comunoption', '0') == 1) :
						// User has avatar
						if (!empty($register->avatar)) :
							if (JFile::exists(JPATH_ROOT . '/' . $imgpath . '/comprofiler/tn' . $register->avatar)) {
								$useravatar = JHtml::image($imgpath . '/comprofiler/tn' . $register->avatar, $register->name);
							} elseif (JFile::exists(JPATH_ROOT . '/' . $imgpath . '/comprofiler/' . $register->avatar)) {
								$useravatar = JHtml::image($imgpath . '/comprofiler/' . $register->avatar, $register->name);
							} else {
								$useravatar = empty($noimg) ? '' : JHtml::image($noimg, $register->name);
							}
							echo '<li><a href="' . JRoute::_('index.php?option=com_comprofiler&task=userProfile&user=' . $register->uid) . '" title = "' . JText::_('COM_JEM_SHOW_USER_PROFILE') . '">' . $useravatar . '<span class="username">' . $register->name . '</span></a></li>';

						// User has no avatar
						else :
							$nouseravatar = empty($noimg) ? '' : JHtml::image($noimg, $register->name);
							echo '<li><a href="' . JRoute::_('index.php?option=com_comprofiler&task=userProfile&user=' . $register->uid) . '" title = "' . JText::_('COM_JEM_SHOW_USER_PROFILE') .'">' . $nouseravatar . '<span class="username">' . $register->name . '</span></a></li>';
						endif;
					else :
						// only show the username with link to profile
						echo '<li><span class="username"><a href="' . JRoute::_('index.php?option=com_comprofiler&amp;task=userProfile&amp;user=' . $register->uid) . '">' . $register->name . '</a></span></li>';
					endif;
				// if CB end - if not CB than only name
				else :
					// no communitycomponent is set so only show the username
					echo '<li><span class="username">' . $register->name . '</span></li>';
				endif;

			// end loop through attendees
			endforeach;
			?>
			</ul>
		</dd>
	<?php endif; ?>
	</dl>

	<?php if ($this->print == 0) : ?>
	<dl class="floattext">
		<dd class="register">
			<?php
			if ($this->item->published != 1) {
				echo JText::_('COM_JEM_WRONG_STATE_FOR_REGISTER');
			} else {
				switch ($this->formhandler) {
				case 0:
					echo JText::_('COM_JEM_TOO_LATE_UNREGISTER');
					break;
				case 1:
					echo JText::_('COM_JEM_TOO_LATE_REGISTER');
					break;
				case 2:
					echo JText::_('COM_JEM_LOGIN_FOR_REGISTER');
					break;
				case 3:
					echo $this->loadTemplate('unregform');
					break;
				case 4:
					echo $this->loadTemplate('regform');
					break;
				}
			}
			?>
		</dd>
	</dl>
	<?php endif; ?>
</div>
