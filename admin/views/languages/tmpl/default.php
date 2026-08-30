<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$stateLabels = array(
    'available' => array('success', 'COM_JEM_LANGUAGES_AVAILABLE'),
    'update' => array('warning', 'COM_JEM_LANGUAGES_UPDATE'),
    'installed' => array('success', 'COM_JEM_LANGUAGES_INSTALLED'),
    'built_in' => array('info', 'COM_JEM_LANGUAGES_BUILT_IN'),
    'joomla_required' => array('secondary', 'COM_JEM_LANGUAGES_JOOMLA_REQUIRED'),
    'incompatible' => array('secondary', 'COM_JEM_LANGUAGES_INCOMPATIBLE'),
    'not_available' => array('secondary', 'COM_JEM_LANGUAGES_NOT_AVAILABLE'),
);
?>

<div id="j-main-container" class="j-main-container jem-languages">
    <div class="alert alert-info" role="status">
        <?php echo Text::sprintf('COM_JEM_LANGUAGES_POLICY', htmlspecialchars($this->jemVersion, ENT_QUOTES, 'UTF-8'), htmlspecialchars($this->jemMajor, ENT_QUOTES, 'UTF-8')); ?>
    </div>

    <?php if (!empty($this->catalogStatus['is_local'])) : ?>
        <div class="alert alert-warning" role="status">
            <?php echo Text::sprintf(
                'COM_JEM_LANGUAGES_CATALOG_LOCAL',
                htmlspecialchars($this->catalogStatus['source'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($this->catalogStatus['version'] ?: Text::_('JNOTAVAILABLE'), ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($this->catalogStatus['published'] ?: Text::_('JNOTAVAILABLE'), ENT_QUOTES, 'UTF-8')
            ); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($this->catalogStatus['available'])) : ?>
        <div class="alert alert-warning" role="alert">
            <?php echo Text::_('COM_JEM_LANGUAGES_CATALOG_UNAVAILABLE'); ?>
        </div>
    <?php elseif (empty($this->catalogStatus['is_local'])) : ?>
        <p class="text-muted">
            <?php echo Text::sprintf(
                'COM_JEM_LANGUAGES_CATALOG_METADATA',
                htmlspecialchars($this->catalogStatus['published'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($this->catalogStatus['source'], ENT_QUOTES, 'UTF-8')
            ); ?>
        </p>
    <?php endif; ?>

    <?php if (!empty($this->catalogStatus['available'])) : ?>
    <div class="table-responsive">
        <table class="table table-striped align-middle">
            <caption class="visually-hidden"><?php echo Text::_('COM_JEM_LANGUAGES_TITLE'); ?></caption>
            <thead>
                <tr>
                    <th scope="col"><?php echo Text::_('COM_JEM_LANGUAGES_LANGUAGE'); ?></th>
                    <th scope="col"><?php echo Text::_('COM_JEM_LANGUAGES_JOOMLA_STATUS'); ?></th>
                    <th scope="col"><?php echo Text::_('COM_JEM_LANGUAGES_INSTALLED_VERSION'); ?></th>
                    <th scope="col"><?php echo Text::_('COM_JEM_LANGUAGES_COMPATIBILITY'); ?></th>
                    <th scope="col" class="text-end"><?php echo Text::_('COM_JEM_LANGUAGES_ACTION'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($this->items as $item) : ?>
                <?php
                $package = $item['package'];
                $displayPackage = $package ?: ($item['packages'][0] ?? null);
                $label = $stateLabels[$item['state']] ?? $stateLabels['not_available'];
                $nameContainsTag = preg_match(
                    '/\(' . preg_quote($item['tag'], '/') . '\)$/i',
                    trim((string) $item['name'])
                ) === 1;
                $majors = array_values(array_unique(array_filter(array_map(static function ($historyPackage) {
                    return JemLanguageCatalogHelper::getVersionMajor($historyPackage['jem']);
                }, $item['packages']))));
                sort($majors, SORT_NATURAL);
                ?>
                <tr>
                    <th scope="row">
                        <?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?>
                        <?php if (!$nameContainsTag) : ?>
                            <span class="text-muted fw-normal">(<?php echo htmlspecialchars($item['tag'], ENT_QUOTES, 'UTF-8'); ?>)</span>
                        <?php endif; ?>
                        <?php if ($displayPackage) : ?>
                            <div class="small text-muted fw-normal mt-1">
                                <?php echo htmlspecialchars($displayPackage['version'], ENT_QUOTES, 'UTF-8'); ?>
                                &middot; JEM <?php echo htmlspecialchars(JemLanguageCatalogHelper::getVersionMajor($displayPackage['jem']), ENT_QUOTES, 'UTF-8'); ?>.x
                                &middot; <?php echo htmlspecialchars($displayPackage['released'], ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php endif; ?>
                    </th>
                    <td>
                        <?php if ($item['joomla_installed']) : ?>
                            <span class="badge bg-success"><?php echo Text::_('COM_JEM_LANGUAGES_JOOMLA_INSTALLED'); ?></span>
                            <div class="small text-muted mt-1">
                                <?php
                                $clients = array();
                                if ($item['joomla_site']) {
                                    $clients[] = Text::_('COM_JEM_LANGUAGES_SITE');
                                }
                                if ($item['joomla_administrator']) {
                                    $clients[] = Text::_('COM_JEM_LANGUAGES_ADMINISTRATOR');
                                }
                                echo htmlspecialchars(implode(' / ', $clients), ENT_QUOTES, 'UTF-8');
                                ?>
                            </div>
                        <?php else : ?>
                            <span class="badge bg-secondary"><?php echo Text::_('COM_JEM_LANGUAGES_JOOMLA_NOT_INSTALLED'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $item['installed_version'] !== '' ? htmlspecialchars($item['installed_version'], ENT_QUOTES, 'UTF-8') : '&mdash;'; ?></td>
                    <td>
                        <?php if ($package) : ?>
                            <?php echo Text::sprintf(
                                'COM_JEM_LANGUAGES_COMPATIBLE_WITH',
                                htmlspecialchars(JemLanguageCatalogHelper::getVersionMajor($package['jem']), ENT_QUOTES, 'UTF-8')
                            ); ?>
                        <?php elseif ($majors) : ?>
                            <?php echo Text::sprintf('COM_JEM_LANGUAGES_ONLY_FOR', htmlspecialchars(implode('.x, ', $majors), ENT_QUOTES, 'UTF-8')); ?>
                        <?php else : ?>
                            &mdash;
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <?php if (in_array($item['state'], array('available', 'update'), true) && $package) : ?>
                            <form action="<?php echo Route::_('index.php?option=com_jem&task=languages.install'); ?>" method="post" class="d-inline">
                                <input type="hidden" name="package_id" value="<?php echo htmlspecialchars($package['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                <button type="submit" class="btn btn-sm btn-primary">
                                    <?php echo Text::_($item['state'] === 'update' ? 'COM_JEM_LANGUAGES_UPDATE_ACTION' : 'COM_JEM_LANGUAGES_INSTALL'); ?>
                                </button>
                                <?php echo HTMLHelper::_('form.token'); ?>
                            </form>
                        <?php else : ?>
                            <span class="badge bg-<?php echo $label[0]; ?>"><?php echo Text::_($label[1]); ?></span>
                            <?php if ($item['state'] === 'joomla_required') : ?>
                                <div class="small mt-1">
                                    <a href="<?php echo Route::_('index.php?option=com_installer&view=languages'); ?>">
                                        <?php echo Text::_('COM_JEM_LANGUAGES_INSTALL_JOOMLA_LANGUAGE'); ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
