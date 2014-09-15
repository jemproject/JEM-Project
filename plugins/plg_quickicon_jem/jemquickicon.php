<?php
/**
 * @version 2.0.0
 * @package JEM
 * @copyright (C) 2013-2014 joomlaeventmanager.net
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
		if ($context != $this->params->get('context', 'mod_quickicon') || !include_once(rtrim(JPATH_ADMINISTRATOR,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'components'.DIRECTORY_SEPARATOR.'com_jem'.DIRECTORY_SEPARATOR.'helpers'.DIRECTORY_SEPARATOR.'helper.php')) {
			return;
		}

		$text = $this->params->get('displayedtext');
		if(empty($text)) $text = JText::_('JEM-Events');

		return array(array(
			'link' => 'index.php?option=com_jem',
			'image' => JURI::base().'../media/com_jem/images/icon-48-home.png',
			'text' => $text,
			'id' => 'plg_quickicon_jemquickicon'
		));
	}
}
