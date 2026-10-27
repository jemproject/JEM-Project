<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Version;
use Joomla\Database\DatabaseDriver;

require_once __DIR__ . '/housekeepingdefaults.class.php';

/**
 * Private, versioned diagnostic reports for download or explicit support mail.
 */
final class JemHousekeepingReports
{
    private const MAX_PAYLOAD_BYTES = 8388608;
    private const REDACTED = '[redacted]';

    private DatabaseDriver $db;

    public function __construct(?DatabaseDriver $db = null)
    {
        $this->db = $db ?? Factory::getContainer()->get(DatabaseDriver::class);
    }

    public function save(array $report, int $userId): int
    {
        $data = array(
            'schema' => 'jem-diagnostics/v1',
            'created_at' => Factory::getDate()->toSql(),
            'joomla_version' => (new Version())->getShortVersion(),
            'php_version' => PHP_VERSION,
            'report' => $report,
        );
        $payload = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
        if (strlen($payload) > self::MAX_PAYLOAD_BYTES) {
            throw new RuntimeException('The diagnostic report exceeds the safe storage limit.');
        }

        $row = (object) array(
            'created' => $data['created_at'],
            'created_by' => max(0, $userId),
            'payload' => $payload,
        );
        $this->db->insertObject('#__jem_diagnostic_reports', $row);

        return (int) $this->db->insertid();
    }

    public function load(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(array('id', 'created', 'created_by', 'payload')))
            ->from($this->db->quoteName('#__jem_diagnostic_reports'))
            ->where($this->db->quoteName('id') . ' = ' . $id);
        $this->db->setQuery($query);
        $row = $this->db->loadObject();
        if (!$row) {
            return null;
        }

        $data = json_decode((string) $row->payload, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data) || ($data['schema'] ?? '') !== 'jem-diagnostics/v1') {
            throw new RuntimeException('The stored diagnostic report has an unsupported format.');
        }

        return array(
            'id' => (int) $row->id,
            'created' => (string) $row->created,
            'created_by' => (int) $row->created_by,
            'data' => $data,
        );
    }

    public function recent(int $limit = 5): array
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(array('id', 'created', 'created_by')))
            ->from($this->db->quoteName('#__jem_diagnostic_reports'))
            ->order($this->db->quoteName('created') . ' DESC')
            ->order($this->db->quoteName('id') . ' DESC');
        $this->db->setQuery($query, 0, max(1, min(20, $limit)));

        return (array) $this->db->loadObjectList();
    }

    /** Remove one stored diagnostic report without touching other reports or site data. */
    public function delete(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        $query = $this->db->getQuery(true)
            ->delete($this->db->quoteName('#__jem_diagnostic_reports'))
            ->where($this->db->quoteName('id') . ' = ' . $id);
        $this->db->setQuery($query)->execute();

        return (int) $this->db->getAffectedRows() === 1;
    }

    public function export(array $stored, bool $includeNames, string $observations = '', array $selection = array()): string
    {
        $data = $stored['data'];
        $data['report_id'] = (int) $stored['id'];
        $data['include_names'] = $includeNames;
        $data['observations'] = trim($observations);
        // Keep the stored audit intact; support exports contain actionable findings only.
        $data['report']['lines'] = array_values(array_filter(
            $data['report']['lines'],
            static fn($line): bool => ($line['severity'] ?? '') !== 'notice'
        ));
        $data['report']['notice'] = 0;
        $data['report'] = $this->selectedReportSections($data['report'], $selection);

        if (!$includeNames) {
            foreach (array('module_items', 'plugin_items') as $key) {
                foreach ($data['report'][$key] as &$item) {
                    $item['name'] = '';
                }
                unset($item);
            }
            foreach ($data['report']['languages']['items'] as &$language) {
                $language['name'] = '';
            }
            unset($language);
            if (isset($data['report']['types']['items'])) {
                foreach ($data['report']['types']['items'] as &$type) {
                    $type['name'] = '';
                }
                unset($type);
            }
            if (isset($data['report']['menu_views']['items'])) {
                foreach ($data['report']['menu_views']['items'] as &$view) {
                    if (isset($view['menu_items'])) {
                        foreach ($view['menu_items'] as &$menuItem) {
                            unset($menuItem['title']);
                        }
                        unset($menuItem);
                    }
                }
                unset($view);
            }
            if (isset($data['report']['overrides']['templates'])) {
                foreach ($data['report']['overrides']['templates'] as &$override) {
                    $template = (string) $override['template'];
                    $override['template'] = self::REDACTED;
                    $override['winner'] = str_replace('/templates/' . $template . '/', '/templates/[redacted]/', $override['winner']);
                    $override['load_order'] = array_map(
                        static fn($path): string => str_replace('/templates/' . $template . '/', '/templates/[redacted]/', $path),
                        $override['load_order']
                    );
                }
                unset($override);
            }
            if (isset($data['report']['overrides']['css'])) {
                foreach ($data['report']['overrides']['css'] as &$override) {
                    $override['name'] = '';
                    $override['path'] = self::REDACTED;
                    $override['used_by'] = array();
                }
                unset($override);
            }
            foreach ($data['report']['lines'] as &$line) {
                $line['message'] = $line['code'] !== '' ? $line['code'] : $line['subject'];
            }
            unset($line);
        }

        foreach ($data['report']['lines'] as &$line) {
            unset($line['action_url'], $line['action_label']);
        }
        unset($line);

        $data['include_selected_settings'] = !empty($selection['settings']);
        $data['settings'] = $data['include_selected_settings']
            ? $this->selectedSettings($stored, $selection, $includeNames)
            : array();
        $data['language_files'] = array_map(
            static fn($file): array => array(
                'language' => $file['language'],
                'path' => $file['path'],
                'attachment_name' => $file['attachment_name'],
                'sha256' => $file['sha256'],
            ),
            $this->languageFiles($stored, $selection)
        );

        return json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
        );
    }

    /** Retain aggregate counters, but export only explicitly selected detail rows. */
    private function selectedReportSections(array $report, array $selection): array
    {
        if (empty($selection['inventory'])) {
            unset($report['inventory']);
        }
        if (empty($selection['override_templates'])) {
            unset($report['overrides']['templates']);
        }
        if (empty($selection['override_languages'])) {
            unset($report['overrides']['languages']);
        }
        if (empty($selection['override_css'])) {
            unset($report['overrides']['css']);
        }
        if (empty($report['overrides'])) {
            unset($report['overrides']);
        }
        $selectedTypes = array_fill_keys(array_map('intval', (array) ($selection['types'] ?? array())), true);
        if (isset($report['types']['items'])) {
            $report['types']['items'] = array_values(array_filter(
                $report['types']['items'],
                static fn($item): bool => isset($selectedTypes[(int) $item['id']])
            ));
        }
        foreach (array('module_items' => 'modules', 'plugin_items' => 'plugins') as $key => $selectionKey) {
            $selected = array_fill_keys(array_map('intval', (array) ($selection[$selectionKey] ?? array())), true);
            if (isset($report[$key])) {
                $report[$key] = array_values(array_filter(
                    $report[$key],
                    static fn($item): bool => !empty($item['active']) && isset($selected[(int) $item['id']])
                ));
            }
        }
        $selectedViews = array_fill_keys(array_map('strval', (array) ($selection['view_types'] ?? array())), true);
        if (isset($report['menu_views']['items'])) {
            $report['menu_views']['items'] = array_filter(
                $report['menu_views']['items'],
                static fn($item): bool => !empty($item['active']) && isset($item['menu_items'])
                    && isset($selectedViews[(string) $item['name']])
            );
        }
        $selectedLanguages = array_fill_keys(array_map('strval', (array) ($selection['languages'] ?? array())), true);
        if (isset($report['languages']['items'])) {
            $report['languages']['items'] = array_values(array_filter(
                $report['languages']['items'],
                static fn($item): bool => !empty($item['active']) && !empty($item['jem_installed'])
                    && isset($selectedLanguages[(string) $item['code']])
            ));
        }

        return $report;
    }

    /** The selection is intersected with active items in the saved report. */
    private function selectedSettings(array $stored, array $selection, bool $includeNames): array
    {
        $report = $stored['data']['report'];
        $defaults = new JemHousekeepingDefaults($this->db);
        $result = array('component' => null, 'views' => array(), 'modules' => array(), 'plugins' => array());

        if (!empty($selection['component'])) {
            try {
                $result['component'] = $this->componentSettings($defaults);
            } catch (\Throwable $error) {
                $result['component'] = array('error' => 'Configuration unavailable or invalid.');
            }
        }

        $scopes = array(
            'view' => array('selection' => 'view_types', 'result' => 'views'),
            'module' => array('selection' => 'modules', 'result' => 'modules', 'items' => $report['module_items'] ?? array()),
            'plugin' => array('selection' => 'plugins', 'result' => 'plugins', 'items' => $report['plugin_items'] ?? array()),
        );
        foreach ($scopes as $scope => $scopeData) {
            $allowed = array();
            if ($scope === 'view') {
                $selectedViews = array_fill_keys(array_map('strval', (array) ($selection['view_types'] ?? array())), true);
                foreach ($report['menu_views']['items'] ?? array() as $view) {
                    if (empty($view['active']) || !isset($selectedViews[$view['name']])) {
                        continue;
                    }
                    foreach ($view['menu_items'] ?? array() as $item) {
                        if (!empty($item['published'])) {
                            $allowed[(int) $item['id']] = true;
                        }
                    }
                }
            } else {
                foreach ($scopeData['items'] as $item) {
                    if (!empty($item['active'])) {
                        $allowed[(int) $item['id']] = true;
                    }
                }
            }
            $selectedIds = $scope === 'view'
                ? array_keys($allowed)
                : array_unique(array_map('intval', (array) ($selection[$scopeData['selection']] ?? array())));
            foreach ($selectedIds as $id) {
                if ($id <= 0 || !isset($allowed[$id]) || !$this->stillActive($scope, $id)) {
                    continue;
                }
                try {
                    $catalog = $defaults->catalog($scope, $id);
                    $fields = array();
                    foreach ($catalog['fields'] as $field) {
                        $fields[$field['key']] = $this->safeValue($field['current']);
                    }
                    $result[$scopeData['result']][] = array(
                        'id' => $id,
                        'name' => $includeNames ? $catalog['title'] : '',
                        'settings' => $fields,
                    );
                } catch (\Throwable $error) {
                    $result[$scopeData['result']][] = array('id' => $id, 'error' => 'Settings form unavailable or invalid.');
                }
            }
        }

        return $result;
    }

    private function componentSettings(JemHousekeepingDefaults $defaults): array
    {
        $catalog = $defaults->catalog('component', 0);
        $safe = array_fill_keys(array_keys($catalog['fields']), true);
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(array('keyname', 'value')))
            ->from($this->db->quoteName('#__jem_config'));
        $this->db->setQuery($query);
        $settings = array();
        foreach ((array) $this->db->loadObjectList() as $row) {
            $key = (string) $row->keyname;
            $value = (string) $row->value;
            if (in_array($key, array('globalattribs', 'css'), true)) {
                $decoded = json_decode($value, true);
                $group = array();
                if (is_array($decoded)) {
                    foreach ($decoded as $name => $nested) {
                        $group[$name] = isset($safe[$key . '.' . $name])
                            ? $this->safeValue($nested) : self::REDACTED;
                    }
                }
                $settings[$key] = $group;
                continue;
            }
            $settings[$key] = isset($safe[$key]) ? $this->safeValue($value) : self::REDACTED;
        }

        return $settings;
    }

    private function safeValue($value)
    {
        if (is_array($value)) {
            if (count($value) > 50) {
                return self::REDACTED;
            }
            $safe = array();
            foreach ($value as $key => $item) {
                $safeKey = is_int($key) || preg_match('/^[a-z0-9_-]{1,64}$/iD', (string) $key)
                    ? $key : '[redacted-key-' . count($safe) . ']';
                $safe[$safeKey] = $this->safeValue($item);
            }

            return $safe;
        }
        if (!is_scalar($value) && $value !== null) {
            return self::REDACTED;
        }
        $text = (string) $value;
        if (strlen($text) > 200 || preg_match('/[@:\/\\\\\r\n]|(?:password|secret|token|api[_-]?key)/i', $text)) {
            return self::REDACTED;
        }

        return $value;
    }

    private function stillActive(string $scope, int $id): bool
    {
        if ($scope === 'view') {
            $table = '#__menu';
            $idColumn = 'id';
            $stateColumn = 'published';
        } elseif ($scope === 'module') {
            $table = '#__modules';
            $idColumn = 'id';
            $stateColumn = 'published';
        } else {
            $table = '#__extensions';
            $idColumn = 'extension_id';
            $stateColumn = 'enabled';
        }
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName($stateColumn))
            ->from($this->db->quoteName($table))
            ->where($this->db->quoteName($idColumn) . ' = ' . $id);
        $this->db->setQuery($query);

        return (int) $this->db->loadResult() === 1;
    }

    /** Return only JEM-owned INI files for selected, active installed languages. */
    public function languageFiles(array $stored, array $selection): array
    {
        $allowed = array();
        foreach ($stored['data']['report']['languages']['items'] ?? array() as $language) {
            if (!empty($language['active']) && !empty($language['jem_installed'])
                && preg_match('/^[a-z]{2,3}-[A-Z]{2}$/D', (string) $language['code'])) {
                $allowed[(string) $language['code']] = true;
            }
        }
        $names = array(
            'com_jem', 'pkg_jem', 'mod_jem', 'mod_jem_banner', 'mod_jem_cal',
            'mod_jem_jubilee', 'mod_jem_map', 'mod_jem_teaser', 'mod_jem_types', 'mod_jem_wide',
            'plg_actionlog_jem', 'plg_content_jemembed', 'plg_content_jemlistevents',
            'plg_finder_jem', 'plg_jem_comments', 'plg_jem_mailer', 'plg_quickicon_jem',
            'plg_task_jem', 'plg_user_jem',
        );
        $files = array();
        foreach (array_unique(array_map('strval', (array) ($selection['languages'] ?? array()))) as $code) {
            if (!isset($allowed[$code]) || !$this->languageStillActive($code)) {
                continue;
            }
            foreach (array('administrator' => JPATH_ADMINISTRATOR, 'site' => JPATH_SITE) as $client => $base) {
                foreach ($names as $name) {
                    foreach (array('.ini', '.sys.ini') as $suffix) {
                        $filename = $name . $suffix;
                        $path = $base . '/language/' . $code . '/' . $filename;
                        $resolved = realpath($path);
                        $expected = str_replace('\\', '/', realpath($base) . '/language/' . $code . '/');
                        if ($resolved === false || is_link($path)
                            || !str_starts_with(str_replace('\\', '/', $resolved), $expected)
                            || !is_file($path) || filesize($path) > 524288) {
                            continue;
                        }
                        $relative = ($client === 'administrator' ? 'administrator/' : '')
                            . 'language/' . $code . '/' . $filename;
                        $files[] = array(
                            'language' => $code,
                            'path' => $relative,
                            'attachment_name' => $client . '_' . $code . '_' . $filename,
                            'content' => (string) file_get_contents($path),
                            'sha256' => hash_file('sha256', $path),
                        );
                    }
                }
            }
        }

        return $files;
    }

    private function languageStillActive(string $code): bool
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('published'))
            ->from($this->db->quoteName('#__languages'))
            ->where($this->db->quoteName('lang_code') . ' = ' . $this->db->quote($code));
        $this->db->setQuery($query);

        return (int) $this->db->loadResult() === 1;
    }
}
