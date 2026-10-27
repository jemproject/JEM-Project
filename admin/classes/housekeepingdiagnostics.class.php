<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Access\Access;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseDriver;

require_once __DIR__ . '/../helpers/languagecatalog.php';
require_once __DIR__ . '/housekeepingoverrides.class.php';

/**
 * Read-only checks for the JEM housekeeping diagnostics screen.
 */
final class JemHousekeepingDiagnostics
{
    private DatabaseDriver $db;

    private array $frontendSource = array();

    public function __construct(?DatabaseDriver $db = null)
    {
        $this->db = $db ?? Factory::getContainer()->get(DatabaseDriver::class);
    }

    /**
     * Audit all JEM content and related Joomla configuration without changing data.
     */
    public function run(): array
    {
        $this->frontendSource = array();
        $report = array(
            'notice' => 0,
            'warning' => 0,
            'error' => 0,
            'lines' => array(),
            'modules' => array('total' => 0, 'active' => 0),
            'plugins' => array('total' => 0, 'active' => 0),
            'component' => array(
                'id' => 0, 'version' => '', 'schema_version' => '', 'version_date' => '',
                'installed_at' => '', 'installed_action' => '', 'timezone' => '', 'active' => false,
            ),
            'languages' => array('total' => 0, 'active' => 0, 'items' => array()),
            'menu_views' => array('total' => 0, 'active' => 0, 'items' => array()),
            'inventory' => array(
                'events' => array(
                    'total' => 0, 'active' => 0, 'public' => 0, 'private' => 0,
                    'archived' => 0, 'trashed' => 0, 'last_id' => 0,
                ),
                'categories' => array(
                    'total' => 0, 'active' => 0, 'public' => 0, 'private' => 0,
                    'archived' => 0, 'trashed' => 0, 'last_id' => 0,
                ),
                'venues' => array(
                    'total' => 0, 'active' => 0, 'public' => 0, 'private' => 0,
                    'archived' => 0, 'trashed' => 0, 'last_id' => 0,
                ),
                'types' => array(
                    'total' => 0, 'active' => 0, 'archived' => 0, 'trashed' => 0, 'last_id' => 0,
                ),
                'special_days' => array(
                    'total' => 0, 'active' => 0, 'public' => 0, 'private' => 0,
                    'archived' => 0, 'trashed' => 0, 'last_id' => 0,
                ),
                'attachments' => array('total' => 0, 'orphaned' => 0, 'last_id' => 0),
            ),
            'module_items' => array(),
            'plugin_items' => array(),
            'types' => array('total' => 0, 'active' => 0, 'items' => array()),
            'overrides' => array('templates' => array(), 'languages' => array()),
            'frontend_checks' => array(),
        );

        try {
            $query = $this->db->getQuery(true)
                ->select($this->db->quoteName('id'))
                ->from($this->db->quoteName('#__viewlevels'));
            $this->db->setQuery($query);
            $viewLevels = array_fill_keys(array_map('intval', (array) $this->db->loadColumn()), true);

            foreach (array(
                'component' => 'checkComponent',
                'permissions' => 'checkPermissionMigration',
                'extensions' => 'checkExtensions',
                'types' => 'checkTypes',
                'languages' => 'checkLanguages',
                'menus' => 'checkFrontendMenus',
                'images' => 'checkImageBounds',
                'content' => 'checkContent',
                'inventory' => 'checkOtherInventory',
                'special_days' => 'checkSpecialDaysInventory',
                'overrides' => 'checkOverrides',
                'frontend' => 'checkFrontendReadiness',
            ) as $area => $method) {
                try {
                    if ($area === 'menus' || $area === 'content') {
                        $this->{$method}($report, $viewLevels);
                    } else {
                        $this->{$method}($report);
                    }
                } catch (Throwable $error) {
                    $this->add(
                        $report,
                        'error',
                        Text::sprintf('COM_JEM_TOOLS_CHECK_AREA_FAILED', $area, $error->getMessage()),
                        Text::_('COM_JEM_TOOLS_RECOMMEND_SCHEMA_VERSION'),
                        'index.php?option=com_installer&view=database',
                        'COM_JEM_TOOLS_OPEN_DATABASE',
                        'JEM',
                        'COM_JEM_TOOLS_CHECK_AREA_FAILED'
                    );
                }
            }
        } catch (Throwable $error) {
            $this->add($report, 'error', Text::sprintf('COM_JEM_TOOLS_CHECK_FAILED', $error->getMessage()));
        }

        return $report;
    }

    /**
     * Report the JEM migration state and the Joomla asset-tree prerequisite without changing either.
     */
    private function checkPermissionMigration(array &$report): void
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('value'))
            ->from($this->db->quoteName('#__jem_config'))
            ->where($this->db->quoteName('keyname') . ' = ' . $this->db->quote('acl_migration_state'));
        $this->db->setQuery($query);
        $state = $this->db->loadResult();

        if ($state !== null) {
            $migration = json_decode((string) $state, true);
            if (!is_array($migration) || empty($migration['complete'])) {
                $reason = is_array($migration) ? trim((string) ($migration['error'] ?? '')) : '';
                $this->add(
                    $report,
                    'error',
                    Text::sprintf('COM_JEM_TOOLS_ACL_MIGRATION_PENDING', $reason !== '' ? $reason : Text::_('COM_JEM_TOOLS_ACL_MIGRATION_NO_REASON')),
                    Text::_('COM_JEM_TOOLS_RECOMMEND_ACL_MIGRATION'),
                    'index.php?option=com_jem',
                    'COM_JEM_TOOLS_OPEN_CONTROL_PANEL',
                    'JEM',
                    'COM_JEM_TOOLS_ACL_MIGRATION_PENDING'
                );
            }
        }

        require_once __DIR__ . '/aclassetmigration.class.php';
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(array('id', 'parent_id', 'lft', 'rgt', 'level', 'name')))
            ->from($this->db->quoteName('#__assets'));
        $this->db->setQuery($query);
        $assets = (array) $this->db->loadAssocList('id');

        try {
            JemAclAssetMigration::validateTree($assets);
        } catch (RuntimeException $error) {
            $this->add(
                $report,
                'error',
                Text::sprintf('COM_JEM_TOOLS_ACL_TREE_INCONSISTENT', $error->getMessage()),
                Text::_('COM_JEM_TOOLS_RECOMMEND_ACL_TREE'),
                '',
                '',
                'Joomla',
                'COM_JEM_TOOLS_ACL_TREE_INCONSISTENT'
            );
        }

        if (!in_array('com_jem', array_column($assets, 'name'), true)) {
            $this->add(
                $report,
                'error',
                Text::_('COM_JEM_TOOLS_ACL_COMPONENT_ASSET_MISSING'),
                Text::_('COM_JEM_TOOLS_RECOMMEND_ACL_TREE'),
                '',
                '',
                'JEM',
                'COM_JEM_TOOLS_ACL_COMPONENT_ASSET_MISSING'
            );
        }
    }

    private function checkComponent(array &$report): void
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(array('extension_id', 'enabled', 'manifest_cache')))
            ->from($this->db->quoteName('#__extensions'))
            ->where($this->db->quoteName('type') . ' = ' . $this->db->quote('component'))
            ->where($this->db->quoteName('element') . ' = ' . $this->db->quote('com_jem'));
        $this->db->setQuery($query);
        $component = $this->db->loadObject();

        if (!$component) {
            $this->add(
                $report,
                'error',
                Text::_('COM_JEM_TOOLS_COMPONENT_MISSING'),
                Text::_('COM_JEM_TOOLS_RECOMMEND_COMPONENT'),
                'index.php?option=com_installer&view=manage',
                'COM_JEM_TOOLS_OPEN_EXTENSIONS',
                'JEM',
                'COM_JEM_TOOLS_COMPONENT_MISSING'
            );
            return;
        }

        $manifest = json_decode((string) $component->manifest_cache, true);
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('version_id'))
            ->from($this->db->quoteName('#__schemas'))
            ->where($this->db->quoteName('extension_id') . ' = ' . (int) $component->extension_id);
        $this->db->setQuery($query);
        $schemaVersion = (string) $this->db->loadResult();
        $versionDate = '';
        $creationDate = is_array($manifest) ? trim((string) ($manifest['creationDate'] ?? '')) : '';
        if ($creationDate !== '') {
            try {
                $versionDate = (new \DateTimeImmutable($creationDate))->format('Y-m-d');
            } catch (\Throwable $error) {
                // An invalid manifest date must not interrupt the diagnostic audit.
            }
        }

        $timezone = (string) Factory::getApplication()->get('offset', 'UTC');
        try {
            $siteTimezone = new \DateTimeZone($timezone);
        } catch (\Throwable $error) {
            $timezone = 'UTC';
            $siteTimezone = new \DateTimeZone('UTC');
        }

        $installation = $this->findComponentInstallation((int) $component->extension_id);
        $installedAt = '';
        if ($installation['date'] !== '') {
            try {
                $installedAt = (new \DateTimeImmutable($installation['date'], new \DateTimeZone('UTC')))
                    ->setTimezone($siteTimezone)->format('Y-m-d H:i');
            } catch (\Throwable $error) {
                // Keep an unparseable action-log date unavailable rather than guessing.
            }
        }
        $report['component'] = array(
            'id' => (int) $component->extension_id,
            'version' => is_array($manifest) ? (string) ($manifest['version'] ?? '') : '',
            'schema_version' => $schemaVersion,
            'version_date' => $versionDate,
            'installed_at' => $installedAt,
            'installed_action' => $installation['action'],
            'timezone' => $timezone,
            'active' => (int) $component->enabled === 1,
        );

        if ($schemaVersion === '') {
            $this->add(
                $report,
                'warning',
                Text::_('COM_JEM_TOOLS_SCHEMA_VERSION_MISSING'),
                Text::_('COM_JEM_TOOLS_RECOMMEND_SCHEMA_VERSION'),
                'index.php?option=com_installer&view=database',
                'COM_JEM_TOOLS_OPEN_DATABASE',
                'JEM',
                'COM_JEM_TOOLS_SCHEMA_VERSION_MISSING'
            );
        }

        if (!$report['component']['active']) {
            $this->add(
                $report,
                'warning',
                Text::_('COM_JEM_TOOLS_COMPONENT_DISABLED'),
                Text::_('COM_JEM_TOOLS_RECOMMEND_COMPONENT'),
                'index.php?option=com_installer&view=manage',
                'COM_JEM_TOOLS_OPEN_EXTENSIONS',
                'JEM',
                'COM_JEM_TOOLS_COMPONENT_DISABLED'
            );
        }
    }

    private function checkOverrides(array &$report): void
    {
        $report['overrides'] = (new JemHousekeepingOverrides($this->db))->scan();
    }

    /** Joomla only has an installation date when its optional action log recorded it. */
    private function findComponentInstallation(int $extensionId): array
    {
        $result = array('date' => '', 'action' => '');

        try {
            $query = $this->db->getQuery(true)
                ->select($this->db->quoteName(array('log_date', 'message')))
                ->from($this->db->quoteName('#__action_logs'))
                ->where($this->db->quoteName('message_language_key') . ' LIKE '
                    . $this->db->quote('PLG_ACTIONLOG_JOOMLA_%'))
                ->where('(' . $this->db->quoteName('message_language_key') . ' LIKE '
                    . $this->db->quote('%_INSTALLED') . ' OR '
                    . $this->db->quoteName('message_language_key') . ' LIKE '
                    . $this->db->quote('%_UPDATED') . ')')
                ->where($this->db->quoteName('message') . ' LIKE ' . $this->db->quote('%com_jem%'))
                ->order($this->db->quoteName('log_date') . ' DESC');
            $this->db->setQuery($query, 0, 200);

            foreach ((array) $this->db->loadObjectList() as $entry) {
                $message = json_decode((string) $entry->message, true);
                if (!is_array($message) || (int) ($message['id'] ?? 0) !== $extensionId
                    || !in_array($message['action'] ?? '', array('install', 'update'), true)) {
                    continue;
                }

                $result = array('date' => (string) $entry->log_date, 'action' => $message['action']);
                break;
            }
        } catch (\Throwable $error) {
            // Action logging may be unavailable; the rest of the audit remains read-only.
        }

        return $result;
    }

    private function checkLanguages(array &$report): void
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(array('element', 'manifest_cache')))
            ->from($this->db->quoteName('#__extensions'))
            ->where($this->db->quoteName('type') . ' = ' . $this->db->quote('file'))
            ->where($this->db->quoteName('element') . ' LIKE ' . $this->db->quote('com_jem_%'));
        $this->db->setQuery($query);
        $jemPacks = array();

        foreach ((array) $this->db->loadObjectList() as $extension) {
            if (preg_match('/^com_jem_([a-z]{2,3}-[A-Z]{2})$/', (string) $extension->element, $matches) !== 1) {
                continue;
            }

            $jemPacks[$matches[1]] = JemLanguageCatalogHelper::getInstalledPackageMetadata(
                (string) $extension->manifest_cache
            );
        }

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(array('lang_id', 'title', 'lang_code', 'published')))
            ->from($this->db->quoteName('#__languages'))
            ->order($this->db->quoteName('lang_id') . ' ASC');
        $this->db->setQuery($query);

        foreach ((array) $this->db->loadObjectList() as $language) {
            $active = (int) $language->published === 1;
            $code = (string) $language->lang_code;
            $jemInstalled = $code === 'en-GB'
                ? $report['component']['id'] > 0
                : isset($jemPacks[$code]);
            $jemVersion = $code === 'en-GB'
                ? $report['component']['version']
                : (string) ($jemPacks[$code]['version'] ?? '');
            $report['languages']['total']++;
            if ($active) {
                $report['languages']['active']++;
            }
            $report['languages']['items'][] = array(
                'id' => (int) $language->lang_id,
                'name' => (string) $language->title,
                'code' => $code,
                'active' => $active,
                'jem_installed' => $jemInstalled,
                'jem_version' => $jemVersion,
            );
        }
    }

    private function checkExtensions(array &$report): void
    {
        $modules = array(
            'mod_jem', 'mod_jem_banner', 'mod_jem_cal', 'mod_jem_jubilee',
            'mod_jem_map', 'mod_jem_teaser', 'mod_jem_types', 'mod_jem_wide',
        );
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(array('id', 'title', 'module', 'published')))
            ->from($this->db->quoteName('#__modules'))
            ->where($this->db->quoteName('module') . ' IN (' . implode(',', array_map(array($this->db, 'quote'), $modules)) . ')')
            ->where($this->db->quoteName('client_id') . ' = 0')
            ->where($this->db->quoteName('published') . ' != -2')
            ->order($this->db->quoteName('id') . ' ASC');
        $this->db->setQuery($query);

        foreach ((array) $this->db->loadObjectList() as $module) {
            $report['modules']['total']++;
            if ((int) $module->published === 1) {
                $report['modules']['active']++;
            }
            $report['module_items'][] = array(
                'name' => (string) $module->title,
                'module' => (string) $module->module,
                'id' => (int) $module->id,
                'active' => (int) $module->published === 1,
            );
        }

        $plugins = array(
            array('actionlog', 'jem'), array('content', 'jemembed'),
            array('content', 'jemlistevents'), array('finder', 'jem'),
            array('jem', 'comments'), array('jem', 'mailer'),
            array('quickicon', 'jem'), array('task', 'jem'), array('user', 'jem'),
        );
        $conditions = array();
        foreach ($plugins as $plugin) {
            $conditions[] = '(' . $this->db->quoteName('folder') . ' = ' . $this->db->quote($plugin[0])
                . ' AND ' . $this->db->quoteName('element') . ' = ' . $this->db->quote($plugin[1]) . ')';
        }
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(array('extension_id', 'name', 'folder', 'element', 'enabled')))
            ->from($this->db->quoteName('#__extensions'))
            ->where($this->db->quoteName('type') . ' = ' . $this->db->quote('plugin'))
            ->where('(' . implode(' OR ', $conditions) . ')')
            ->order($this->db->quoteName('extension_id') . ' ASC');
        $this->db->setQuery($query);

        foreach ((array) $this->db->loadObjectList() as $plugin) {
            $report['plugins']['total']++;
            if ((int) $plugin->enabled === 1) {
                $report['plugins']['active']++;
            }
            $report['plugin_items'][] = array(
                'name' => Text::_((string) $plugin->name),
                'module' => (string) $plugin->folder . '/' . (string) $plugin->element,
                'id' => (int) $plugin->extension_id,
                'active' => (int) $plugin->enabled === 1,
            );
        }
    }

    /** Count assignments for each type's own entity, including unpublished records. */
    private function checkTypes(array &$report): void
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(array('id', 'name', 'entity', 'published')))
            ->from($this->db->quoteName('#__jem_types'))
            ->order($this->db->quoteName('id') . ' ASC');
        $this->db->setQuery($query);
        $types = (array) $this->db->loadObjectList();

        $assignments = array();
        foreach (array(
            1 => array('#__jem_events', 'type_id'),
            2 => array('#__jem_categories', 'type_id'),
            3 => array('#__jem_venues', 'type_id'),
            4 => array('#__jem_special_days', 'day_type_id'),
        ) as $entity => $source) {
            $query = $this->db->getQuery(true)
                ->select($this->db->quoteName($source[1]) . ', COUNT(*) AS ' . $this->db->quoteName('usage_count'))
                ->from($this->db->quoteName($source[0]))
                ->where($this->db->quoteName($source[1]) . ' > 0')
                ->group($this->db->quoteName($source[1]));
            $this->db->setQuery($query);
            foreach ((array) $this->db->loadObjectList() as $item) {
                $assignments[$entity][(int) $item->{$source[1]}] = (int) $item->usage_count;
            }
        }

        foreach ($types as $type) {
            $entity = (int) $type->entity;
            $active = (int) $type->published === 1;
            $report['types']['total']++;
            if ($active) {
                $report['types']['active']++;
            }
            $report['types']['items'][] = array(
                'id' => (int) $type->id,
                'name' => (string) $type->name,
                'entity' => $entity,
                'active' => $active,
                'count' => (int) ($assignments[$entity][(int) $type->id] ?? 0),
            );
        }
    }

    private function checkImageBounds(array &$report): void
    {
        $settings = JemHelper::config();
        $minimum = (int) ($settings->image_min_dimension ?? 0);
        $maximum = (int) ($settings->image_max_dimension ?? 0);

        if ($minimum <= 0 || $maximum <= 0 || $minimum > $maximum) {
            $this->add(
                $report,
                'error',
                Text::_('COM_JEM_TOOLS_IMAGE_BOUNDS_INVALID'),
                Text::_('COM_JEM_TOOLS_RECOMMEND_IMAGE_BOUNDS'),
                'index.php?option=com_jem&view=settings',
                'COM_JEM_TOOLS_OPEN_SETTINGS'
            );
        }
    }

    /**
     * Count JEM frontend views referenced by non-trashed site menu items.
     */
    private function checkFrontendMenus(array &$report, array $viewLevels): void
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(array('id', 'title', 'link', 'published', 'access', 'params')))
            ->from($this->db->quoteName('#__menu'))
            ->where($this->db->quoteName('client_id') . ' = 0')
            ->where($this->db->quoteName('published') . ' != -2')
            ->where($this->db->quoteName('link') . ' LIKE ' . $this->db->quote('%option=com_jem%'))
            ->order($this->db->quoteName('id') . ' ASC');
        $this->db->setQuery($query);

        $referenceIds = array();
        foreach (array(
            'categoryswitchcats' => '#__jem_categories',
            'timeline_filter_venues' => '#__jem_venues',
            'timeline_filter_types' => '#__jem_types',
        ) as $field => $table) {
            $idsQuery = $this->db->getQuery(true)
                ->select($this->db->quoteName('id'))
                ->from($this->db->quoteName($table));
            $this->db->setQuery($idsQuery);
            $referenceIds[$field] = array_fill_keys(array_map('intval', (array) $this->db->loadColumn()), true);
        }

        $this->db->setQuery($query);

        foreach ((array) $this->db->loadObjectList() as $menu) {
            $parts = parse_url((string) $menu->link) ?: array();
            $params = array();
            parse_str((string) ($parts['query'] ?? ''), $params);
            $view = (string) ($params['view'] ?? '');
            if (($params['option'] ?? '') !== 'com_jem') {
                continue;
            }

            $menuId = (int) $menu->id;
            $menuUrl = 'index.php?option=com_menus&task=item.edit&id=' . $menuId;
            $subject = Text::sprintf('COM_JEM_TOOLS_SUBJECT_MENU', $menuId);
            if (!isset($viewLevels[(int) $menu->access])) {
                $this->add(
                    $report,
                    'error',
                    Text::sprintf('COM_JEM_TOOLS_INVALID_ACCESS', $menu->title, (int) $menu->access),
                    Text::_('COM_JEM_TOOLS_RECOMMEND_MENU_SETTINGS'),
                    $menuUrl,
                    'COM_JEM_TOOLS_OPEN_MENU',
                    $subject,
                    'COM_JEM_TOOLS_INVALID_ACCESS'
                );
            }

            $menuParams = (string) $menu->params;
            $decodedParams = $menuParams === '' ? array() : json_decode($menuParams, true);
            if (!is_array($decodedParams)) {
                $this->add(
                    $report,
                    'error',
                    Text::sprintf('COM_JEM_TOOLS_INVALID_PARAMS', $menu->title),
                    Text::_('COM_JEM_TOOLS_RECOMMEND_MENU_SETTINGS'),
                    $menuUrl,
                    'COM_JEM_TOOLS_OPEN_MENU',
                    $subject,
                    'COM_JEM_TOOLS_INVALID_PARAMS'
                );
            } else {
                foreach ($referenceIds as $field => $validIds) {
                    foreach ($this->normaliseIds($decodedParams[$field] ?? array()) as $referencedId) {
                        if (isset($validIds[$referencedId])) {
                            continue;
                        }
                        $this->add(
                            $report,
                            'warning',
                            Text::sprintf('COM_JEM_TOOLS_MENU_BROKEN_FILTER', $menu->title, $menuId, $field, $referencedId),
                            Text::_('COM_JEM_TOOLS_RECOMMEND_MENU_FILTERS'),
                            $menuUrl,
                            'COM_JEM_TOOLS_OPEN_MENU',
                            $subject,
                            'COM_JEM_TOOLS_MENU_BROKEN_FILTER'
                        );
                    }
                }
            }

            if (!preg_match('/^[a-z0-9_]+$/iD', $view)) {
                $this->add(
                    $report,
                    'warning',
                    Text::sprintf('COM_JEM_TOOLS_MENU_INVALID_VIEW', $menu->title),
                    Text::_('COM_JEM_TOOLS_RECOMMEND_MENU_SETTINGS'),
                    $menuUrl,
                    'COM_JEM_TOOLS_OPEN_MENU',
                    $subject,
                    'COM_JEM_TOOLS_MENU_INVALID_VIEW'
                );
                continue;
            }

            if (!isset($report['menu_views']['items'][$view])) {
                $report['menu_views']['items'][$view] = array(
                    'name' => $view,
                    'total' => 0,
                    'active' => 0,
                    'menu_items' => array(),
                );
            }
            $report['menu_views']['total']++;
            $report['menu_views']['items'][$view]['total']++;
            $isPublished = (int) $menu->published === 1;
            $configuredLayout = $params['layout'] ?? (is_array($decodedParams) ? ($decodedParams['layout'] ?? 'default') : 'default');
            $layout = is_scalar($configuredLayout) ? (string) $configuredLayout : '(invalid)';
            $modeField = $view === 'day' ? 'day_display_mode' : (
                in_array($view, array('calendar', 'weekcal'), true)
                || ($layout === 'calendar' && in_array($view, array('category', 'venue'), true))
                    ? 'calendar_display_mode' : ''
            );
            $configuredMode = $modeField !== '' && is_array($decodedParams)
                ? ($decodedParams[$modeField] ?? ($view === 'day' ? 'list' : 'calendar')) : '';
            $mode = is_scalar($configuredMode) ? (string) $configuredMode : '(invalid)';
            $report['menu_views']['items'][$view]['menu_items'][] = array(
                'id' => $menuId,
                'title' => (string) $menu->title,
                'published' => $isPublished,
                'layout' => $layout,
                'mode' => $mode,
            );
            if ($isPublished) {
                $report['menu_views']['active']++;
                $report['menu_views']['items'][$view]['active']++;
            }
        }

        uasort($report['menu_views']['items'], static function (array $first, array $second): int {
            $firstId = (int) ($first['menu_items'][0]['id'] ?? 0);
            $secondId = (int) ($second['menu_items'][0]['id'] ?? 0);

            return $firstId <=> $secondId;
        });
    }

    private function normaliseIds($value): array
    {
        $values = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_unique(array_filter(array_map('intval', $values))));
    }

    /**
     * Inspect every stored event, category, venue and event-category relation.
     */
    private function checkContent(array &$report, array $viewLevels): void
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__users'));
        $this->db->setQuery($query);
        $users = array_fill_keys(array_map('intval', (array) $this->db->loadColumn()), true);

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(array('id', 'catname', 'parent_id', 'published', 'access', 'created_user_id')))
            ->from($this->db->quoteName('#__jem_categories'));
        $this->db->setQuery($query);
        $categories = (array) $this->db->loadObjectList('id');

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(array('id', 'venue', 'parent_venue_id', 'published', 'access', 'created_by')))
            ->from($this->db->quoteName('#__jem_venues'));
        $this->db->setQuery($query);
        $venues = (array) $this->db->loadObjectList('id');

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(array(
                'id', 'title', 'published', 'access', 'locid', 'dates', 'enddates',
                'publish_up', 'publish_down', 'created_by', 'type_id', 'featured',
            )))
            ->from($this->db->quoteName('#__jem_events'));
        $this->db->setQuery($query);
        $events = (array) $this->db->loadObjectList('id');

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(array('id', 'itemid', 'catid')))
            ->from($this->db->quoteName('#__jem_cats_event_relations'));
        $this->db->setQuery($query);
        $relations = (array) $this->db->loadObjectList();
        $assignments = array();
        $guestLevels = array_map('intval', Access::getAuthorisedViewLevels(0));

        foreach ($relations as $relation) {
            $eventId = (int) $relation->itemid;
            $categoryId = (int) $relation->catid;
            if (!isset($events[$eventId]) || !isset($categories[$categoryId])) {
                $this->add(
                    $report,
                    'error',
                    Text::sprintf('COM_JEM_TOOLS_BROKEN_RELATION', (int) $relation->id, $eventId, $categoryId),
                    Text::_('COM_JEM_TOOLS_RECOMMEND_RELATION'),
                    'index.php?option=com_jem&view=housekeeping',
                    'COM_JEM_TOOLS_OPEN_HOUSEKEEPING',
                    Text::sprintf('COM_JEM_TOOLS_SUBJECT_RELATION', (int) $relation->id),
                    'COM_JEM_TOOLS_BROKEN_RELATION'
                );
                continue;
            }

            $assignments[$eventId][] = $categoryId;
        }

        foreach ($categories as $category) {
            $this->countItem($report, 'categories', (int) $category->published, (int) $category->id, (int) $category->access, $guestLevels);
            $id = (int) $category->id;
            $url = 'index.php?option=com_jem&view=category&layout=edit&id=' . $id;
            $subject = Text::sprintf('COM_JEM_TOOLS_SUBJECT_CATEGORY', $id);
            $parentId = (int) $category->parent_id;
            if ($parentId > 0 && !isset($categories[$parentId])) {
                $this->addFinding($report, 'error', 'COM_JEM_TOOLS_CATEGORY_PARENT_MISSING', $category->catname, $id, $url, 'COM_JEM_TOOLS_OPEN_CATEGORY', $subject);
            } elseif ($parentId > 0 && (int) $category->published === 1 && (int) $categories[$parentId]->published !== 1) {
                $this->addFinding($report, 'warning', 'COM_JEM_TOOLS_CATEGORY_PARENT_HIDDEN', $category->catname, $id, $url, 'COM_JEM_TOOLS_OPEN_CATEGORY', $subject);
            }
            $this->checkCommonReferences(
                $report,
                $category->catname,
                $id,
                (int) $category->access,
                (int) $category->created_user_id,
                $viewLevels,
                $users,
                $url,
                'COM_JEM_TOOLS_OPEN_CATEGORY',
                $subject
            );
        }

        foreach ($venues as $venue) {
            $this->countItem($report, 'venues', (int) $venue->published, (int) $venue->id, (int) $venue->access, $guestLevels);
            $id = (int) $venue->id;
            $url = 'index.php?option=com_jem&view=venue&layout=edit&id=' . $id;
            $subject = Text::sprintf('COM_JEM_TOOLS_SUBJECT_VENUE', $id);
            $parentId = (int) $venue->parent_venue_id;
            if ($parentId > 0 && !isset($venues[$parentId])) {
                $this->addFinding($report, 'error', 'COM_JEM_TOOLS_VENUE_PARENT_MISSING', $venue->venue, $id, $url, 'COM_JEM_TOOLS_OPEN_VENUE', $subject);
            } elseif ($parentId > 0 && (int) $venue->published === 1 && (int) $venues[$parentId]->published !== 1) {
                $this->addFinding($report, 'warning', 'COM_JEM_TOOLS_VENUE_PARENT_HIDDEN', $venue->venue, $id, $url, 'COM_JEM_TOOLS_OPEN_VENUE', $subject);
            }
            $this->checkCommonReferences(
                $report,
                $venue->venue,
                $id,
                (int) $venue->access,
                (int) $venue->created_by,
                $viewLevels,
                $users,
                $url,
                'COM_JEM_TOOLS_OPEN_VENUE',
                $subject
            );
        }

        foreach ($events as $event) {
            $this->countItem($report, 'events', (int) $event->published, (int) $event->id, (int) $event->access, $guestLevels);
            $id = (int) $event->id;
            $url = 'index.php?option=com_jem&view=event&layout=edit&id=' . $id;
            $subject = Text::sprintf('COM_JEM_TOOLS_SUBJECT_EVENT', $id);
            $this->checkCommonReferences(
                $report,
                $event->title,
                $id,
                (int) $event->access,
                (int) $event->created_by,
                $viewLevels,
                $users,
                $url,
                'COM_JEM_TOOLS_OPEN_EVENT',
                $subject
            );

            $eventCategories = $assignments[$id] ?? array();
            if ((int) $event->published === 1 && !$eventCategories) {
                $this->addFinding($report, 'warning', 'COM_JEM_TOOLS_PUBLISHED_EVENT_NO_CATEGORY', $event->title, $id, $url, 'COM_JEM_TOOLS_OPEN_EVENT', $subject);
            } elseif ((int) $event->published === 1 && !array_filter($eventCategories, static function ($categoryId) use ($categories) {
                return (int) $categories[$categoryId]->published === 1;
            })) {
                $this->addFinding($report, 'warning', 'COM_JEM_TOOLS_PUBLISHED_EVENT_HIDDEN_CATEGORIES', $event->title, $id, $url, 'COM_JEM_TOOLS_OPEN_EVENT', $subject);
            }

            $venueId = (int) $event->locid;
            if ($venueId > 0 && !isset($venues[$venueId])) {
                $this->addFinding($report, 'error', 'COM_JEM_TOOLS_EVENT_VENUE_MISSING', $event->title, $id, $url, 'COM_JEM_TOOLS_OPEN_EVENT', $subject);
            } elseif ($venueId > 0 && (int) $event->published === 1 && (int) $venues[$venueId]->published !== 1) {
                $this->addFinding($report, 'warning', 'COM_JEM_TOOLS_EVENT_VENUE_UNPUBLISHED', $event->title, $id, $url, 'COM_JEM_TOOLS_OPEN_EVENT', $subject);
            }

            if ($event->dates && $event->enddates && $event->enddates < $event->dates) {
                $this->addFinding($report, 'error', 'COM_JEM_TOOLS_EVENT_DATES_REVERSED', $event->title, $id, $url, 'COM_JEM_TOOLS_OPEN_EVENT', $subject);
            }
            if ($event->publish_up && $event->publish_down && $event->publish_down < $event->publish_up) {
                $this->addFinding($report, 'error', 'COM_JEM_TOOLS_EVENT_PUBLICATION_REVERSED', $event->title, $id, $url, 'COM_JEM_TOOLS_OPEN_EVENT', $subject);
            }
        }

        $this->frontendSource = compact('events', 'categories', 'venues', 'assignments', 'guestLevels');
        $this->checkAttachmentReferences($report, $events, $categories, $venues);
    }

    /** Find stored attachments whose referenced JEM record is no longer present. */
    private function checkAttachmentReferences(array &$report, array $events, array $categories, array $venues): void
    {
        $recordsByType = array('event' => $events, 'venue' => $venues, 'category' => $categories);
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(array('id', 'object')))
            ->from($this->db->quoteName('#__jem_attachments'));
        $this->db->setQuery($query);

        foreach ((array) $this->db->loadObjectList() as $attachment) {
            $object = (string) $attachment->object;
            $matches = array();
            $known = preg_match('/^(event|venue|category)([0-9]+)$/iD', $object, $matches);
            $records = $known ? $recordsByType[strtolower($matches[1])] : null;
            if ($known && isset($records[(int) $matches[2]])) {
                continue;
            }

            $id = (int) $attachment->id;
            $report['inventory']['attachments']['orphaned']++;
            $this->add(
                $report,
                'error',
                Text::sprintf('COM_JEM_TOOLS_ATTACHMENT_ORPHANED', $id, $object),
                Text::_('COM_JEM_TOOLS_RECOMMEND_ATTACHMENT_ORPHANED'),
                'index.php?option=com_jem&task=attachment.edit&id=' . $id,
                'COM_JEM_TOOLS_OPEN_ATTACHMENT',
                Text::sprintf('COM_JEM_TOOLS_SUBJECT_ATTACHMENT', $id),
                'COM_JEM_TOOLS_ATTACHMENT_ORPHANED'
            );
        }
    }

    private function countItem(array &$report, string $kind, int $published, int $id, int $access, array $guestLevels): void
    {
        $report['inventory'][$kind]['total']++;
        $report['inventory'][$kind]['last_id'] = max($report['inventory'][$kind]['last_id'], $id);
        if ($published === 1) {
            $report['inventory'][$kind]['active']++;
            $report['inventory'][$kind][in_array($access, $guestLevels, true) ? 'public' : 'private']++;
        } elseif ($published === 2) {
            $report['inventory'][$kind]['archived']++;
        } elseif ($published === -2) {
            $report['inventory'][$kind]['trashed']++;
        }
    }

    /**
     * A small, additive battery of guest-visibility checks. Each result is
     * independent evidence, not a promise that every menu will show an event.
     */
    private function checkFrontendReadiness(array &$report): void
    {
        $source = $this->frontendSource;
        if (!$source) {
            foreach (array(
                'component_schema', 'menu', 'published', 'publication_window', 'event_dates',
                'event_access', 'category_access', 'venue_access', 'type_access', 'filters_overrides',
            ) as $code) {
                $this->frontendCheck($report, $code, 'skip', Text::_('COM_JEM_TOOLS_FE_SOURCE_UNAVAILABLE'),
                    'index.php?option=com_installer&view=database', false);
            }
            return;
        }

        $events = $source['events'];
        $categories = $source['categories'];
        $venues = $source['venues'];
        $assignments = $source['assignments'];
        $guestLevels = $source['guestLevels'];
        $settings = class_exists('JemHelper') ? JemHelper::config() : null;
        $levelsFor = static function (string $kind) use ($guestLevels, $settings): array {
            $levels = $guestLevels;
            $locked = $settings->{'access_level_locked_' . $kind} ?? '["1"]';
            if ($locked !== '["1"]') {
                $extra = json_decode((string) $locked, true);
                if (is_array($extra)) {
                    $levels = array_values(array_unique(array_merge($levels, array_map('intval', $extra))));
                }
            }
            return $levels;
        };
        $eventLevels = $levelsFor('events');
        $categoryLevels = $levelsFor('categories');
        $venueLevels = $levelsFor('venues');
        $now = Factory::getDate()->toSql();
        $today = (new \DateTimeImmutable('now', new \DateTimeZone($report['component']['timezone'] ?: 'UTC')))
            ->format('Y-m-d');
        $published = array_filter($events, static fn($event): bool => (int) $event->published === 1);
        $inWindow = array_filter($published, static function ($event) use ($now): bool {
            $up = (string) $event->publish_up;
            $down = (string) $event->publish_down;
            return ($up === '' || str_starts_with($up, '0000-00-00') || $up <= $now)
                && ($down === '' || str_starts_with($down, '0000-00-00') || $down > $now);
        });
        $guestEvents = array_filter($inWindow, static fn($event): bool => in_array((int) $event->access, $eventLevels, true));
        $dated = array_filter($inWindow, static function ($event) use ($today): bool {
            $lastDate = (string) ($event->enddates ?: $event->dates);
            return $lastDate !== '' && $lastDate >= $today;
        });

        $schemaVersion = (string) $report['component']['schema_version'];
        $schemaFiles = glob(dirname(__DIR__) . '/sql/updates/mysql/*.sql') ?: array();
        $expectedSchema = '';
        foreach ($schemaFiles as $schemaFile) {
            $version = basename($schemaFile, '.sql');
            if (preg_match('/^\d+\.\d+\.\d+$/D', $version)
                && ($expectedSchema === '' || version_compare($version, $expectedSchema, '>'))) {
                $expectedSchema = $version;
            }
        }
        $schemaReady = $schemaVersion !== '' && ($expectedSchema === '' || version_compare($schemaVersion, $expectedSchema, '>='));
        $componentReady = (bool) $report['component']['active'] && $schemaReady;
        $this->frontendCheck($report, 'component_schema', $componentReady ? 'ok' : 'error',
            Text::sprintf('COM_JEM_TOOLS_FE_COMPONENT_DETAIL', $schemaVersion ?: '-', $expectedSchema ?: '-'),
            'index.php?option=com_installer&view=database', true);

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(array('id', 'parent_id', 'published', 'access', 'link', 'params')))
            ->from($this->db->quoteName('#__menu'))
            ->where($this->db->quoteName('client_id') . ' = 0')
            ->where($this->db->quoteName('published') . ' != -2');
        $this->db->setQuery($query);
        $menus = (array) $this->db->loadObjectList('id');
        $listViews = array_flip(array(
            'annualcalendar', 'calendar', 'categories', 'category', 'day', 'event', 'eventsblog', 'eventslist',
            'eventsmap', 'search', 'typeevents', 'venue', 'venues', 'venueslist', 'venuesmap', 'weekcal',
        ));
        $visibleMenus = array();
        foreach ($menus as $menu) {
            $parts = parse_url((string) $menu->link) ?: array();
            $params = array();
            parse_str((string) ($parts['query'] ?? ''), $params);
            if (($params['option'] ?? '') !== 'com_jem' || !isset($listViews[$params['view'] ?? ''])) {
                continue;
            }
            $current = $menu;
            $seen = array();
            while ($current) {
                $id = (int) $current->id;
                if (isset($seen[$id]) || (int) $current->published !== 1
                    || !in_array((int) $current->access, $guestLevels, true)) {
                    continue 2;
                }
                $seen[$id] = true;
                $parentId = (int) $current->parent_id;
                if ($parentId <= 1) {
                    $visibleMenus[] = $menu;
                    break;
                }
                $current = $menus[$parentId] ?? null;
            }
        }
        $this->frontendCheck($report, 'menu', $visibleMenus ? 'ok' : 'warning',
            Text::sprintf('COM_JEM_TOOLS_FE_MENU_DETAIL', count($visibleMenus)),
            'index.php?option=com_menus&view=items', true);

        $this->frontendCheck($report, 'published', $published ? 'ok' : 'warning',
            $this->withEventSamples(
                Text::sprintf('COM_JEM_TOOLS_FE_PUBLISHED_DETAIL', count($published), count($events)),
                array_diff_key($events, $published),
                !$published
            ),
            'index.php?option=com_jem&view=events', true);
        $this->frontendCheck($report, 'publication_window', !$published ? 'skip' : ($inWindow ? 'ok' : 'warning'),
            $this->withEventSamples(
                Text::sprintf('COM_JEM_TOOLS_FE_WINDOW_DETAIL', count($inWindow), count($published)),
                array_diff_key($published, $inWindow),
                !$inWindow
            ),
            'index.php?option=com_jem&view=events', true);
        $this->frontendCheck($report, 'event_dates', !$inWindow ? 'skip' : ($dated ? 'ok' : 'warning'),
            $this->withEventSamples(
                Text::sprintf('COM_JEM_TOOLS_FE_DATES_DETAIL', count($dated), count($inWindow)),
                array_diff_key($inWindow, $dated),
                !$dated
            ),
            'index.php?option=com_jem&view=events', true);
        $this->frontendCheck($report, 'event_access', !$inWindow ? 'skip' : ($guestEvents ? 'ok' : 'warning'),
            $this->withEventSamples(
                Text::sprintf('COM_JEM_TOOLS_FE_ACCESS_DETAIL', count($guestEvents), count($inWindow)),
                array_diff_key($inWindow, $guestEvents),
                !$guestEvents
            ),
            'index.php?option=com_jem&view=events', true);

        $withCategories = array_filter($guestEvents, static function ($event) use ($assignments, $categories, $categoryLevels): bool {
            foreach ($assignments[(int) $event->id] ?? array() as $categoryId) {
                $category = $categories[$categoryId] ?? null;
                if ($category && (int) $category->published === 1
                    && in_array((int) $category->access, $categoryLevels, true)) {
                    return true;
                }
            }
            return false;
        });
        $this->frontendCheck($report, 'category_access', !$guestEvents ? 'skip' : ($withCategories ? 'ok' : 'warning'),
            $this->withEventSamples(
                Text::sprintf('COM_JEM_TOOLS_FE_CATEGORIES_DETAIL', count($withCategories), count($guestEvents)),
                array_diff_key($guestEvents, $withCategories),
                !$withCategories
            ),
            'index.php?option=com_jem&view=categories', true);

        $visibleVenue = static function (int $venueId) use ($venues, $venueLevels): bool {
            $seen = array();
            while ($venueId > 0) {
                if (isset($seen[$venueId]) || !isset($venues[$venueId])) {
                    return false;
                }
                $seen[$venueId] = true;
                $venue = $venues[$venueId];
                if ((int) $venue->published !== 1 || !in_array((int) $venue->access, $venueLevels, true)) {
                    return false;
                }
                $venueId = (int) $venue->parent_venue_id;
            }
            return true;
        };
        $withVenues = array_filter($withCategories, static fn($event): bool => $visibleVenue((int) $event->locid));
        $this->frontendCheck($report, 'venue_access', !$withCategories ? 'skip' : ($withVenues ? 'ok' : 'warning'),
            $this->withEventSamples(
                Text::sprintf('COM_JEM_TOOLS_FE_VENUES_DETAIL', count($withVenues), count($withCategories)),
                array_diff_key($withCategories, $withVenues),
                !$withVenues
            ),
            'index.php?option=com_jem&view=venues', true);

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(array('id', 'entity', 'published', 'access')))
            ->from($this->db->quoteName('#__jem_types'));
        $this->db->setQuery($query);
        $types = (array) $this->db->loadObjectList('id');
        $withTypes = array_filter($withVenues, static function ($event) use ($types, $guestLevels): bool {
            $typeId = (int) $event->type_id;
            if ($typeId < 1) {
                return true;
            }
            $type = $types[$typeId] ?? null;
            return $type && (int) $type->entity === 1 && (int) $type->published === 1
                && in_array((int) $type->access, $guestLevels, true);
        });
        $this->frontendCheck($report, 'type_access', !$withVenues ? 'skip' : ($withTypes ? 'ok' : 'warning'),
            $this->withEventSamples(
                Text::sprintf('COM_JEM_TOOLS_FE_TYPES_DETAIL', count($withTypes), count($withVenues)),
                array_diff_key($withVenues, $withTypes),
                !$withTypes
            ),
            'index.php?option=com_jem&view=types', true);

        $filteredMenus = 0;
        foreach ($visibleMenus as $menu) {
            $params = json_decode((string) $menu->params, true);
            if (!is_array($params)) {
                continue;
            }
            foreach (array('onlyfeatured', 'categoryswitchcats', 'timeline_filter_venues',
                'timeline_filter_types', 'timeline_filter_countries', 'tablefiltereventfrom',
                'tablefiltereventuntil') as $field) {
                if (!empty($params[$field])) {
                    $filteredMenus++;
                    break;
                }
            }
        }
        $overrideCount = count($report['overrides']['templates'] ?? array())
            + count($report['overrides']['languages'] ?? array())
            + count($report['overrides']['css'] ?? array());
        $brokenFilters = count(array_filter(
            $report['lines'],
            static fn($line): bool => $line['code'] === 'COM_JEM_TOOLS_MENU_BROKEN_FILTER'
        ));
        $contextStatus = $brokenFilters ? 'warning' : (($filteredMenus || $overrideCount) ? 'review' : 'ok');
        $this->frontendCheck($report, 'filters_overrides', $contextStatus,
            Text::sprintf('COM_JEM_TOOLS_FE_CONTEXT_DETAIL', $brokenFilters, $filteredMenus, $overrideCount),
            'index.php?option=com_menus&view=items', false);
    }

    private function frontendCheck(
        array &$report,
        string $code,
        string $status,
        string $detail,
        string $url,
        bool $createFinding
    ): void {
        $label = Text::_('COM_JEM_TOOLS_FE_CHECK_' . strtoupper($code));
        $report['frontend_checks'][] = array(
            'code' => $code,
            'label' => $label,
            'status' => $status,
            'detail' => $detail,
            'action_url' => $url,
        );
        if ($createFinding && in_array($status, array('warning', 'error'), true)) {
            $this->add(
                $report,
                $status,
                $label . ': ' . $detail,
                Text::_('COM_JEM_TOOLS_FE_RECOMMENDATION'),
                $url,
                'COM_JEM_TOOLS_FE_OPEN_SETTINGS',
                'Frontend visibility',
                'COM_JEM_TOOLS_FE_' . strtoupper($code)
            );
        }
    }

    private function withEventSamples(string $detail, array $events, bool $show): string
    {
        if (!$show || !$events) {
            return $detail;
        }
        $ids = array_slice(array_keys($events), 0, 5);
        $labels = array_map(static fn($id): string => '#' . (int) $id, $ids);

        return $detail . ' ' . Text::sprintf('COM_JEM_TOOLS_FE_EXAMPLE_EVENTS', implode(', ', $labels));
    }

    /**
     * Count type records and stored attachments without loading their contents.
     */
    private function checkOtherInventory(array &$report): void
    {
        foreach (array('types', 'attachments') as $kind) {
            $query = $this->db->getQuery(true)
                ->select('COUNT(*) AS ' . $this->db->quoteName('total'))
                ->select('COALESCE(MAX(' . $this->db->quoteName('id') . '), 0) AS ' . $this->db->quoteName('last_id'))
                ->from($this->db->quoteName('#__jem_' . $kind));

            if ($kind === 'types') {
                $query->select(
                    'COALESCE(SUM(' . $this->db->quoteName('published') . ' = 1), 0) AS '
                    . $this->db->quoteName('active')
                )
                    ->select(
                        'COALESCE(SUM(' . $this->db->quoteName('published') . ' = 2), 0) AS '
                        . $this->db->quoteName('archived')
                    )
                    ->select(
                        'COALESCE(SUM(' . $this->db->quoteName('published') . ' = -2), 0) AS '
                        . $this->db->quoteName('trashed')
                    );
            }

            $this->db->setQuery($query);
            $counts = (array) $this->db->loadAssoc();
            $report['inventory'][$kind]['total'] = (int) ($counts['total'] ?? 0);
            $report['inventory'][$kind]['last_id'] = (int) ($counts['last_id'] ?? 0);

            if ($kind === 'types') {
                $report['inventory'][$kind]['active'] = (int) ($counts['active'] ?? 0);
                $report['inventory'][$kind]['archived'] = (int) ($counts['archived'] ?? 0);
                $report['inventory'][$kind]['trashed'] = (int) ($counts['trashed'] ?? 0);
            }
        }
    }

    private function checkSpecialDaysInventory(array &$report): void
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(array('id', 'published', 'access')))
            ->from($this->db->quoteName('#__jem_special_days'));
        $this->db->setQuery($query);
        $guestLevels = array_map('intval', Access::getAuthorisedViewLevels(0));

        foreach ((array) $this->db->loadObjectList() as $day) {
            $this->countItem(
                $report,
                'special_days',
                (int) $day->published,
                (int) $day->id,
                (int) $day->access,
                $guestLevels
            );
        }
    }

    private function checkCommonReferences(
        array &$report,
        string $name,
        int $id,
        int $access,
        int $author,
        array $viewLevels,
        array $users,
        string $url,
        string $actionLabel,
        string $subject
    ): void {
        if (!isset($viewLevels[$access])) {
            $this->addFinding($report, 'error', 'COM_JEM_TOOLS_RECORD_INVALID_ACCESS', $name, $id, $url, $actionLabel, $subject);
        }
        if ($author > 0 && !isset($users[$author])) {
            $this->addFinding($report, 'warning', 'COM_JEM_TOOLS_RECORD_AUTHOR_MISSING', $name, $id, $url, $actionLabel, $subject);
        }
    }

    private function addFinding(
        array &$report,
        string $severity,
        string $messageKey,
        string $name,
        int $id,
        string $url,
        string $actionLabel,
        string $subject
    ): void {
        $this->add(
            $report,
            $severity,
            Text::sprintf($messageKey, $name, $id),
            Text::_('COM_JEM_TOOLS_RECOMMEND_REVIEW_RECORD'),
            $url,
            $actionLabel,
            $subject,
            $messageKey
        );
    }

    private function add(
        array &$report,
        string $severity,
        string $message,
        string $recommendation = '',
        string $actionUrl = '',
        string $actionLabel = '',
        string $subject = 'JEM',
        string $code = ''
    ): void {
        $report[$severity]++;
        $report['lines'][] = array(
            'severity' => $severity,
            'message' => $message,
            'recommendation' => $recommendation,
            'action_url' => $actionUrl,
            'action_label' => $actionLabel,
            'subject' => $subject,
            'code' => $code,
        );
    }
}
