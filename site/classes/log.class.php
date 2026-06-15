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
     *
     * @return void
     */
    public static function add($message, $where = null, $priority = Log::DEBUG, $code = null, array $context = array())
    {
        self::addFileLogger();

        $source = self::resolveSource($where);
        $channel = self::detectChannel($source['file']);
        $category = self::getCategory($channel);
        $code = $code ?: self::getDefaultCode($priority);

        $context = array_merge(self::getRequestContext(), $context);
        $sourceText = self::formatSource($source);
        $contextText = self::formatContext($context);
        $entryMessage = $code . ' | ' . trim((string) $message) . $sourceText . $contextText;

        $entry = new LogEntry($entryMessage, $priority, $category);
        $entry->where = $source['function'];

        Log::add($entry);
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
