<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\LanguageHelper;
use Joomla\CMS\Language\Multilanguage;
use Joomla\Registry\Registry;

require_once JPATH_SITE . '/components/com_jem/helpers/helper.php';
require_once JPATH_SITE . '/components/com_jem/helpers/route.php';

/**
 * JEM component association helper.
 *
 * The event view represents one event in every site language. When an event uses
 * a Joomla article as event content, the article association changes the text,
 * but the JEM event id must stay stable while switching languages.
 */
abstract class JemHelperAssociation
{
    /**
     * Return language switcher routes for the current item.
     *
     * @param   integer|null  $id      Current item id.
     * @param   string|null   $view    Current view.
     * @param   string|null   $layout  Current layout.
     *
     * @return  array
     */
    public static function getAssociations($id = 0, $view = null, $layout = null)
    {
        if (!Multilanguage::isEnabled()) {
            return array();
        }

        $app   = Factory::getApplication();
        $input = $app->getInput();
        $view  = $view ?: $input->getCmd('view');

        if ($view !== 'event') {
            return array();
        }

        $id = (int) ($id ?: $input->getInt('id'));

        if ($id <= 0) {
            return array();
        }

        $event = self::getEventForRoute($id);

        if (!$event) {
            return array();
        }

        $levels   = Factory::getUser()->getAuthorisedViewLevels();
        $return   = array();
        $languages = LanguageHelper::getLanguages();

        foreach ($languages as $language) {
            if (!isset($language->lang_code) || !array_key_exists($language->lang_code, LanguageHelper::getInstalledLanguages(0))) {
                continue;
            }

            $target = clone $event;
            JemHelper::applyAssociatedArticleEventContent($target, $levels, $language->lang_code);

            $slug = !empty($target->alias) ? ((int) $target->id . ':' . $target->alias) : (int) $target->id;
            $return[$language->lang_code] = JEMHelperRoute::getEventRoute($slug, null, $language->lang_code);
        }

        return $return;
    }

    /**
     * Load the event fields needed to build language-switcher routes.
     *
     * @param   integer  $id  Event id.
     *
     * @return  object|null
     */
    protected static function getEventForRoute($id)
    {
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName(array('id', 'title', 'alias', 'article_id', 'attribs', 'introtext', 'fulltext', 'metadata', 'meta_keywords', 'meta_description')))
            ->from($db->quoteName('#__jem_events'))
            ->where($db->quoteName('id') . ' = ' . (int) $id);

        $db->setQuery($query);

        try {
            $event = $db->loadObject();
        } catch (Exception $e) {
            return null;
        }

        if (!$event) {
            return null;
        }

        $event->params = JemHelper::globalattribs();

        $registry = new Registry;
        $registry->loadString((string) ($event->attribs ?? '{}'));
        $event->params->merge($registry);

        return $event;
    }
}
