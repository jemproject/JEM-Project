<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

require_once JPATH_SITE . '/components/com_jem/classes/log.class.php';

$logPath = Factory::getApplication()->get('log_path', JPATH_ADMINISTRATOR . '/logs');
$logLevels = array(
    0 => Text::_('COM_JEM_LOGLEVEL_OFF'),
    1 => Text::_('COM_JEM_LOGLEVEL_ERROR'),
    2 => Text::_('COM_JEM_LOGLEVEL_WARNING'),
    3 => Text::_('COM_JEM_LOGLEVEL_INFO'),
    4 => Text::_('COM_JEM_LOGLEVEL_DEBUG'),
    5 => Text::_('COM_JEM_LOGLEVEL_ALL'),
);
$currentLogLevel = JemLog::getConfiguredLevel();
$logFiles = array(
    array(
        'key' => JemLog::CHANNEL_COMPONENT,
        'file' => JemLog::getLogFiles()[JemLog::CHANNEL_COMPONENT],
        'source' => Text::_('COM_JEM_CONFIGINFO_LOG_SOURCE_COMPONENT'),
        'condition' => Text::_('COM_JEM_CONFIGINFO_LOG_CONDITION_LOGLEVEL'),
    ),
    array(
        'key' => JemLog::CHANNEL_MODULES,
        'file' => JemLog::getLogFiles()[JemLog::CHANNEL_MODULES],
        'source' => Text::_('COM_JEM_CONFIGINFO_LOG_SOURCE_MODULES'),
        'condition' => Text::_('COM_JEM_CONFIGINFO_LOG_CONDITION_LOGLEVEL'),
    ),
    array(
        'key' => JemLog::CHANNEL_PLUGINS,
        'file' => JemLog::getLogFiles()[JemLog::CHANNEL_PLUGINS],
        'source' => Text::_('COM_JEM_CONFIGINFO_LOG_SOURCE_PLUGINS'),
        'condition' => Text::_('COM_JEM_CONFIGINFO_LOG_CONDITION_LOGLEVEL'),
    ),
);

?>
<div class="width-100" style="padding: 10px 1vw;">
    <div class="width-100" style="padding: 10px 1vw;">
        <fieldset class="options-form">
            <legend><?php echo Text::_('COM_JEM_SETTINGS_LEGEND_CONFIGINFO'); ?></legend>
            <br>
            <table class="adminlist table">
                <?php
                $known_extensions = array('pkg_jem'           => 'COM_JEM_MAIN_CONFIG_VS_PACKAGE'
                                         ,'com_jem'           => 'COM_JEM_MAIN_CONFIG_VS_COMPONENT'
                                         ,'mod_jem'           => 'COM_JEM_MAIN_CONFIG_VS_MOD_JEM'
                                         ,'mod_jem_cal'       => 'COM_JEM_MAIN_CONFIG_VS_MOD_JEM_CAL'
                                         ,'mod_jem_banner'    => 'COM_JEM_MAIN_CONFIG_VS_MOD_JEM_BANNER'
                                         ,'mod_jem_jubilee'   => 'COM_JEM_MAIN_CONFIG_VS_MOD_JEM_JUBILEE'
                                         ,'mod_jem_map'       => 'COM_JEM_MAIN_CONFIG_VS_MOD_JEM_MAP'
                                         ,'mod_jem_teaser'    => 'COM_JEM_MAIN_CONFIG_VS_MOD_JEM_TEASER'
                                         ,'mod_jem_types'     => 'COM_JEM_MAIN_CONFIG_VS_MOD_JEM_TYPES'
                                         ,'mod_jem_wide'      => 'COM_JEM_MAIN_CONFIG_VS_MOD_JEM_WIDE'
                                         ,'plg_content_jemlistevents' => 'COM_JEM_MAIN_CONFIG_VS_PLG_CONTENT_LISTEVENTS'
                                         ,'plg_finder_jem'    => 'COM_JEM_MAIN_CONFIG_VS_PLG_FINDER'
                                         ,'plg_jem_comments'  => 'COM_JEM_MAIN_CONFIG_VS_PLG_COMMENTS'
                                         ,'plg_jem_mailer'    => 'COM_JEM_MAIN_CONFIG_VS_PLG_MAILER'
                                         ,'plg_jem_demo'      => 'COM_JEM_MAIN_CONFIG_VS_PLG_DEMO'
                                         ,'plg_quickicon_jemquickicon' => 'COM_JEM_MAIN_CONFIG_VS_PLG_QUICKICON'
                                         ,'AcyMailing Tag : insert events from JEM 2.1+'
                                                              => 'COM_JEM_MAIN_CONFIG_VS_PLG_ACYMAILING_TAGJEM'
                                         ,'files_acym_jem'     => 'COM_JEM_MAIN_CONFIG_VS_ACYMAILING_JEM'
                                         );
                ?>
                <tr>
                    <th><u><?php echo Text::_('COM_JEM_NAME'); ?></u></th>
                    <th><u><?php echo Text::_('COM_JEM_MAIN_CONFIG_VS'); ?></u></th>
                    <th><u><?php echo Text::_('COM_JEM_DATE'); ?></u></th>
                    <th><u><?php echo Text::_('JSTATUS'); ?></u></th>
                </tr>
                <?php
                foreach ($known_extensions as $name => $label) {
                    if (!empty($this->config->$name)) { ?>
                    <tr>
                        <td><?php echo Text::_($label).': '; ?></td>
                        <td><b><?php echo $this->config->$name->version; ?></b></td>
                        <td><?php echo $this->config->$name->creationDate; ?></td>
                        <td><?php echo empty($this->config->$name->enabled) ? Text::_('COM_JEM_DISABLED') : Text::_('COM_JEM_ENABLED'); ?></td>
                    </tr>
                    <?php
                    }
                }
                ?>
                    <tr>
                        <td><?php echo Text::_('COM_JEM_MAIN_CONFIG_VS_PHP').': '; ?></td>
                        <td colspan="3"><b><?php echo $this->config->vs_php; ?> </b></td>
                    </tr>
                    <?php if (!empty($this->config->vs_php_magicquotes)) : ?>
                    <tr>
                        <td><?php echo Text::_('COM_JEM_MAIN_CONFIG_VS_PHP_MAGICQUOTES').': '; ?></td>
                        <td colspan="3"><b><?php echo $this->config->vs_php_magicquotes; ?> </b></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td><?php echo Text::_('COM_JEM_MAIN_CONFIG_VS_GD').': '; ?></td>
                        <td colspan="3"><b><?php echo $this->config->vs_gd; ?> </b></td>
                    </tr>
                </table>
        </fieldset>
        <fieldset class="options-form">
            <legend><?php echo Text::_('COM_JEM_CONFIGINFO_LOG_FILES'); ?></legend>
            <p><?php echo Text::_('COM_JEM_CONFIGINFO_LOG_LOCATION'); ?></p>
            <p><?php echo Text::sprintf('COM_JEM_CONFIGINFO_LOG_CURRENT_LEVEL', htmlspecialchars($logLevels[$currentLogLevel] ?? $currentLogLevel, ENT_QUOTES, 'UTF-8')); ?></p>
            <table class="adminlist table">
                <tr>
                    <th><u><?php echo Text::_('COM_JEM_CONFIGINFO_LOG_FILE'); ?></u></th>
                    <th><u><?php echo Text::_('COM_JEM_CONFIGINFO_LOG_SOURCE'); ?></u></th>
                    <th><u><?php echo Text::_('COM_JEM_CONFIGINFO_LOG_CONDITION'); ?></u></th>
                    <th><u><?php echo Text::_('COM_JEM_CONFIGINFO_LOG_ACTION'); ?></u></th>
                </tr>
                <?php foreach ($logFiles as $logFile) : ?>
                    <?php
                    $logFilePath = $logPath . '/' . $logFile['file'];
                    $logExists = is_file($logFilePath) && is_readable($logFilePath);
                    $viewUrl = Route::_('index.php?option=com_jem&task=settings.viewLog&log=' . $logFile['key'] . '&' . Session::getFormToken() . '=1', false);
                    $downloadUrl = Route::_('index.php?option=com_jem&task=settings.downloadLog&log=' . $logFile['key'] . '&' . Session::getFormToken() . '=1', false);
                    $modalId = 'jem-log-modal-' . preg_replace('/[^a-z0-9_-]/i', '-', $logFile['key']);
                    ?>
                    <tr>
                        <td><code><?php echo htmlspecialchars($logFile['file'], ENT_QUOTES, 'UTF-8'); ?></code></td>
                        <td><?php echo $logFile['source']; ?></td>
                        <td><?php echo $logFile['condition']; ?></td>
                        <td>
                            <?php if ($logExists) : ?>
                                <?php
                                echo HTMLHelper::_(
                                    'bootstrap.renderModal',
                                    $modalId,
                                    array(
                                        'url'    => $viewUrl,
                                        'title'  => htmlspecialchars($logFile['file'], ENT_QUOTES, 'UTF-8'),
                                        'width'  => '100%',
                                        'height' => '600px',
                                        'footer' => '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">' . Text::_('COM_JEM_CLOSE') . '</button>'
                                    )
                                );
                                ?>
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#<?php echo $modalId; ?>">
                                    <?php echo Text::_('COM_JEM_CONFIGINFO_LOG_VIEW'); ?>
                                </button>
                                <a class="btn btn-sm btn-outline-primary" href="<?php echo $downloadUrl; ?>">
                                    <span class="icon-download" aria-hidden="true"></span>
                                    <?php echo Text::_('COM_JEM_CONFIGINFO_LOG_DOWNLOAD'); ?>
                                </a>
                            <?php else : ?>
                                <span class="text-muted"><?php echo Text::_('COM_JEM_CONFIGINFO_LOG_NOT_CREATED'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </fieldset>
        <?php if (!empty($this->config->libraries)) : ?>
        <fieldset class="options-form">
            <legend><?php echo Text::_('COM_JEM_CONFIGINFO_LIBRARIES'); ?></legend>
            <p><?php echo Text::_('COM_JEM_CONFIGINFO_LIBRARIES_DESC'); ?></p>
            <table class="adminlist table">
                <tr>
                    <th><u><?php echo Text::_('COM_JEM_CONFIGINFO_LIBRARY_NAME'); ?></u></th>
                    <th><u><?php echo Text::_('COM_JEM_CONFIGINFO_LIBRARY_VERSION'); ?></u></th>
                    <th><u><?php echo Text::_('COM_JEM_CONFIGINFO_LIBRARY_LICENSE'); ?></u></th>
                    <th><u><?php echo Text::_('COM_JEM_CONFIGINFO_LIBRARY_SCOPE'); ?></u></th>
                    <th><u><?php echo Text::_('COM_JEM_CONFIGINFO_LIBRARY_LOCATION'); ?></u></th>
                    <th><u><?php echo Text::_('COM_JEM_CONFIGINFO_LIBRARY_NOTES'); ?></u></th>
                </tr>
                <?php foreach ($this->config->libraries as $library) : ?>
                    <?php
                    $scopeKey = !empty($library['scope']) && $library['scope'] === 'runtime'
                        ? 'COM_JEM_CONFIGINFO_LIBRARY_SCOPE_RUNTIME'
                        : 'COM_JEM_CONFIGINFO_LIBRARY_SCOPE_BUNDLED';
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($library['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><b><?php echo htmlspecialchars($library['version'] ?? '?', ENT_QUOTES, 'UTF-8'); ?></b></td>
                        <td><?php echo htmlspecialchars($library['license'] ?? '?', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo Text::_($scopeKey); ?></td>
                        <td><code><?php echo htmlspecialchars($library['path'] ?? '', ENT_QUOTES, 'UTF-8'); ?></code></td>
                        <td><?php echo htmlspecialchars($library['notes'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </fieldset>
        <?php endif; ?>
    </div>
</div>

<div class="width-50 fltrt">

</div>

<div class="clr"></div>
