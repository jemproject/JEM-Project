<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$columns = max(3, min(6, (int) $this->params->get('blog_columns', 4)));
$rowCount = count($this->rows);
$gridColumns = max(3, min($columns, $rowCount));
$imageFit = (string) $this->params->get('blog_image_fit', 'automatic');
$imageFit = in_array($imageFit, array('automatic', 'height', 'width'), true) ? $imageFit : 'automatic';
$imageRatioWidth = max(1, min(20, (int) $this->params->get('blog_image_ratio_width', 1)));
$imageRatioHeight = max(1, min(20, (int) $this->params->get('blog_image_ratio_height', 1)));
$showDateFilter = (int) $this->params->get('blog_show_date_filter', 1) === 1;
$showCategoryFilter = (int) $this->params->get('blog_show_category_filter', 1) === 1;
$showVenueFilter = (int) $this->params->get('blog_show_venue_filter', 1) === 1;
$showTypeFilter = (int) $this->params->get('blog_show_type_filter', 1) === 1;
$showCountryFilter = (int) $this->params->get('blog_show_country_filter', 1) === 1;
$showFilters = $showDateFilter || $showCategoryFilter || $showVenueFilter || $showTypeFilter || $showCountryFilter;
$periods = array(
    'all'       => 'COM_JEM_EVENTSBLOG_ALL',
    'today'     => 'COM_JEM_EVENTSBLOG_TODAY',
    'tomorrow'  => 'COM_JEM_EVENTSBLOG_TOMORROW',
    'week'      => 'COM_JEM_EVENTSBLOG_THIS_WEEK',
    'weekend'   => 'COM_JEM_EVENTSBLOG_WEEKEND',
    'next-week' => 'COM_JEM_EVENTSBLOG_NEXT_WEEK',
);
?>
<div id="jem" class="jem-eventsblog jem-eventsblog-image-fit--<?php echo $imageFit; ?><?php echo $this->pageclass_sfx; ?>" style="--jem-eventsblog-columns: <?php echo $gridColumns; ?>; --jem-eventsblog-image-ratio: <?php echo $imageRatioWidth; ?> / <?php echo $imageRatioHeight; ?>;">
    <?php if ($this->params->get('show_page_heading', 1)) : ?>
        <h1 class="componentheading"><?php echo $this->escape($this->params->get('page_heading')); ?></h1>
    <?php endif; ?>

    <?php if ($this->params->get('showintrotext')) : ?>
        <div class="jem-eventsblog-intro description no_space floattext"><?php echo $this->params->get('introtext'); ?></div>
    <?php endif; ?>

    <?php if ($showFilters) : ?>
    <form class="jem-eventsblog-filters" action="<?php echo htmlspecialchars($this->action, ENT_QUOTES, 'UTF-8'); ?>" method="get">
        <input type="hidden" name="option" value="com_jem">
        <input type="hidden" name="view" value="eventsblog">
        <?php if ($this->itemId) : ?><input type="hidden" name="Itemid" value="<?php echo (int) $this->itemId; ?>"><?php endif; ?>

        <?php if ($showDateFilter) : ?>
        <fieldset class="jem-eventsblog-periods">
            <legend class="visually-hidden"><?php echo Text::_('COM_JEM_EVENTSBLOG_DATE_FILTER'); ?></legend>
            <?php foreach ($periods as $value => $label) : ?>
                <label class="jem-eventsblog-period<?php echo $this->period === $value ? ' is-active' : ''; ?>">
                    <input type="radio" name="blog_period" value="<?php echo $value; ?>"<?php echo $this->period === $value ? ' checked' : ''; ?> onchange="this.form.submit()">
                    <span><?php echo Text::_($label); ?></span>
                </label>
            <?php endforeach; ?>
        </fieldset>
        <?php endif; ?>

        <?php if ($showCategoryFilter || $showVenueFilter || $showTypeFilter || $showCountryFilter) : ?>
        <div class="jem-eventsblog-selects">
            <?php if ($showCategoryFilter) : ?>
            <label>
                <span class="visually-hidden"><?php echo Text::_('COM_JEM_CATEGORY'); ?></span>
                <select name="blog_category" class="form-select" onchange="this.form.submit()">
                    <option value="0"><?php echo Text::_('COM_JEM_EVENTSBLOG_ALL_CATEGORIES'); ?></option>
                    <?php foreach ($this->categories as $option) : ?>
                        <option value="<?php echo (int) $option->value; ?>"<?php if ($this->categoryId === (int) $option->value) : ?> selected<?php endif; ?>><?php echo $this->escape($option->text); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php endif; ?>
            <?php if ($showVenueFilter) : ?>
            <label>
                <span class="visually-hidden"><?php echo Text::_('COM_JEM_VENUE'); ?></span>
                <select name="blog_venue" class="form-select" onchange="this.form.submit()">
                    <option value="0"><?php echo Text::_('COM_JEM_EVENTSBLOG_ALL_VENUES'); ?></option>
                    <?php foreach ($this->venues as $option) : ?>
                        <option value="<?php echo (int) $option->value; ?>"<?php if ($this->venueId === (int) $option->value) : ?> selected<?php endif; ?>><?php echo $this->escape($option->text); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php endif; ?>
            <?php if ($showTypeFilter) : ?>
            <label>
                <span class="visually-hidden"><?php echo Text::_('COM_JEM_TYPE'); ?></span>
                <select name="blog_type" class="form-select" onchange="this.form.submit()">
                    <option value="0"><?php echo Text::_('COM_JEM_EVENTSBLOG_ALL_TYPES'); ?></option>
                    <?php foreach ($this->types as $option) : ?>
                        <option value="<?php echo (int) $option->value; ?>"<?php if ($this->typeId === (int) $option->value) : ?> selected<?php endif; ?>><?php echo $this->escape($option->text); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php endif; ?>
            <?php if ($showCountryFilter) : ?>
            <label>
                <span class="visually-hidden"><?php echo Text::_('COM_JEM_COUNTRY'); ?></span>
                <select name="blog_country" class="form-select" onchange="this.form.submit()">
                    <option value=""><?php echo Text::_('COM_JEM_EVENTSBLOG_ALL_COUNTRIES'); ?></option>
                    <?php foreach ($this->countries as $option) : ?>
                        <option value="<?php echo $this->escape($option->value); ?>"<?php if ($this->country === (string) $option->value) : ?> selected<?php endif; ?>><?php echo $this->escape($option->text ?: $option->value); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </form>
    <?php endif; ?>

    <p class="jem-eventsblog-count" aria-live="polite"><?php echo Text::plural('COM_JEM_EVENTSBLOG_RESULTS', $this->pagination->total); ?></p>

    <?php if ($this->rows) : ?>
        <div class="jem-eventsblog-grid">
            <?php foreach ($this->rows as $row) :
                $category = !empty($row->categories) ? reset($row->categories) : null;
                $location = implode(', ', array_filter(array($row->venue ?? '', $row->city ?? '')));
                $hasRegistration = (int) ($row->registra ?? 0) > 0;
                $canRegister = $hasRegistration && $row->registrationOpen && $row->availabilityState !== 'soldout';
                ?>
                <article class="jem-eventsblog-card<?php echo !empty($row->featured) ? ' is-featured' : ''; ?>" itemscope itemtype="https://schema.org/Event">
                    <a class="jem-eventsblog-image-link" href="<?php echo htmlspecialchars($row->eventLink, ENT_QUOTES, 'UTF-8'); ?>" tabindex="-1" aria-hidden="true">
                        <img class="jem-eventsblog-image" src="<?php echo htmlspecialchars($row->blogImage, ENT_QUOTES, 'UTF-8'); ?>" alt="" loading="lazy">
                    </a>
                    <div class="jem-eventsblog-card-body">
                        <div class="jem-eventsblog-date">
                            <span class="icon-calendar" aria-hidden="true"></span>
                            <?php echo JemOutput::formatShortDateTime($row->dates, $row->times, $row->enddates, $row->endtimes, $this->jemsettings->showtime); ?>
                            <?php echo JemOutput::formatSchemaOrgDateTime($row->dates, $row->times, $row->enddates, $row->endtimes, true, $row); ?>
                        </div>
                        <?php if ($category) : ?><div class="jem-eventsblog-category"><?php echo $this->escape($category->catname); ?></div><?php endif; ?>
                        <h2 class="jem-eventsblog-title"><a href="<?php echo htmlspecialchars($row->eventLink, ENT_QUOTES, 'UTF-8'); ?>" itemprop="url"><span itemprop="name"><?php echo $this->escape($row->title); ?></span></a></h2>
                        <?php echo JemOutput::eventStateBadges($row, true, true); ?>
                        <?php if ($location !== '') : ?>
                            <div class="jem-eventsblog-location" itemprop="location" itemscope itemtype="https://schema.org/Place"><span class="icon-location" aria-hidden="true"></span> <span itemprop="name"><?php echo $this->escape($location); ?></span></div>
                        <?php endif; ?>
                    </div>
                    <?php if ($this->params->get('blog_show_registration', 1) && $hasRegistration) : ?>
                        <div class="jem-eventsblog-card-action">
                            <?php if ($canRegister) : ?>
                                <a class="btn btn-primary" href="<?php echo htmlspecialchars($row->eventLink, ENT_QUOTES, 'UTF-8'); ?>"><?php echo Text::_($row->availabilityState === 'waitinglist' ? 'COM_JEM_EVENTSBLOG_JOIN_WAITINGLIST' : 'COM_JEM_REGISTER'); ?></a>
                            <?php else : ?>
                                <span class="btn btn-secondary disabled" aria-disabled="true"><?php echo Text::_($row->availabilityState === 'soldout' ? 'COM_JEM_EVENT_AVAILABILITY_SOLDOUT' : 'COM_JEM_EVENTSBLOG_REGISTRATION_CLOSED'); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
        <nav class="jem-eventsblog-pagination" aria-label="<?php echo Text::_('JLIB_HTML_PAGINATION'); ?>"><?php echo $this->pagination->getPagesLinks(); ?></nav>
    <?php else : ?>
        <div class="alert alert-info"><?php echo Text::_('COM_JEM_EVENTSBLOG_NO_EVENTS'); ?></div>
    <?php endif; ?>

    <?php if ($this->params->get('showfootertext')) : ?>
        <div class="jem-eventsblog-footer description no_space floattext"><?php echo $this->params->get('footertext'); ?></div>
    <?php endif; ?>

    <div class="copyright"><?php echo JemOutput::footer(); ?></div>
</div>
