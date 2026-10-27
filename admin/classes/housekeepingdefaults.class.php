<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseDriver;

/**
 * Discover and restore explicitly declared JEM form defaults, one target at a time.
 */
final class JemHousekeepingDefaults
{
    private const MODULES = array(
        'mod_jem', 'mod_jem_banner', 'mod_jem_cal', 'mod_jem_jubilee',
        'mod_jem_map', 'mod_jem_teaser', 'mod_jem_types', 'mod_jem_wide',
    );

    private const PLUGINS = array(
        'actionlog/jem', 'content/jemembed', 'content/jemlistevents',
        'finder/jem', 'jem/comments', 'jem/mailer', 'quickicon/jem',
        'task/jem', 'user/jem',
    );

    /** Fields whose reset could change security, data ownership or external integrations. */
    private const EXCLUDED = array(
        'operating_profile', 'operating_profile_configured',
        'access_level_locked_events', 'access_level_locked_venues', 'access_level_locked_categories',
        'attachments_path', 'flagicons_path', 'defaultCategory', 'defaultVenue',
        'defaultCountry', 'defaultCurrency', 'storeip', 'storeipmode',
        'reminders_enabled', 'actionlog_enabled', 'global_cleanup_db_on_uninstall',
        'global_googleapi', 'global_googleclientid', 'allowed_link_schemes',
        'allowed_link_extensions', 'api_tokens', 'base_url', 'admin_receivers',
        'mailer_profiles', 'introtext', 'footertext', 'no_events_msg',
    );

    private DatabaseDriver $db;

    public function __construct(?DatabaseDriver $db = null)
    {
        $this->db = $db ?? Factory::getContainer()->get(DatabaseDriver::class);
    }

    /**
     * Return only JEM-owned targets; Joomla publication, ACL and assignment are not resettable.
     */
    public function targets(string $scope): array
    {
        if ($scope === 'component') {
            return array(array('id' => 0, 'title' => Text::_('COM_JEM_MENU_SETTINGS')));
        }

        if ($scope === 'view') {
            $query = $this->db->getQuery(true)
                ->select($this->db->quoteName(array('id', 'title', 'link')))
                ->from($this->db->quoteName('#__menu'))
                ->where($this->db->quoteName('client_id') . ' = 0')
                ->where($this->db->quoteName('link') . ' LIKE ' . $this->db->quote('%option=com_jem%'))
                ->order($this->db->quoteName('title'));
            $this->db->setQuery($query);
            return array_map(static function ($item) {
                return array('id' => (int) $item->id, 'title' => (string) $item->title);
            }, (array) $this->db->loadObjectList());
        }

        if ($scope === 'module') {
            $query = $this->db->getQuery(true)
                ->select($this->db->quoteName(array('id', 'title', 'module')))
                ->from($this->db->quoteName('#__modules'))
                ->where($this->db->quoteName('module') . ' IN (' . $this->quoteList(self::MODULES) . ')')
                ->where($this->db->quoteName('client_id') . ' = 0')
                ->where($this->db->quoteName('published') . ' != -2')
                ->order($this->db->quoteName('title'));
            $this->db->setQuery($query);
            return array_map(static function ($item) {
                return array('id' => (int) $item->id, 'title' => (string) $item->title . ' (' . $item->module . ')');
            }, (array) $this->db->loadObjectList());
        }

        if ($scope === 'plugin') {
            $query = $this->db->getQuery(true)
                ->select($this->db->quoteName(array('extension_id', 'folder', 'element')))
                ->from($this->db->quoteName('#__extensions'))
                ->where($this->db->quoteName('type') . ' = ' . $this->db->quote('plugin'))
                ->order($this->db->quoteName('folder') . ', ' . $this->db->quoteName('element'));
            $this->db->setQuery($query);
            $targets = array();
            foreach ((array) $this->db->loadObjectList() as $item) {
                $name = (string) $item->folder . '/' . (string) $item->element;
                if (in_array($name, self::PLUGINS, true)) {
                    $targets[] = array('id' => (int) $item->extension_id, 'title' => $name);
                }
            }
            return $targets;
        }

        throw new InvalidArgumentException('Invalid JEM settings scope.');
    }

    /**
     * Build a selectable catalogue from installed manifests and the target's current values.
     */
    public function catalog(string $scope, int $id): array
    {
        $target = $this->resolveTarget($scope, $id);
        $xml = simplexml_load_file($target['form'], 'SimpleXMLElement', LIBXML_NONET);
        if ($xml === false) {
            throw new RuntimeException('Unable to read the JEM settings form.');
        }

        $fields = array();
        foreach ($xml->xpath('//fieldset') ?: array() as $fieldset) {
            $group = Text::_((string) ($fieldset['label'] ?: $fieldset['name']));
            foreach ($fieldset->xpath('./field') ?: array() as $field) {
                $name = (string) $field['name'];
                $type = strtolower((string) $field['type']);
                if ($name === '' || !preg_match('/^[a-z][a-z0-9_-]*$/iD', $name)
                    || !isset($field['default'])
                    || in_array($type, array('hidden', 'spacer', 'note', 'rules', 'accesslevel', 'usergroup', 'usergroups', 'password', 'file'), true)
                    || in_array($name, self::EXCLUDED, true)
                    || preg_match('/(?:password|secret|token|api_?key|credential|access|path|directory|email|recipient|endpoint|webhook|licen[cs]e)/i', $name)) {
                    continue;
                }

                $parents = $field->xpath('ancestor::fields[1]');
                $storage = $scope === 'component' && $parents
                    ? (string) $parents[0]['name'] : '';
                if ($scope === 'component' && $storage !== ''
                    && !in_array($storage, array('globalattribs', 'css'), true)) {
                    continue;
                }
                $key = $storage !== '' ? $storage . '.' . $name : $name;
                $default = (string) $field['default'];
                $current = $storage !== ''
                    ? ($target['values'][$storage][$name] ?? null)
                    : ($target['values'][$name] ?? null);
                $fields[$key] = array(
                    'key' => $key,
                    'name' => $name,
                    'storage' => $storage,
                    'label' => Text::_((string) ($field['label'] ?: $name)),
                    'group' => $group,
                    'current' => $current,
                    'default' => $default,
                    'changed' => $current === null || $this->comparable($current) !== $default,
                );
            }
        }

        return array(
            'scope' => $scope,
            'id' => $id,
            'title' => $target['title'],
            'fields' => $fields,
        );
    }

    /**
     * Preview only changed fields, never accepting a posted default value.
     */
    public function preview(string $scope, int $id, array $names): array
    {
        $catalog = $this->catalog($scope, $id);
        $names = array_values(array_unique(array_map('strval', $names)));
        if (!$names || count($names) > 400) {
            throw new InvalidArgumentException('Select between 1 and 400 settings.');
        }

        $changes = array();
        foreach ($names as $name) {
            if (!isset($catalog['fields'][$name])) {
                throw new InvalidArgumentException('A selected setting is not resettable.');
            }
            if ($catalog['fields'][$name]['changed']) {
                $changes[$name] = $catalog['fields'][$name];
            }
        }

        return array(
            'scope' => $scope,
            'id' => $id,
            'changes' => $changes,
            'fingerprint' => hash('sha256', json_encode($changes, JSON_UNESCAPED_SLASHES)),
        );
    }

    /**
     * Apply the exact previewed change set if nothing has changed since preview.
     */
    public function apply(array $preview): array
    {
        $scope = (string) ($preview['scope'] ?? '');
        $id = (int) ($preview['id'] ?? 0);
        $names = array_keys((array) ($preview['changes'] ?? array()));
        $fresh = $this->preview($scope, $id, $names);
        if (!$fresh['changes'] || !hash_equals((string) ($preview['fingerprint'] ?? ''), $fresh['fingerprint'])) {
            throw new RuntimeException('The settings changed after preview. Preview them again.');
        }

        $target = $this->resolveTarget($scope, $id);
        if ($scope === 'component') {
            $this->validateComponentImageBounds($target['values'], $fresh['changes']);
        }

        $this->db->transactionStart();
        try {
            if ($scope === 'component') {
                $values = $target['values'];
                foreach ($fresh['changes'] as $field) {
                    $name = $field['name'];
                    $storage = $field['storage'];
                    if ($storage !== '') {
                        $values[$storage][$name] = $field['default'];
                        continue;
                    }
                    if (array_key_exists($name, $values)) {
                        $query = $this->db->getQuery(true)
                            ->update($this->db->quoteName('#__jem_config'))
                            ->set($this->db->quoteName('value') . ' = ' . $this->db->quote($field['default']))
                            ->where($this->db->quoteName('keyname') . ' = ' . $this->db->quote($name));
                    } else {
                        $query = $this->db->getQuery(true)
                            ->insert($this->db->quoteName('#__jem_config'))
                            ->columns($this->db->quoteName(array('keyname', 'value')))
                            ->values($this->db->quote($name) . ', ' . $this->db->quote($field['default']));
                    }
                    $this->db->setQuery($query)->execute();
                }
                foreach (array('globalattribs', 'css') as $storage) {
                    if (!isset($values[$storage]) || $values[$storage] === $target['values'][$storage]) {
                        continue;
                    }
                    $encoded = json_encode($values[$storage], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                    if (isset($target['present'][$storage])) {
                        $query = $this->db->getQuery(true)
                            ->update($this->db->quoteName('#__jem_config'))
                            ->set($this->db->quoteName('value') . ' = ' . $this->db->quote($encoded))
                            ->where($this->db->quoteName('keyname') . ' = ' . $this->db->quote($storage));
                    } else {
                        $query = $this->db->getQuery(true)
                            ->insert($this->db->quoteName('#__jem_config'))
                            ->columns($this->db->quoteName(array('keyname', 'value')))
                            ->values($this->db->quote($storage) . ', ' . $this->db->quote($encoded));
                    }
                    $this->db->setQuery($query)->execute();
                }
            } else {
                $values = $target['values'];
                foreach ($fresh['changes'] as $name => $field) {
                    $values[$name] = $field['default'];
                }
                $query = $this->db->getQuery(true)
                    ->update($this->db->quoteName($target['table']))
                    ->set($this->db->quoteName('params') . ' = ' . $this->db->quote(json_encode($values, JSON_THROW_ON_ERROR)))
                    ->where($this->db->quoteName($target['id_column']) . ' = ' . $id);
                $this->db->setQuery($query)->execute();
            }
            $this->db->transactionCommit();
        } catch (Throwable $error) {
            $this->db->transactionRollback();
            throw $error;
        }

        return array_values($fresh['changes']);
    }

    private function resolveTarget(string $scope, int $id): array
    {
        if ($scope === 'component' && $id === 0) {
            $query = $this->db->getQuery(true)
                ->select($this->db->quoteName(array('keyname', 'value')))
                ->from($this->db->quoteName('#__jem_config'));
            $this->db->setQuery($query);
            $values = array();
            foreach ((array) $this->db->loadObjectList() as $row) {
                $values[(string) $row->keyname] = (string) $row->value;
            }
            $present = array_fill_keys(array_keys($values), true);
            foreach (array('globalattribs', 'css') as $storage) {
                $raw = (string) ($values[$storage] ?? '');
                $decoded = $raw === '' ? array() : json_decode($raw, true);
                if (!is_array($decoded)) {
                    throw new RuntimeException('A stored JEM settings group is not valid JSON. No value was changed.');
                }
                $values[$storage] = is_array($decoded) ? $decoded : array();
            }
            return array(
                'title' => Text::_('COM_JEM_MENU_SETTINGS'),
                'form' => JPATH_ADMINISTRATOR . '/components/com_jem/models/forms/settings.xml',
                'values' => $values,
                'present' => $present,
            );
        }

        $targets = $this->targets($scope);
        $allowed = array_column($targets, 'title', 'id');
        if ($id <= 0 || !isset($allowed[$id])) {
            throw new InvalidArgumentException('This JEM settings target is not available.');
        }

        if ($scope === 'view') {
            $table = '#__menu';
            $idColumn = 'id';
            $columns = array('link', 'params');
        } elseif ($scope === 'module') {
            $table = '#__modules';
            $idColumn = 'id';
            $columns = array('module', 'params');
        } else {
            $table = '#__extensions';
            $idColumn = 'extension_id';
            $columns = array('folder', 'element', 'params');
        }
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName($columns))
            ->from($this->db->quoteName($table))
            ->where($this->db->quoteName($idColumn) . ' = ' . $id);
        $this->db->setQuery($query);
        $row = $this->db->loadObject();
        if (!$row) {
            throw new RuntimeException('The selected settings target no longer exists.');
        }

        if ($scope === 'view') {
            $parts = parse_url((string) $row->link);
            $queryParams = array();
            parse_str((string) ($parts['query'] ?? ''), $queryParams);
            $view = (string) ($queryParams['view'] ?? '');
            $savedParams = json_decode((string) $row->params, true);
            $layout = (string) ($queryParams['layout'] ?? ($savedParams['layout'] ?? 'default'));
            if (($queryParams['option'] ?? '') !== 'com_jem'
                || !preg_match('/^[a-z0-9_]+$/D', $view)
                || !preg_match('/^[a-z0-9_]+$/D', $layout)) {
                throw new RuntimeException('This menu item does not use a standard JEM view form.');
            }
            $form = JPATH_SITE . '/components/com_jem/views/' . $view . '/tmpl/' . $layout . '.xml';
        } elseif ($scope === 'module') {
            $module = (string) $row->module;
            $form = JPATH_SITE . '/modules/' . $module . '/' . $module . '.xml';
        } else {
            $folder = (string) $row->folder;
            $element = (string) $row->element;
            $form = JPATH_PLUGINS . '/' . $folder . '/' . $element . '/' . $element . '.xml';
        }
        if (!is_file($form)) {
            throw new RuntimeException('The installed JEM settings form is missing.');
        }

        $rawParams = (string) $row->params;
        $values = $rawParams === '' ? array() : json_decode($rawParams, true);
        if (!is_array($values)) {
            throw new RuntimeException('The selected item has invalid JSON parameters. No value was changed.');
        }
        return array(
            'title' => $allowed[$id],
            'form' => $form,
            'values' => $values,
            'table' => $table,
            'id_column' => $idColumn,
        );
    }

    private function validateComponentImageBounds(array $values, array $changes): void
    {
        require_once JPATH_SITE . '/components/com_jem/classes/imageprofilepolicy.class.php';

        foreach ($changes as $field) {
            if ($field['storage'] !== '') {
                $values[$field['storage']][$field['name']] = $field['default'];
            } else {
                $values[$field['name']] = $field['default'];
            }
        }
        $minimum = (int) ($values['image_min_dimension'] ?? JemImageProfilePolicy::DEFAULT_MIN_DIMENSION);
        $maximum = (int) ($values['image_max_dimension'] ?? JemImageProfilePolicy::DEFAULT_CONFIGURED_MAX_DIMENSION);
        if ($minimum < JemImageProfilePolicy::MIN_CONFIGURED_MIN_DIMENSION
            || $maximum < JemImageResourcePolicy::MIN_CONFIGURED_DIMENSION
            || $maximum > JemImageResourcePolicy::MAX_CONFIGURED_DIMENSION
            || $minimum > $maximum) {
            throw new RuntimeException('The selected image defaults conflict with the current image dimension limits.');
        }

        foreach (array(
            JemImageProfilePolicy::EVENT_INTRO,
            JemImageProfilePolicy::EVENT_FULL,
            JemImageProfilePolicy::VENUE,
            JemImageProfilePolicy::CATEGORY,
        ) as $profile) {
            $prefix = JemImageProfilePolicy::prefix($profile);
            $resolved = JemImageProfilePolicy::resolve($values, $profile);
            if ($resolved['mode'] !== JemImageProfilePolicy::MODE_NONE
                && !JemImageProfilePolicy::ratioFitsBounds(
                    $minimum,
                    $maximum,
                    $resolved['ratio_width'],
                    $resolved['ratio_height']
                )) {
                throw new RuntimeException('The selected image defaults conflict with the current image ratio.');
            }
            $dimension = (int) ($values[$prefix . '_default_dimension'] ?? 0);
            if ($dimension > 0 && ($dimension < JemImageProfilePolicy::minimumOutputMaxDimension($values, $profile)
                || $dimension > $maximum)) {
                throw new RuntimeException('The selected image defaults conflict with a profile output dimension.');
            }
        }
    }

    private function comparable($value): string
    {
        return is_array($value) ? json_encode($value) : (string) $value;
    }

    private function quoteList(array $values): string
    {
        return implode(',', array_map(array($this->db, 'quote'), $values));
    }
}
