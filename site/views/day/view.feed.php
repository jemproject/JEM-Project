<?php
/**
 * @version    4.2.0
 * @package    JEM
 * @copyright  (C) 2013-2023 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView;

/**
 * Day-Feed
 */
class JemViewDay extends HtmlView
{
	/**
	 * Creates the Event Feed
	 */
	public function display($cachable = false, $urlparams = false)
	{
		$app = Factory::getApplication();
		$document = $app->getDocument();
		$jemsettings = JemHelper::config();

		// Get some data from the model
		$app->input->set('limit', $app->get('feed_limit'));
		$rows = $this->get('Items');

		if (!empty($rows)) {
			foreach ($rows as $row) {
				// strip html from feed item title
				$title = $this->escape($row->title);
				$title = html_entity_decode($title);

				// strip html from feed item category
				if (!empty($row->categories)) {
					$category = array();
					foreach ($row->categories AS $category2) {
						$category[] = $category2->catname;
					}

					// ading the , to the list when there are multiple category's
					$category = $this->escape(implode(', ', $category));
					$category = html_entity_decode($category);
				} else {
					$category = '';
				}

				// Format date and time
				$displaydate = JemOutput::formatLongDateTime($row->dates, $row->times,$row->enddates, $row->endtimes, $jemsettings->showtime);

				// url link to event
				$link = JRoute::_(JemHelperRoute::getEventRoute($row->id));

				// feed item description text
				$description  = Text::_('COM_JEM_TITLE').': '.$title.'<br />';
				$description .= Text::_('COM_JEM_VENUE').': '.$row->venue.($row->city ? (' / '.$row->city) : '').'<br />';
				$description .= Text::_('COM_JEM_CATEGORY').': '.$category.'<br />';
				$description .= Text::_('COM_JEM_DATE').': '.$displaydate.'<br />';
				$description .= Text::_('COM_JEM_DESCRIPTION').': '.$row->fulltext;

				$created = ($row->created ? date('r', strtotime($row->created)) : '');

				// load individual item creator class
				$item = new JFeedItem();
				$item->title       = $title;
				$item->link        = $link;
				$item->description = $description;
				$item->date        = $created;
				$item->category    = $category;

				// loads item info into rss array
				$document->addItem($item);
			}
		}
	}
}
?>
