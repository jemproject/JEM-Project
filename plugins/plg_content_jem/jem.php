<?php
/**
 * @package    JEM
 * @subpackage JEM Content Plugin
 * @copyright  (C) 2013-2025 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Application\ApplicationHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Database\DatabaseInterface;

/**
 * JEM Content Plugin
 *
 * @package    JEM.Plugin
 * @subpackage Content.jem
 * @since      1.9.6
 */
class plgContentJem extends CMSPlugin
{
    /**
     * Dissolve recurrence sets where deleted event is referred to as first.
     *
     * @param   string  $context  The context for the content passed to the plugin.
     * @param   object  $data     The data relating to the content that was deleted.
     *
     * @return  void
     */
    public function onContentAfterDelete($context, $data)
    {
        // Skip plugin if we are deleting something other than events
        if (($context != 'com_jem.event') || empty($data->id)) {
            return;
        }

        // event maybe first of recurrence set -> dissolve complete set
        JemHelper::dissolve_recurrence($data->id);
    }

    /**
     * Create a Joomla article when a new JEM event is saved
     *
     * @param   string  $context  The context of the content passed to the plugin
     * @param   object  $item     The JEM event object
     * @param   bool    $isNew    If the content is just about to be created
     *
     * @return  bool
     */
    public function onContentAfterSave($context, $item, $isNew, $data)
    {
        if ($context !== 'com_jem.event' || !$isNew) {
            return true;
        }

        // Get the first category of event
        $item->catid = $data["cats"][0];

        // Article structure
        try {
            $article = [
                'title' => $item->title,
                'alias' => ApplicationHelper::stringURLSafe($item->title),
                'introtext' => $this->generateArticleContent($item),
                'catid' => $this->getArticleCategoryId($item->catid),
                'state' => 1,
                'access' => 1,
                'language' => '*',
                'created' => $item->created,
                'created_by' => $item->created_by,
                'publish_up' => $item->dates ?: $item->created,
                'articletext' => '', // Required field
                'metakey' => '',
                'metadesc' => '',
                'metadata' => '{"robots":"","author":"","rights":"","xreference":""}'
            ];

            $table = Table::getInstance('Content');
            if (!$table->bind($article) || !$table->check() || !$table->store()) {
                throw new \RuntimeException($table->getError());
            }

            Factory::getApplication()->enqueueMessage(
                Text::sprintf('PLG_CONTENT_JEM_ARTICLE_CREATED', $article['title']),
                'message'
            );
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('PLG_CONTENT_JEM_ERROR', $e->getMessage()),
                'error'
            );
        }

        return true;
    }

    /**
     * Generate HTML content for the article
     *
     * @param   object  $event  The JEM event object
     *
     * @return  string
     */
    private function generateArticleContent($event): string
    {
        $content = '<h2>' . Text::_('PLG_CONTENT_JEM_EVENT_DETAILS') . '</h2>';
        $content .= '<p><strong>' . Text::_('PLG_CONTENT_JEM_DATE') . '</strong>: ' .
            HTMLHelper::_('date', $event->dates, Text::_('DATE_FORMAT_LC2')) . '</p>';

        if (!empty($event->enddates)) {
            $content .= '<p><strong>' . Text::_('PLG_CONTENT_JEM_END_DATE') . '</strong>: ' .
                HTMLHelper::_('date', $event->enddates, Text::_('DATE_FORMAT_LC2')) . '</p>';
        }

        $content .= '<p><strong>' . Text::_('PLG_CONTENT_JEM_VENUE') . '</strong>: ' .
            $this->getVenueName($event->locid) . '</p>';
        $content .= '<p><strong>' . Text::_('PLG_CONTENT_JEM_DESCRIPTION') . '</strong>:</p>' .
            $event->introtext;

        return $content;
    }

    /**
     * Get venue name from ID
     *
     * @param   int  $locid  The venue ID
     *
     * @return  string
     */
    private function getVenueName($locid): string
    {
        if (!$locid) {
            return '';
        }

        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('venue')
            ->from('#__jem_venues')
            ->where('id = ' . (int)$locid);

        return $db->setQuery($query)->loadResult() ?: '';
    }

    /**
     * Map JEM category to Joomla article category
     *
     * @param   int  $jemCatId  The JEM category ID
     *
     * @return  int
     */
    private function getArticleCategoryId($jemCatId): int
    {
        $db = Factory::getDbo();

        // Get JEM category name
        $query = $db->getQuery(true)
            ->select('catname')
            ->from('#__jem_categories')
            ->where('id = ' . (int)$jemCatId);
        $catName = $db->setQuery($query)->loadResult();

        if (!$catName) {
            return 2; // Default to Uncategorised
        }

        // Find matching Joomla article category
        $query = $db->getQuery(true)
            ->select('id')
            ->from('#__categories')
            ->where('extension = ' . $db->quote('com_content'))
            ->where('title = ' . $db->quote($catName));

        return (int) ($db->setQuery($query)->loadResult() ?: 2);
    }
}