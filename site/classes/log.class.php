<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Log\LogEntry;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

/**
 * Central JEM logging helper.
 *
 * The class keeps one format for component, module and plugin logs while
 * routing entries to separate log files.
 */
class JemLog
{
    const CHANNEL_COMPONENT = 'component';
    const CHANNEL_MODULES   = 'modules';
    const CHANNEL_PLUGINS   = 'plugins';

    protected static $loggerAdded = false;
    protected static $messageLoggerRegistered = false;
    protected static $loggedMessages = array();
    protected static $eventslistTraceId = null;
    protected static $eventslistTraceStep = 0;
    protected static $eventslistModuleContext = null;

    /**
     * Configure Joomla file loggers for all JEM channels.
     *
     * @return void
     */
    public static function addFileLogger()
    {
        if (self::$loggerAdded) {
            return;
        }

        self::$loggerAdded = true;
        $loglevel = self::getJoomlaPriorityMask();

        if ($loglevel <= 0) {
            return;
        }

        $format = '{DATE} {TIME} | {PRIORITY} | {CATEGORY} | {WHERE} | {MESSAGE}';

        Log::addLogger(array('text_file' => 'jem.log.php', 'text_entry_format' => $format), $loglevel, array('JEM'));
        Log::addLogger(array('text_file' => 'jem-modules.log.php', 'text_entry_format' => $format), $loglevel, array('JEM_MODULES'));
        Log::addLogger(array('text_file' => 'jem-plugins.log.php', 'text_entry_format' => $format), $loglevel, array('JEM_PLUGINS'));

        self::registerScreenMessageLogger();
    }

    /**
     * Add a log entry.
     *
     * @param   string       $message   Message text.
     * @param   string|null  $where     Optional source hint.
     * @param   integer      $priority  Joomla log priority.
     * @param   string|null  $code      JEM message code.
     * @param   array        $context   Extra structured context.
     * @param   bool         $requestContext  Include URL, IP and user context.
     *
     * @return void
     */
    public static function add(
        $message,
        $where = null,
        $priority = Log::DEBUG,
        $code = null,
        array $context = array(),
        bool $requestContext = true
    ) {
        self::addFileLogger();

        $source = self::resolveSource($where);
        $channel = self::detectChannel($source['file']);
        $category = self::getCategory($channel);
        $code = $code ?: self::getDefaultCode($priority);

        $context = $requestContext ? array_merge(self::getRequestContext(), $context) : $context;
        $sourceText = self::formatSource($source);
        $contextText = self::formatContext($context);
        $entryMessage = $code . ' | ' . trim((string) $message) . $sourceText . $contextText;

        $entry = new LogEntry($entryMessage, $priority, $category);
        $entry->where = $source['function'];

        Log::add($entry);
    }

    /**
     * Trace the Events List pipeline without event contents, URL, IP or user data.
     * Event ID lists are limited to twenty positive integers.
     */
    public static function traceEventslist(string $stage, array $metrics = array()): void
    {
        try {
            if (self::getConfiguredLevel() < 4) {
                return;
            }

            if (!self::isEventslistTraceRequest()) {
                return;
            }

            $context = array_merge(
                self::nextEventslistTraceContext(),
                self::normaliseTraceMetrics($metrics, self::getConfiguredLevel() === 5)
            );

            $stage = preg_replace('/[^a-z0-9._-]/i', '', $stage);
            if ($stage !== '') {
                self::add('Eventslist trace: ' . $stage, null, Log::DEBUG, 'JEM-D-0101', $context, false);
            }
        } catch (\Throwable) {
            // A diagnostic trace must never prevent the frontend from rendering.
        }
    }

    /** Mark model queries made by a JEM module, including its effective layout. */
    public static function beginEventslistModuleTrace($module, $params): void
    {
        self::$eventslistModuleContext = null;

        try {
            if (self::getConfiguredLevel() < 4) {
                return;
            }

            $type = (string) ($module->module ?? '');
            $layout = (string) $params->get('layout', 'default');
            if (!preg_match('/^mod_jem(?:_[a-z]+)?$/', $type)
                || !preg_match('/^[a-z0-9:_-]{1,80}$/i', $layout)) {
                return;
            }

            $layoutPath = \Joomla\CMS\Helper\ModuleHelper::getLayoutPath($type, $layout);
            self::$eventslistModuleContext = array(
                'consumer' => 'module',
                'module_id' => (int) ($module->id ?? 0),
                'module_type' => $type,
                'layout' => $layout,
                'override_used' => strpos(str_replace('\\', '/', $layoutPath), str_replace('\\', '/', JPATH_THEMES) . '/') === 0,
            );
        } catch (\Throwable) {
            self::$eventslistModuleContext = null;
        }
    }

    public static function endEventslistModuleTrace(): void
    {
        self::$eventslistModuleContext = null;
    }

    /** Record query shape only; SQL literals and numeric values are redacted. */
    public static function traceEventslistSql(string $sql): void
    {
        try {
            if (self::getConfiguredLevel() !== 5 || !self::isEventslistTraceRequest()) {
                return;
            }

            $safeSql = self::redactEventslistSql($sql);
            self::add(
                'Eventslist trace: query.sql',
                null,
                Log::DEBUG,
                'JEM-D-0102',
                array_merge(self::nextEventslistTraceContext(), array(
                    'sql_shape' => substr($safeSql, 0, 12000),
                    'sql_truncated' => strlen($safeSql) > 12000,
                )),
                false
            );
        } catch (\Throwable) {
            // Logging must not affect the frontend.
        }
    }

    /** Record a Joomla-caught database error without echoing private SQL values. */
    public static function traceEventslistSqlError(string $message): void
    {
        try {
            if (self::getConfiguredLevel() !== 5 || !self::isEventslistTraceRequest()) {
                return;
            }

            $context = self::nextEventslistTraceContext();
            if (preg_match('/Unknown column [\'\"]([a-z][a-z0-9_]*\.[a-z][a-z0-9_]*)[\'\"]/i', $message, $match)) {
                $context['missing_column'] = $match[1];
            }

            $context['sql_error'] = self::redactEventslistSqlError($message);
            self::add('Eventslist trace: query.failed', null, Log::ERROR, 'JEM-E-0102', $context, false);
        } catch (\Throwable) {
            // Logging must not affect the frontend.
        }
    }

    /** Identify the template selected by Joomla after rendering, not just a possible override. */
    public static function traceEventslistViewTemplate(string $layout, string $templatePath): void
    {
        try {
            if (self::getConfiguredLevel() !== 5 || !self::isEventslistTraceRequest()) {
                return;
            }

            $context = self::nextEventslistTraceContext();
            $context['layout'] = preg_match('/^[a-z0-9:_-]{1,80}$/i', $layout) ? $layout : '[custom]';
            $context['override_used'] = strpos(str_replace('\\', '/', $templatePath), str_replace('\\', '/', JPATH_THEMES) . '/') === 0;
            self::add('Eventslist trace: view.template', null, Log::DEBUG, 'JEM-D-0103', $context, false);
        } catch (\Throwable) {
            // Logging must not affect the frontend.
        }
    }

    private static function redactEventslistSql(string $sql): string
    {
        $sql = preg_replace('/\/\*.*?\*\/|--[^\r\n]*/s', ' ', $sql);
        $sql = preg_replace('/\'(?:\'\'|\\\\.|[^\'\\\\])*\'|\"(?:\"\"|\\\\.|[^\"\\\\])*\"/s', '?', $sql);
        $sql = preg_replace('/\b0x[0-9a-f]+\b/i', '?', $sql);
        $sql = preg_replace('/(?<![a-z0-9_])\d+(?:\.\d+)?(?![a-z0-9_])/i', '?', $sql);

        return (string) $sql;
    }

    private static function redactEventslistSqlError(string $message): string
    {
        $message = preg_replace('/\'(?:\'\'|\\\\.|[^\'\\\\])*\'|\"(?:\"\"|\\\\.|[^\"\\\\])*\"/s', '[redacted]', $message);

        return substr((string) $message, 0, 1000);
    }

    private static function isEventslistTraceRequest(): bool
    {
        $app = Factory::getApplication();
        if (!$app->isClient('site')) {
            return false;
        }

        if (self::$eventslistModuleContext !== null) {
            return true;
        }

        $view = $app->input->getCmd('view', '');
        if ($view === '') {
            $menu = $app->getMenu()->getActive();
            if ($menu) {
                parse_str((string) parse_url((string) $menu->link, PHP_URL_QUERY), $menuQuery);
                $view = is_string($menuQuery['view'] ?? null) ? $menuQuery['view'] : '';
            }
        }

        return $view === 'eventslist';
    }

    private static function nextEventslistTraceContext(): array
    {
        if (self::$eventslistTraceId === null) {
            self::$eventslistTraceId = bin2hex(random_bytes(6));
        }

        $menu = Factory::getApplication()->getMenu()->getActive();
        $context = array(
            'trace_id' => self::$eventslistTraceId,
            'step' => ++self::$eventslistTraceStep,
            'menu_id' => $menu ? (int) $menu->id : 0,
        );

        return array_merge($context, self::$eventslistModuleContext ?? array('consumer' => 'view'));
    }

    /** Reject strings, objects and unrecognised metric names before writing a trace. */
    private static function normaliseTraceMetrics(array $metrics, bool $includeEventIds = true): array
    {
        $safe = array();

        foreach ($metrics as $name => $value) {
            if (!is_string($name) || !preg_match('/^[a-z][a-z0-9_]*$/', $name)
                || in_array($name, array('trace_id', 'step', 'menu_id', 'consumer'), true)) {
                continue;
            }

            if ($includeEventIds
                && in_array($name, array(
                    'event_ids', 'without_category_ids', 'age_restricted_ids',
                    'category_filter_ids', 'venue_filter_ids', 'type_filter_ids',
                    'published_states', 'access_level_ids',
                ), true)
                && is_array($value)) {
                $safe[$name] = array_slice(array_values(array_filter($value, static function ($id) {
                    return is_int($id) && $id > 0;
                })), 0, 20);
                continue;
            }

            if ($name === 'event_tree' && in_array($value, array('all', 'calendar', 'children', 'parents'), true)) {
                $safe[$name] = $value;
                continue;
            }

            if (!is_int($value) && !is_bool($value)) {
                continue;
            }

            $safe[$name] = $value;
        }

        return $safe;
    }

    public static function info($code, $message, array $context = array())
    {
        self::add($message, null, Log::INFO, $code, $context);
    }

    public static function warning($code, $message, array $context = array())
    {
        self::add($message, null, Log::WARNING, $code, $context);
    }

    public static function error($code, $message, array $context = array())
    {
        self::add($message, null, Log::ERROR, $code, $context);
    }

    public static function exception($code, Exception $exception, array $context = array())
    {
        $context['exception_file'] = $exception->getFile();
        $context['exception_line'] = $exception->getLine();

        self::add($exception->getMessage(), null, Log::ERROR, $code, $context);
    }

    /**
     * Return the current JEM log level setting.
     *
     * @return integer
     */
    public static function getConfiguredLevel()
    {
        try {
            $config = JemConfig::getInstance()->toRegistry();
            $global = $config->get('globalattribs', null);

            if ($global instanceof Registry) {
                return (int) $global->get('loglevel', 2);
            }

            if (is_object($global)) {
                return isset($global->loglevel) ? (int) $global->loglevel : 2;
            }

            return (int) $config->get('globalattribs.loglevel', 2);
        } catch (Exception $e) {
            return 2;
        }
    }

    /**
     * Return configured JEM log files.
     *
     * @return array
     */
    public static function getLogFiles()
    {
        return array(
            self::CHANNEL_COMPONENT => 'jem.log.php',
            self::CHANNEL_MODULES   => 'jem-modules.log.php',
            self::CHANNEL_PLUGINS   => 'jem-plugins.log.php',
        );
    }

    /**
     * Convert JEM log level setting to Joomla priority mask.
     *
     * @return integer
     */
    protected static function getJoomlaPriorityMask()
    {
        switch (self::getConfiguredLevel()) {
            case 1:
                return Log::ERROR * 2 - 1;
            case 2:
                return Log::WARNING * 2 - 1;
            case 3:
                return Log::INFO * 2 - 1;
            case 4:
                return Log::DEBUG * 2 - 1;
            case 5:
                return Log::ALL;
            case 0:
            default:
                return 0;
        }
    }

    protected static function registerScreenMessageLogger()
    {
        if (self::$messageLoggerRegistered || self::getConfiguredLevel() < 3) {
            return;
        }

        self::$messageLoggerRegistered = true;

        register_shutdown_function(function () {
            try {
                $app = Factory::getApplication();

                if (!method_exists($app, 'getMessageQueue')) {
                    return;
                }

                foreach ((array) $app->getMessageQueue() as $message) {
                    $text = isset($message['message']) ? trim(strip_tags((string) $message['message'])) : '';

                    if ($text === '') {
                        continue;
                    }

                    $type = isset($message['type']) ? (string) $message['type'] : 'message';
                    $hash = md5($type . "\n" . $text);

                    if (isset(self::$loggedMessages[$hash])) {
                        continue;
                    }

                    self::$loggedMessages[$hash] = true;
                    self::add($text, 'screen-message', Log::INFO, 'JEM-I-0001', array('message_type' => $type));
                }
            } catch (Exception $e) {
                // Never let logging interfere with the response.
            }
        });
    }

    protected static function resolveSource($where = null)
    {
        $source = array(
            'file' => '',
            'line' => '',
            'function' => $where ?: '',
        );

        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 12) as $trace) {
            $file = isset($trace['file']) ? str_replace('\\', '/', $trace['file']) : '';

            if ($file === '' || strpos($file, '/components/com_jem/classes/log.class.php') !== false) {
                continue;
            }

            if (strpos($file, '/components/com_jem/helpers/helper.php') !== false && $where !== null) {
                continue;
            }

            $class = isset($trace['class']) ? $trace['class'] . '::' : '';
            $function = isset($trace['function']) ? $class . $trace['function'] : '';

            if ($function === 'JemModelEventslist::traceEventslist') {
                continue;
            }

            $source['file'] = $file;
            $source['line'] = isset($trace['line']) ? (int) $trace['line'] : '';
            $source['function'] = $where ?: $function;

            break;
        }

        return $source;
    }

    protected static function detectChannel($file)
    {
        $file = str_replace('\\', '/', (string) $file);

        if (strpos($file, '/modules/mod_jem') !== false) {
            return self::CHANNEL_MODULES;
        }

        if (strpos($file, '/plugins/') !== false) {
            return self::CHANNEL_PLUGINS;
        }

        return self::CHANNEL_COMPONENT;
    }

    protected static function getCategory($channel)
    {
        switch ($channel) {
            case self::CHANNEL_MODULES:
                return 'JEM_MODULES';
            case self::CHANNEL_PLUGINS:
                return 'JEM_PLUGINS';
            case self::CHANNEL_COMPONENT:
            default:
                return 'JEM';
        }
    }

    protected static function getDefaultCode($priority)
    {
        if ($priority <= Log::ERROR) {
            return 'JEM-E-0001';
        }

        if ($priority <= Log::WARNING) {
            return 'JEM-W-0001';
        }

        if ($priority <= Log::INFO) {
            return 'JEM-I-0001';
        }

        return 'JEM-D-0001';
    }

    protected static function formatSource(array $source)
    {
        $parts = array();

        if (!empty($source['file'])) {
            $parts[] = 'file=' . self::relativePath($source['file']);
        }

        if (!empty($source['function'])) {
            $parts[] = 'function=' . $source['function'];
        }

        if (!empty($source['line'])) {
            $parts[] = 'line=' . $source['line'];
        }

        return $parts ? ' | ' . implode(' | ', $parts) : '';
    }

    protected static function getRequestContext()
    {
        $context = array();

        try {
            $app = Factory::getApplication();
            $user = method_exists($app, 'getIdentity') ? $app->getIdentity() : Factory::getUser();
            $input = $app->input;

            $context['user_id'] = isset($user->id) ? (int) $user->id : 0;
            $context['option'] = $input->getCmd('option', '');
            $context['view'] = $input->getCmd('view', '');
            $context['task'] = $input->getCmd('task', '');
            $context['id'] = $input->getInt('id', 0);
            $context['ip'] = $input->server->getString('REMOTE_ADDR', '');

            if (!$app->isClient('cli')) {
                $context['url'] = Uri::getInstance()->toString(array('path', 'query'));
            }
        } catch (Exception $e) {
            // Request context is best effort only.
        }

        return array_filter($context, function ($value) {
            return $value !== '' && $value !== null;
        });
    }

    protected static function formatContext(array $context)
    {
        if (!$context) {
            return '';
        }

        return ' | context=' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    protected static function relativePath($path)
    {
        $path = str_replace('\\', '/', (string) $path);
        $root = str_replace('\\', '/', defined('JPATH_ROOT') ? JPATH_ROOT : '');

        if ($root && strpos($path, $root . '/') === 0) {
            return substr($path, strlen($root) + 1);
        }

        return $path;
    }
}
