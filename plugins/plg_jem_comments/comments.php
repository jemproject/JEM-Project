<?php
/**
 * @version 1.9 $Id$
 * @package JEM
 * @subpackage JEM Comments Plugin
 * @copyright (C) 2013-2013 joomlaeventmanager.net
 * @copyright (C) 2005-2009 Christoph Lukes
 * @license GNU/GPL, see LICENSE.php
 
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

// no direct access
defined( '_JEXEC' ) or die;
 
// Import library dependencies
jimport( 'joomla.plugin.plugin' );

include_once(JPATH_SITE.'/components/com_jem/helpers/route.php');

class plgJEMComments extends JPlugin {
	
	
	public function __construct(& $subject, $config)
    {
            parent::__construct($subject, $config);
            $this->loadLanguage();
    }
	
	
	
	/**
	 * Constructor
	 *
	 * @param object $subject The object to observe
	 * @param 	array  $config  An array that holds the plugin configuration
	 * @since 1.0
	 */
	public function plgJEMComments(&$subject, $config = array())
	{
		parent::__construct($subject, $config);
	}
	
	/**
	 * This method handles the supported comment systems
	 *
	 * @access	public
	 * @param   int 	$event_id 	 Integer Event identifier
	 * @param   int 	$event_title	 String Event title
	 * @return	boolean
	 * @since 1.0
	 */
	public function onEventDetailsEnd($event_id, $event_title = '' )
	{	
		//simple, skip if processing not needed
		if (!$this->params->get('commentsystem', '0') ) {
			return '';
		}
		
		$res = '';
	
		//jomcomment integration
		if ($this->params->get('commentsystem') == 1 ) {
			if (file_exists(JPATH_SITE.'/plugins/content/jom_comment_bot.php')) {
    			require_once(JPATH_SITE.'/plugins/content/jom_comment_bot.php');
				$res	.= '<div class="elcomments">';
    			$res 	.= jomcomment($event_id, 'com_jem');
				$res 	.= '</div>';
  			}
		}
	
		//jcomments integration
		if ($this->params->get('commentsystem') == 2 ) {
			if (file_exists(JPATH_SITE.'/components/com_jcomments/jcomments.php')) {
				require_once(JPATH_SITE.'/components/com_jcomments/jcomments.php');
				$res .= '<div class="elcomments">';
				$res .= JComments::showComments($event_id, 'com_jem', $event_title);
				$res .= '</div>';
			}
		}
		
		//JXtended Comments integration
		if ($this->params->get('commentsystem') == 3 ) {
			if (file_exists(JPATH_SITE.'/components/com_comments/helpers/html/comments.php')) {
				require_once(JPATH_SITE.'/components/com_comments/helpers/html/comments.php');
				
				$res .= '<div class="elcomments">';
				
				// display sharing
				$res .= JHtml::_('comments.share', substr($_SERVER['REQUEST_URI'], 1), $event_title);
				
				// display ratings
				$res .= JHtml::_('comments.rating', 'jem', $event_id, JEMHelperRoute::getRoute($event_id), substr($_SERVER['REQUEST_URI'], 1), $event_title);
				
				// display comments
				$res .= JHtml::_('comments.comments', 'jem', $event_id, JEMHelperRoute::getRoute($event_id), substr($_SERVER['REQUEST_URI'], 1), $event_title);
				$res .= '<style type="text/css">';
				$res .= 'div#respond-container dt { float: none;border-bottom: medium none;padding: 0;width: auto;}';
				$res .= '</style>';
				$res .= '</div>';
			}
		}
		return $res;
	}
}
?>