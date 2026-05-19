<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

require_once __DIR__ . '/venueslist.php';

class JemModelTypevenues extends JemModelVenueslist
{
    protected function populateState($ordering = null, $direction = null)
    {
        $app    = Factory::getApplication();
        $typeId = $app->input->getInt('id', 0);

        parent::populateState($ordering, $direction);

        $this->setState('filter.type_id', $typeId);
    }

    public function getType()
    {
        $typeId = (int) $this->getState('filter.type_id');

        if (!$typeId) {
            return null;
        }

        $app      = Factory::getApplication();
        $user     = JemFactory::getUser();
        $levels   = $user->getAuthorisedViewLevels();
        $language = $app->getLanguage()->getTag();
        $db       = Factory::getContainer()->get('DatabaseDriver');
        $query    = $db->getQuery(true)
            ->select($db->quoteName(array('id', 'name', 'alias', 'icon', 'color', 'description', 'base_language', 'translation_languages', 'translations', 'language')))
            ->from($db->quoteName('#__jem_types'))
            ->where($db->quoteName('id') . ' = ' . $typeId)
            ->where($db->quoteName('entity') . ' = 3')
            ->where($db->quoteName('published') . ' = 1')
            ->where($db->quoteName('access') . ' IN (' . implode(',', array_map('intval', $levels)) . ')')
            ->where('('
                . $db->quoteName('language') . ' IN (' . $db->quote('*') . ', ' . $db->quote($language) . ')'
                . ' OR ' . $db->quoteName('base_language') . ' = ' . $db->quote($language)
                . ' OR ' . $db->quoteName('translation_languages') . ' LIKE ' . $db->quote('%' . $language . '%')
                . ')');

        $db->setQuery($query);
        $type = $db->loadObject();

        if ($type) {
            require_once JPATH_SITE . '/components/com_jem/classes/output.class.php';
            JemOutput::translateType($type);
        }

        return $type;
    }
}
