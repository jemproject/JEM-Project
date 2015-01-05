<?php
/**
 * @version 2.1.0
 * @package JEM
 * @copyright (C) 2013-2015 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 *
 * Plugin based on the Joomla! update notification plugin
 */

defined('_JEXEC') or die;

/**
 * JEM Quickicon Plugin
 *
 */
class plgQuickiconJEMquickicon extends JPlugin
{
	public function __construct(& $subject, $config)
	{
		parent::__construct($subject, $config);
	}

	public function onGetIcons($context)
	{
		if ($context != $this->params->get('context', 'mod_quickicon') ||
		    !JFactory::getUser()->authorise('core.manage', 'com_jem')  ||
		    !file_exists(JPATH_ADMINISTRATOR.'/components/com_jem/helpers/helper.php')) {
			return;
		}

		$useIcons = version_compare(JVERSION, '3.0', '>');
		$icon = 'com_jem/icon-48-home.png'; // which means '/media/com_jem/images/icon-48-home.png'
		$text = $this->params->get('displayedtext');
		if (empty($text)) $text = JText::_('JEM-Events');

		return array(array(
			'link' => 'index.php?option=com_jem',
			'image' => $useIcons ? 'calendar' : $icon, // for J! 2.5 or e.g. Isis on J! 3.x
			'icon' => $icon,                           // for e.g. Hathor on J! 3.x
			'text' => $text,
			'id' => 'plg_quickicon_jemquickicon'
		));
	}
}
