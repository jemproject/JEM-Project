<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/**
 * JEM Component Controller
 *
 * @package JEM
 *
 */
class JemController extends BaseController
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Display the view
     */
    public function display($cachable = false, $urlparams = false)
    {
        $app        = Factory::getApplication();
        $document   = $app->getDocument();
        $user       = JemFactory::getUser();
        $input      = $app->input;

        // AJAX Request for Load More
        if ($input->get('format') === 'json' && $input->server->get('HTTP_X_REQUESTED_WITH') === 'XMLHttpRequest') {
            $this->loadMore();
            return;
        }

        // Set the default view name and format from the Request.
        $jinput     = $app->input;
        $id         = $jinput->getInt('a_id', 0);
        $viewName   = $jinput->getCmd('view', 'eventslist');
        $viewFormat = $document->getType();
        $layoutName = $jinput->getCmd('layout', 'edit');

        // Check for edit form.
        if ($viewName == 'editevent' && !$this->checkEditId('com_jem.edit.event', $id)) {
            // Somehow the person just went to the form - we don't allow that.
            throw new Exception(Text::sprintf('JLIB_APPLICATION_ERROR_UNHELD_ID', $id), 403);
        }

        $view = $this->getView($viewName, $viewFormat);
        if ($view) {
            // Do any specific processing by view.
            switch ($viewName) {
                case 'attendees':
                case 'calendar':
                case 'categories':
                case 'categoriesdetailed':
                case 'category':
                case 'day':
                case 'editevent':
                case 'editvenue':
                case 'event':
                case 'eventslist':
                case 'myattendances':
                case 'myevents':
                case 'myvenues':
                case 'search':
                case 'venue':
                case 'venues':
                case 'venueslist':
                case 'mailto':
                case 'weekcal':
                    $model = $this->getModel($viewName);
                    break;
                default:
                    $model = $this->getModel('eventslist');
                    break;
            }

            // Push the model into the view
            if ($viewName == 'venue') {
                $model1 = $this->getModel('Venue');
                $model2 = $this->getModel('VenueCal');
                $view->setModel($model1, true);
                $view->setModel($model2);
            } elseif($viewName == 'category') {
                $model1 = $this->getModel('Category');
                $model2 = $this->getModel('CategoryCal');
                $view->setModel($model1, true);
                $view->setModel($model2);
            } else {
                $view->setModel($model, true);
            }

            $view->setLayout($layoutName);

            // Push document object into the view.
            $view->document = $document;

            JemHelper::loadIconFont();

            $view->display();
        }
    }

    /**
     * AJAX Load More functionality
     */
    private function loadMore()
    {
        $app = Factory::getApplication();
        $input = $app->input;
        
        $offset = $input->getInt('offset', 0);
        $limit = $input->getInt('limit', 10);
        $viewName = $input->getCmd('view', 'eventslist');
        
        // Get already displayed months from frontend (as array)
        $displayedMonths = $input->get('displayedMonths', array(), 'array');
        // Ensure it's an array and decode HTML entities
        $displayedMonths = array_map('html_entity_decode', (array)$displayedMonths);
        
        // Load model according to view
        $model = $this->getModel($viewName);
        if (!$model) {
            $model = $this->getModel('eventslist');
        }
        
        $result = $model->getEventsAjax($offset, $limit);
        
        if (!empty($result['items'])) {
            // Render template for individual events - pass displayedMonths
            $renderResult = $this->renderEventItems($result['items'], $displayedMonths);
            
            echo json_encode([
                'html' => $renderResult['html'],
                'hasMore' => $result['hasMore'],
                'total' => $result['total'],
                'displayedMonths' => $renderResult['displayedMonths'] // Return updated months to frontend
            ]);
        } else {
            echo json_encode([
                'html' => '',
                'hasMore' => false,
                'total' => 0,
                'displayedMonths' => $displayedMonths
            ]);
        }
        
        $app->close();
    }

    /**
     * Render Event Items for AJAX
     */
    private function renderEventItems($items, $displayedMonths = array())
    {
        ob_start();
        
        // Necessary Variables for Template
        $app = Factory::getApplication();
        $params = $app->getParams();
        $jemsettings = JemHelper::config();
        
        // Parameters for Icons
        $paramShowIconsOrder = $params->get('showiconsinorder', 1);
        $showiconsineventtitle = $params->get('showiconsineventtitle', 1);
        $showiconsineventdata = $params->get('showiconsineventdata', 1);
        $paramShowMonthRow = $params->get('showmonthrow', '');
        
        // Safari Browser Detection
        $isSafari = false;
        if (isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'Safari') && !strpos($_SERVER['HTTP_USER_AGENT'], 'Chrome')) {
            $isSafari = true;
        }
        
        $showMonthRow = false;
        $uri = Uri::getInstance();
        
        foreach ($items as $row) : ?>
            <?php
            if ($paramShowMonthRow && $row->dates) {
                // Get event date
                $year = date('Y', strtotime($row->dates));
                $month = date('F', strtotime($row->dates));
                $YearMonth = Text::_('COM_JEM_'.strtoupper ($month)) . ' ' . $year;

                // Check if this month was already displayed
                if (!in_array($YearMonth, $displayedMonths)) {
                    $showMonthRow = $YearMonth;
                    $displayedMonths[] = $YearMonth; // Add to list
                } else {
                    $showMonthRow = false;
                }

                // Publish month row
                if ($showMonthRow) { ?>
                    <li class="jem-event jem-row jem-justify-center bg-body-secondary" itemscope="itemscope"><span class="row-month"><?php echo $showMonthRow;?></span></li>
                <?php }
            } ?>
            <?php if (!empty($row->featured)) : ?>
                <li class="jem-event jem-row jem-justify-start jem-featured <?php echo $params->get('pageclass_sfx') . ' event_id' . htmlspecialchars($row->id); if (!empty($row->locid)) {  echo ' venue_id' . htmlspecialchars($row->locid); } ?>" itemscope="itemscope" itemtype="https://schema.org/Event" <?php if ($jemsettings->showdetails == 1 && (!$isSafari)) : echo 'onclick="location.href=\''.Route::_(JemHelperRoute::getEventRoute($row->slug)) .'\'"'; endif; ?> >
            <?php else : ?>
                <li class="jem-event jem-row jem-justify-start jem-odd<?php echo ($row->odd + 1) . $params->get('pageclass_sfx') . ' event_id' . htmlspecialchars($row->id); if (!empty($row->locid)) {  echo ' venue_id' . htmlspecialchars($row->locid); } ?>" itemscope="itemscope" itemtype="https://schema.org/Event" <?php if (($jemsettings->showdetails == 1) && (!$isSafari) && ($jemsettings->gddisabled == 0)) : echo 'onclick="location.href=\''. Route::_(JemHelperRoute::getEventRoute($row->slug)) .'\'"'; endif; ?>>
            <?php endif; ?>

            <?php if ($jemsettings->showeventimage == 1) : ?>
                <div class="jem-list-img">
                    <?php if (!empty($row->datimage)) : ?>
                        <?php
                        $dimage = JemImage::flyercreator($row->datimage, 'event');
                        echo JemOutput::flyer($row, $dimage, 'event');
                        ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="jem-event-details" <?php if (($jemsettings->showdetails == 1) && (!$isSafari) && ($jemsettings->gddisabled == 1)) : echo 'onclick="location.href=\''. Route::_(JemHelperRoute::getEventRoute($row->slug)) .'\'"'; endif; ?>>
                <?php if (($jemsettings->showtitle == 1) && ($jemsettings->showdetails == 1)) : // Display title as title of jem-event with link ?>
                    <h3 title="<?php echo Text::_('COM_JEM_TABLE_TITLE') . ': ' . htmlspecialchars($row->title); ?>">
                        <a href="<?php echo Route::_(JemHelperRoute::getEventRoute($row->slug)); ?>"><?php echo htmlspecialchars($row->title); ?></a>
                        <?php echo ($showiconsineventtitle? JemOutput::recurrenceicon($row) :''); ?>
                        <?php echo JemOutput::publishstateicon($row); ?>
                        <?php if (!empty($row->featured)) : ?>
                            <?php echo ($showiconsineventtitle? '<i class="jem-featured-icon fa fa-exclamation-circle" aria-hidden="true"></i>':''); ?>
                        <?php endif; ?>
                    </h3>

                <?php elseif (($jemsettings->showtitle == 1) && ($jemsettings->showdetails == 0)) : //Display title as title of jem-event without link ?>
                    <h4 title="<?php echo Text::_('COM_JEM_TABLE_TITLE') . ': ' . htmlspecialchars($row->title); ?>">
                        <?php echo htmlspecialchars($row->title) . ($showiconsineventtitle? JemOutput::recurrenceicon($row) :'') . JemOutput::publishstateicon($row); ?>
                        <?php if (!empty($row->featured)) : ?>
                            <?php echo ($showiconsineventtitle? '<i class="jem-featured-icon fa fa-exclamation-circle" aria-hidden="true"></i>':''); ?>
                        <?php endif; ?>
                    </h4>

                <?php elseif (($jemsettings->showtitle == 0) && ($jemsettings->showdetails == 1)) : // Display date as title of jem-event with link ?>
                    <h4>
                        <a href="<?php echo Route::_(JemHelperRoute::getEventRoute($row->slug)); ?>">
                            <?php
                            echo JemOutput::formatShortDateTime($row->dates, $row->times, $row->enddates, $row->endtimes, $jemsettings->showtime);
                            echo JemOutput::formatSchemaOrgDateTime($row->dates, $row->times, $row->enddates, $row->endtimes);
                            ?>
                        </a>
                        <?php echo ($showiconsineventtitle? JemOutput::recurrenceicon($row) :''); ?>
                        <?php echo JemOutput::publishstateicon($row); ?>
                        <?php if (!empty($row->featured)) : ?>
                            <?php echo ($showiconsineventtitle? '<i class="jem-featured-icon fa fa-exclamation-circle" aria-hidden="true"></i>':''); ?>
                        <?php endif; ?>
                    </h4>

                <?php else : // Display date as title of jem-event without link ?>
                    <h4>
                        <?php
                        echo JemOutput::formatShortDateTime($row->dates, $row->times,
                            $row->enddates, $row->endtimes, $jemsettings->showtime);
                        echo JemOutput::formatSchemaOrgDateTime($row->dates, $row->times,
                            $row->enddates, $row->endtimes);
                        ?>
                        <?php echo ($showiconsineventtitle? JemOutput::recurrenceicon($row) :''); ?>
                        <?php echo JemOutput::publishstateicon($row); ?>
                        <?php if (!empty($row->featured)) : ?>
                            <?php echo ($showiconsineventtitle? '<i class="jem-featured-icon fa fa-exclamation-circle" aria-hidden="true"></i>':''); ?>
                        <?php endif; ?>
                    </h4>
                <?php endif; ?>

                <?php // Display other information below in a row ?>
                <div class="jem-list-row">
                    <?php if ($jemsettings->showtitle == 1) : ?>
                        <div class="jem-event-info" title="<?php echo Text::_('COM_JEM_TABLE_DATE').': '.strip_tags(JemOutput::formatShortDateTime($row->dates, $row->times, $row->enddates, $row->endtimes, $jemsettings->showtime)); ?>">
                            <?php echo ($showiconsineventdata? '<i class="far fa-clock" aria-hidden="true"></i>':''); ?>
                            <?php
                            echo JemOutput::formatShortDateTime($row->dates, $row->times, $row->enddates, $row->endtimes, $jemsettings->showtime);
                            echo JemOutput::formatSchemaOrgDateTime($row->dates, $row->times, $row->enddates, $row->endtimes);
                            ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($jemsettings->showtitle == 0) : ?>
                        <div class="jem-event-info" title="<?php echo Text::_('COM_JEM_TABLE_TITLE').': '.htmlspecialchars($row->title); ?>">
                            <?php echo ($showiconsineventdata? '<i class="fa fa-comment" aria-hidden="true"></i>':''); ?>
                            <?php echo htmlspecialchars($row->title); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($jemsettings->showlocate == 1 && !empty($row->locid)) : ?>
                        <div class="jem-event-info" title="<?php echo Text::_('COM_JEM_TABLE_LOCATION') . ': ' . htmlspecialchars($row->venue); ?>">
                            <?php echo ($showiconsineventdata? '<i class="fa fa-map-marker" aria-hidden="true"></i>':''); ?>
                            <?php if ($jemsettings->showlinkvenue == 1) : ?>
                                <a href="<?php echo Route::_(JemHelperRoute::getVenueRoute($row->venueslug ?? '')); ?>">
                                    <?php echo htmlspecialchars($row->venue); ?>
                                </a>
                            <?php else : ?>
                                <?php echo htmlspecialchars($row->venue); ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($jemsettings->showcity == 1 && !empty($row->city)) : ?>
                        <div class="jem-event-info" title="<?php echo Text::_('COM_JEM_TABLE_CITY') . ': ' . htmlspecialchars($row->city); ?>">
                            <?php echo ($showiconsineventdata? '<i class="fa fa-building" aria-hidden="true"></i>':''); ?>
                            <?php echo htmlspecialchars($row->city); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($jemsettings->showstate == 1 && !empty($row->state)): ?>
                        <div class="jem-event-info" title="<?php echo Text::_('COM_JEM_TABLE_STATE') . ': ' . htmlspecialchars($row->state); ?>">
                            <?php echo ($showiconsineventdata? '<i class="fa fa-map" aria-hidden="true"></i>':''); ?>
                            <?php echo htmlspecialchars($row->state); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($jemsettings->showcat == 1) : ?>
                        <?php 
                        $catList = JemOutput::getCategoryList($row->categories, $jemsettings->catlinklist);
                        $catString = implode(", ", $catList); 
                        ?>
                        <div class="jem-event-info" title="<?php echo strip_tags(Text::_('COM_JEM_TABLE_CATEGORY') . ': ' . $catString); ?>">
                            <?php echo ($showiconsineventdata? '<i class="fa fa-tag" aria-hidden="true"></i>':''); ?>
                            <?php echo $catString; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($jemsettings->showatte == 1) : ?>
                         <?php 
                         $maxPlaces = (int)($row->maxplaces ?? 0);
                         $regCount = (int)($row->regCount ?? 0);
                         ?>
                        <?php if ($regCount > 0) : ?>
                            <div class="jem-event-info" title="<?php echo Text::_('COM_JEM_TABLE_ATTENDEES') . ': ' . $regCount; ?>">
                                <?php echo ($showiconsineventdata? '<i class="fa fa-user" aria-hidden="true"></i>':''); ?>
                                <?php echo $regCount . " / " . $maxPlaces; ?>
                            </div>
                        <?php elseif ($maxPlaces == 0) : ?>
                            <div>
                                <?php echo ($showiconsineventdata? '<i class="fa fa-user" aria-hidden="true"></i>':''); ?>
                                &gt; 0
                            </div>
                        <?php else : ?>
                            <div class="jem-event-info-small jem-event-attendees">
                                <?php echo ($showiconsineventdata? '<i class="fa fa-user" aria-hidden="true"></i>':''); ?>
                                &lt; <?php echo $maxPlaces; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <?php if ($params->get('show_introtext_events') == 1) : ?>
                    <div class="jem-event-intro">
                        <?php echo $row->introtext ?? ''; ?>
                        <?php $settings = JemHelper::globalattribs(); ?>
                        <?php if ($settings->get('event_show_readmore') && $row->fulltext != '' && $row->fulltext != '<br />') : ?>
                            <a href="<?php echo Route::_(JemHelperRoute::getEventRoute($row->slug)); ?>"><?php echo Text::_('COM_JEM_EVENT_READ_MORE_TITLE'); ?></a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <meta itemprop="name" content="<?php echo htmlspecialchars($row->title); ?>"/>
            <meta itemprop="url" content="<?php echo rtrim($uri->base(), '/').Route::_(JemHelperRoute::getEventRoute($row->slug)); ?>" />
            <meta itemprop="identifier" content="<?php echo rtrim($uri->base(), '/').Route::_(JemHelperRoute::getEventRoute($row->slug)); ?>" />
            <div itemtype="https://schema.org/Place" itemscope itemprop="location" style="display: none;">
                <meta itemprop="name" content="<?php echo !empty($row->locid) ? htmlspecialchars($row->venue) : 'None'; ?>"/>
                <?php
                $microadress = '';
                if (!empty($row->city)) {
                    $microadress .= htmlspecialchars($row->city);
                }
                if (!empty($microadress)) {
                    $microadress .= ', ';
                }
                if (!empty($row->state)) {
                    $microadress .= htmlspecialchars($row->state);
                }
                if (empty($microadress)) {
                    $microadress .= '-';
                }
                ?>
                <meta itemprop="address" content="<?php echo $microadress; ?>"/>
            </div>

            </li>
        <?php endforeach;
        
        $html = ob_get_clean();
        
        // Return both HTML and the updated displayedMonths
        return array(
            'html' => $html,
            'displayedMonths' => $displayedMonths
        );
    }

    /**
     * For attachment downloads
     */
    public function getfile()
    {
        // Check for request forgeries
        Session::checkToken('request') or jexit('Invalid Token');

        $id = Factory::getApplication()->input->getInt('file', 0);
        $path = JemAttachment::getAttachmentPath($id);

        if (!$path || !file_exists($path)) {
             throw new \Exception(Text::_('JGLOBAL_RESOURCE_NOT_FOUND'), 404);
        }

        header("Content-Type: application/octet-stream");
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        header('Content-Length: ' . filesize($path));
        ob_clean();
        ob_end_flush();
        readfile($path);
        
        $this->app->close();
    }

    /**
     * Delete attachment
     *
     * @return true on success
     * @access public
     */
    public function ajaxattachremove()
    {
        // Check for request forgeries
        Session::checkToken('request') or jexit('Invalid Token');

        $jemsettings = JemHelper::config();
        $res = 0;

        if ($jemsettings->attachmentenabled > 0) {
            $id     = Factory::getApplication()->input->getInt('id', 0);
            $res = JemAttachment::remove($id);
        } // else don't delete anything

        if (!$res) {
            echo 0; // The caller expects an answer!
            $this->app->close();
        }

        $cache = Factory::getCache('com_jem');
        $cache->clean();

        echo 1; // The caller expects an answer!
        $this->app->close();;
    }

    /**
     * Remove image
     * @deprecated since version 1.9.7
     */
    public function ajaximageremove()
    {
        // prevent unwanted usage
        $this->app->close();
    }
}
?>