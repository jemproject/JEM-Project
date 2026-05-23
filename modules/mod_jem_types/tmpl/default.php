<?php
/**
 * @package    JEM
 * @subpackage mod_jem_types
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$mode = $params->get('display_mode', 'summary');
?>

<div class="mod-jem-types<?php echo $params->get('moduleclass_sfx', ''); ?>">

<?php if ($mode === 'summary') : ?>

    <?php if (empty($data)) : ?>
        <p class="mod-jem-types__empty"><?php echo Text::_('MOD_JEM_TYPES_NO_TYPES'); ?></p>
    <?php else : ?>
        <ul class="mod-jem-types__list list-unstyled">
            <?php foreach ($data as $type) : ?>
                <?php
                $link  = Route::_(JemHelperRoute::getTypeeventsRoute($type->id));
                $name  = htmlspecialchars($type->name, ENT_QUOTES, 'UTF-8');
                $tooltip = JemOutput::typeDescriptionSummary(isset($type->description) ? $type->description : '');
                $title = $tooltip !== '' ? ' title="' . htmlspecialchars($tooltip, ENT_QUOTES, 'UTF-8') . '"' : '';
                $style = '';
                if (!empty($type->color) && preg_match('/^#[0-9a-fA-F]{6}$/', (string) $type->color)) {
                    $style = ' style="border-left: 4px solid ' . htmlspecialchars($type->color, ENT_QUOTES, 'UTF-8') . ';"';
                }
                ?>
                <li class="mod-jem-types__item"<?php echo $style; ?>>
                    <a href="<?php echo htmlspecialchars($link, ENT_QUOTES, 'UTF-8'); ?>" class="mod-jem-types__link"<?php echo $title; ?>>
                        <?php if (!empty($type->icon)) : ?>
                            <span class="<?php echo htmlspecialchars($type->icon, ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></span>
                        <?php endif; ?>
                        <?php echo $name; ?>
                    </a>
                    <span class="mod-jem-types__count badge bg-secondary ms-1"><?php echo (int) $type->event_count; ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

<?php elseif ($mode === 'topn') : ?>

    <?php if (empty($data)) : ?>
        <p class="mod-jem-types__empty"><?php echo Text::_('MOD_JEM_TYPES_NO_TYPES'); ?></p>
    <?php else : ?>
        <?php foreach ($data as $type) : ?>
            <?php
            $typeLink = Route::_(JemHelperRoute::getTypeeventsRoute($type->id));
            $typeName = htmlspecialchars($type->name, ENT_QUOTES, 'UTF-8');
            $tooltip = JemOutput::typeDescriptionSummary(isset($type->description) ? $type->description : '');
            $title = $tooltip !== '' ? ' title="' . htmlspecialchars($tooltip, ENT_QUOTES, 'UTF-8') . '"' : '';
            ?>
            <div class="mod-jem-types__group">
                <h5 class="mod-jem-types__group-title">
                    <a href="<?php echo htmlspecialchars($typeLink, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $title; ?>>
                        <?php if (!empty($type->icon)) : ?>
                            <span class="<?php echo htmlspecialchars($type->icon, ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></span>
                        <?php endif; ?>
                        <?php echo $typeName; ?>
                    </a>
                </h5>

                <?php if (empty($type->events)) : ?>
                    <p class="mod-jem-types__no-events"><?php echo Text::_('MOD_JEM_TYPES_NO_EVENTS'); ?></p>
                <?php else : ?>
                    <ul class="mod-jem-types__events list-unstyled">
                        <?php foreach ($type->events as $event) : ?>
                            <li class="mod-jem-types__event">
                                <a href="<?php echo htmlspecialchars(Route::_(JemHelperRoute::getEventRoute($event->slug)), ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars($event->title, ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                                <small class="mod-jem-types__date text-muted ms-1">
                                    <?php echo JemOutput::formatShortDateTime($event->dates, $event->times, $event->enddates, $event->endtimes, 0); ?>
                                </small>
                                <?php $moreInformationDisplay = JemHelper::getMoreInformationDisplay($params->get('show_more_information', 'link')); ?>
                                <?php if ($moreInformationDisplay !== '' && !empty($event->articlelink)) : ?>
                                    <a id="<?php echo JemHelper::getModuleActionId('mod-jem-types', 'more-information', $event->id, $module->id ?? 0); ?>"
                                       href="<?php echo htmlspecialchars($event->articlelink, ENT_QUOTES, 'UTF-8'); ?>"
                                       class="<?php echo JemHelper::getMoreInformationClass($moreInformationDisplay, 'jem-more-information-link mod-jem-types__more-information'); ?>">
                                        <?php echo Text::_('MOD_JEM_TYPES_MORE_INFORMATION'); ?><?php echo ((int)$params->get('show_more_information_title', 0) && !empty($event->articletitle)) ? ': ' . $event->articletitle : ''; ?>
                                    </a>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <a href="<?php echo htmlspecialchars($typeLink, ENT_QUOTES, 'UTF-8'); ?>" class="mod-jem-types__more">
                        <?php echo Text::_('MOD_JEM_TYPES_MORE'); ?>
                    </a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

<?php endif; ?>

</div>
