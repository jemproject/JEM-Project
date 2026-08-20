<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;

require_once JPATH_SITE . '/components/com_jem/classes/money.class.php';

class JFormFieldCurrencyOptions extends ListField
{
    protected $type = 'CurrencyOptions';

    protected function getOptions()
    {
        $options = array();
        $currencies = array();
        try {
            $db = Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true)
                ->select('DISTINCT ' . $db->quoteName('currency'))
                ->from($db->quoteName('#__jem_countries'))
                ->where($db->quoteName('currency') . " <> ''")
                ->order($db->quoteName('currency') . ' ASC');
            $db->setQuery($query);
            foreach ((array) $db->loadColumn() as $currency) {
                $currency = strtoupper(trim((string) $currency));
                if (preg_match('/^[A-Z]{3}$/D', $currency) === 1) {
                    $currencies[$currency] = true;
                }
            }
        } catch (Throwable $e) {
            // Keep the event form usable while an update is still running.
        }

        ksort($currencies, SORT_STRING);
        $locale = Factory::getApplication()->getLanguage()->getTag();
        foreach (array_keys($currencies) as $currency) {
            $options[] = HTMLHelper::_('select.option', $currency, JemMoney::currencyLabel($currency, $locale));
        }

        return array_merge(parent::getOptions(), $options);
    }
}
