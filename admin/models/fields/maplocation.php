<?php
/**
 * @package JEM
 * @copyright (C) 2013-2026 joomlaeventmanager.net
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/** Parent-dependent menu defaults using JEM's existing County/Region field. */
class JFormFieldMapLocation extends ListField
{
    protected $type = 'MapLocation';

    /**
     * Read distinct existing locations once per form request.
     *
     * @return array<array{country:string,county:string,city:string}> Location choices.
     */
    private function locations(): array
    {
        static $rows;
        if ($rows === null) {
            $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select('DISTINCT ' . $db->quoteName('country') . ', '
                    . $db->quoteName('region') . ', ' . $db->quoteName('state', 'county') . ', ' . $db->quoteName('city'))
                ->from($db->quoteName('#__jem_venues'));
            $rows = $db->setQuery($query)->loadAssocList() ?: [];
            foreach ($rows as &$row) {
                $row = array_map(static fn ($value) => (string) ($value ?? ''), $row);
            }
            unset($row);
        }

        return $rows;
    }

    /**
     * Build defaults narrowed by country and, for city, County/Region.
     * Existing configured values remain visible instead of being silently discarded.
     *
     * @return array Joomla select options.
     */
    protected function getOptions()
    {
        $level = in_array((string) $this->element['level'], ['region', 'county', 'city'], true)
            ? (string) $this->element['level'] : 'county';
        $country = (string) $this->form->getValue('default_country', 'params', '');
        $country = $country === '0' ? '' : $country;
        $county = (string) $this->form->getValue('default_county', 'params', '');
        $region = (string) $this->form->getValue('default_region', 'params', '');
        $values = [];
        foreach ($this->locations() as $row) {
            if (($country !== '' && $row['country'] !== $country)
                || ($level !== 'region' && $region !== '' && $row['region'] !== $region)
                || ($level === 'city' && $county !== '' && $row['county'] !== $county)) {
                continue;
            }
            if (trim($row[$level]) !== '') {
                $values[] = $row[$level];
            }
        }
        if (trim((string) $this->value) !== '') {
            $values[] = (string) $this->value;
        }
        $values = array_unique($values);
        natcasesort($values);
        $options = [HTMLHelper::_('select.option', '', Text::_($level === 'region'
            ? 'COM_JEM_EVENTS_MAP_ALL_REGIONS' : ($level === 'county'
                ? 'COM_JEM_EVENTS_MAP_ALL_COUNTIES' : 'COM_JEM_EVENTS_MAP_ALL_CITIES')))];
        foreach ($values as $value) {
            $options[] = HTMLHelper::_('select.option', $value, $value);
        }

        return $options;
    }

    /**
     * Register safe JSON data and the parent-change listener for this selector.
     *
     * @return string Rendered native select; no inline JavaScript or unescaped data.
     */
    protected function getInput()
    {
        $document = Factory::getApplication()->getDocument();
        $document->addScriptOptions('jem.map.locations', $this->locations(), false);
        $document->addScriptOptions('jem.map.location-fields', [$this->id => [
            'level' => in_array((string) $this->element['level'], ['region', 'county', 'city'], true)
                ? (string) $this->element['level'] : 'county',
            'country' => preg_replace('/\[[^\]]+\]$/', '[default_country]', $this->name),
            'region' => preg_replace('/\[[^\]]+\]$/', '[default_region]', $this->name),
            'county' => preg_replace('/\[[^\]]+\]$/', '[default_county]', $this->name),
        ]]);
        $document->getWebAssetManager()->registerAndUseScript(
            'com_jem.map-location-options',
            'media/com_jem/js/map-location-options.js',
            [],
            ['defer' => true],
            ['core']
        );

        return parent::getInput();
    }
}
