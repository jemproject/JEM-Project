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
use Joomla\CMS\Log\Log;
use Joomla\CMS\Response\JsonResponse;

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

        // Set the default view name and format from the Request.
        $jinput     = $app->input;
        $viewName   = $jinput->getCmd('view', 'eventslist');
        $viewFormat = $document->getType();
        $layoutName = $jinput->getCmd('layout', 'edit');

        // Apply one access policy before any frontend editor or selector can load data.
        if (($viewName === 'editevent') || ($viewName === 'editvenue')) {
            if (JemFrontendAccess::redirectGuestToLogin($app)) {
                return false;
            }

            $isEventSelector = ($viewName === 'editevent') && in_array(
                $layoutName,
                array('choosevenue', 'choosecontact', 'choosearticle', 'chooseusers'),
                true
            );

            // Selector layouts are auxiliary requests, not editor record routes.
            // Only their explicit editor id is relevant; a generic id may belong
            // to the active Joomla menu item and must not be treated as an event.
            $id = $isEventSelector
                ? JemFrontendAccess::readSelectorRecordId($jinput)
                : JemFrontendAccess::normaliseRecordId($jinput);
            $type  = ($viewName === 'editevent') ? 'event' : 'venue';
            $model = $this->getModel($viewName);

            if (!$model) {
                throw new Exception(Text::_('JERROR_AN_ERROR_HAS_OCCURRED'), 500);
            }

            // Copy routes expose source data, so the source requires edit permission too.
            if ($jinput->exists('from_id')) {
                $sourceId = JemFrontendAccess::readId($jinput, array('from_id'), true);
                $source = $model->getItem($sourceId);

                if (!$source || ((int) $source->id !== $sourceId)) {
                    $key = ($type === 'event')
                        ? 'COM_JEM_EVENT_ERROR_EVENT_NOT_FOUND'
                        : 'COM_JEM_VENUE_ERROR_VENUE_NOT_FOUND';
                    throw new Exception(Text::_($key), 404);
                }

                JemFrontendAccess::enforce(JemFrontendAccess::decideEdit($user, $type, $source));
            }

            if ($isEventSelector) {
                $this->checkToken('request');
            }

            if ($id > 0) {
                $item = $model->getItem($id);

                if (!$item || ((int) $item->id !== $id)) {
                    $key = ($type === 'event')
                        ? 'COM_JEM_EVENT_ERROR_EVENT_NOT_FOUND'
                        : 'COM_JEM_VENUE_ERROR_VENUE_NOT_FOUND';
                    throw new Exception(Text::_($key), 404);
                }

                JemFrontendAccess::enforce(JemFrontendAccess::decideEdit($user, $type, $item));

                if (!$this->checkEditId('com_jem.edit.' . $type, $id)) {
                    throw new Exception(Text::sprintf('JLIB_APPLICATION_ERROR_UNHELD_ID', $id), 403);
                }
            } elseif ($isEventSelector) {
                if (!JemFrontendAccess::canUseEventSelectors($app, $user, $model)) {
                    throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
                }
            } else {
                $categoryId = ($type === 'event') ? $jinput->getInt('catid', 0) : 0;

                JemFrontendAccess::enforce(JemFrontendAccess::decideAdd($user, $type, $categoryId));
            }
        }

        $view = $this->getView($viewName, $viewFormat);
        if ($view) {
            // Do any specific processing by view.
            switch ($viewName) {
                case 'attendees':
                case 'attendeeregistrations':
                case 'calendar':
                case 'categories':
                case 'categoriesdetailed':
                case 'category':
                case 'day':
                case 'editevent':
                case 'editvenue':
                case 'event':
                case 'eventsblog':
                case 'eventslist':
                case 'myattendances':
                case 'myevents':
                case 'myvenues':
                case 'search':
                case 'specialday':
                case 'specialdays':
                case 'venue':
                case 'venues':
                case 'venueslist':
                case 'mailto':
                case 'weekcal':
                case 'typeevents':
                case 'typevenues':
                    $model = $this->getModel($viewName);
                    break;
                case 'eventsmap':
                    $model = $this->getModel('eventslist');
                    break;
                case 'venuesmap':
                    $model = $this->getModel('venueslist');
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
     * Return the next bounded events-list page.
     */
    public function loadmore()
    {
        $app = Factory::getApplication();
        $input = $app->input;

        try {
            $method = $input->server->getString('REQUEST_METHOD', '');
            $query = (string) $input->server->get('QUERY_STRING', '', 'raw');

            if (!JemLoadMoreRequestPolicy::isGetRequest($method)
                || !JemLoadMoreRequestPolicy::isQueryStringAllowed($query)) {
                throw new InvalidArgumentException('Invalid load-more request.');
            }

            $request = JemLoadMoreRequestPolicy::normaliseParameters($input->getArray());
            $remoteAddress = JemLoadMoreRequestPolicy::normaliseRemoteAddress(
                $input->server->getString('REMOTE_ADDR', '')
            );
            $rateDirectory = JPATH_CACHE . '/com_jem/loadmore';
            $allowed = JemLoadMoreRequestPolicy::consumeRateLimit(
                $rateDirectory,
                'ip:' . $remoteAddress,
                JemLoadMoreRequestPolicy::RATE_LIMIT,
                JemLoadMoreRequestPolicy::RATE_WINDOW_SECONDS
            );

            if (!$allowed) {
                $app->setHeader('Retry-After', (string) JemLoadMoreRequestPolicy::RATE_WINDOW_SECONDS, true);
                $this->sendLoadMoreResponse(array(), 429, true);
                return;
            }

            $model = $this->getModel('eventslist');

            if (!$model || !method_exists($model, 'getEventsAjax')) {
                throw new RuntimeException('Events list model is unavailable.');
            }

            $result = $model->getEventsAjax($request['offset'], $request['limit']);
            $rendered = $this->renderLoadMoreItems(
                (array) ($result['items'] ?? array()),
                $request['lastDisplayedMonth'],
                $request['offset']
            );
            $hasMore = !empty($result['hasMore'])
                && isset($result['nextOffset'])
                && (int) $result['nextOffset'] <= JemLoadMoreRequestPolicy::MAX_OFFSET;

            $this->sendLoadMoreResponse(array(
                'html' => $rendered['html'],
                'hasMore' => $hasMore,
                'nextOffset' => $hasMore ? (int) $result['nextOffset'] : null,
                'lastDisplayedMonth' => $rendered['lastDisplayedMonth'],
            ));
        } catch (InvalidArgumentException $exception) {
            $this->sendLoadMoreResponse(array(), 400, true);
        } catch (Throwable $exception) {
            Log::add(
                'JEM load-more request failed: ' . get_class($exception),
                Log::ERROR,
                'com_jem.security'
            );
            $this->sendLoadMoreResponse(array(), 500, true);
        }
    }

    /**
     * Render event items through the same partial used by the initial page.
     */
    private function renderLoadMoreItems(array $items, string $previousYearMonth, int $offset): array
    {
        $app = Factory::getApplication();
        $params = $app->getParams();
        $jemsettings = JemHelper::config();
        $settings = JemHelper::globalattribs();
        $showIconsInEventTitle = (bool) $params->get('showiconsineventtitle', 1);
        $showIconsInEventData = (bool) $params->get('showiconsineventdata', 1);
        $showAvailabilityText = (bool) $params->get('event_show_availability', 0);
        $showMonthRow = (bool) $params->get('showmonthrow', '');
        $userAgent = $app->input->server->getString('HTTP_USER_AGENT', '');
        $isSafari = strpos($userAgent, 'Safari') !== false && strpos($userAgent, 'Chrome') === false;
        $odd = $offset % 2;

        ob_start();

        try {
            foreach ($items as $row) {
                $row->odd = $odd;
                $odd = 1 - $odd;

                if (empty($row->user_has_access_category)) {
                    continue;
                }

                if ($showMonthRow && !empty($row->dates)) {
                    $year = date('Y', strtotime($row->dates));
                    $month = date('F', strtotime($row->dates));
                    $yearMonth = Text::_('COM_JEM_' . strtoupper($month)) . ' ' . $year;

                    if ($previousYearMonth === '' || $previousYearMonth !== $yearMonth) {
                        echo '<li class="jem-event jem-row jem-justify-center bg-body-secondary" itemscope="itemscope"><span class="row-month">'
                            . htmlspecialchars($yearMonth, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                            . '</span></li>';
                    }

                    $previousYearMonth = $yearMonth;
                }

                $displayData = array(
                    'row' => $row,
                    'params' => $params,
                    'jemsettings' => $jemsettings,
                    'settings' => $settings,
                    'isSafari' => $isSafari,
                    'showIconsInEventTitle' => $showIconsInEventTitle,
                    'showIconsInEventData' => $showIconsInEventData,
                    'showAvailabilityText' => $showAvailabilityText,
                    'structuredData' => true,
                    'imagePathAware' => false,
                );
                require JPATH_COMPONENT_SITE
                    . '/common/views/tmpl/responsive/default_jem_eventslist_item.php';
            }

            $html = (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }

        return array(
            'html' => $html,
            'lastDisplayedMonth' => $previousYearMonth,
        );
    }

    /**
     * Emit a private JSON response with an explicit HTTP status.
     */
    private function sendLoadMoreResponse(array $payload, int $status = 200, bool $error = false): void
    {
        $app = Factory::getApplication();

        http_response_code($status);
        $app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        $app->setHeader('X-Content-Type-Options', 'nosniff', true);
        $app->setHeader('Cache-Control', 'no-store, private', true);
        $app->setHeader('X-Robots-Tag', 'noindex, nofollow', true);
        $app->sendHeaders();

        echo new JsonResponse(
            $payload,
            $error ? Text::_('JERROR_AN_ERROR_HAS_OCCURRED') : null,
            $error,
            true
        );
        $app->close();
    }

    /**
     * For attachment downloads
     */
    public function getfile()
    {
        $this->checkToken('request');

        $id = Factory::getApplication()->input->getInt('file', 0);

        try {
            $path = JemAttachment::getAttachmentPath($id);
        } catch (\Exception $e) {
            JemAttachment::logDownloadError($id, 'frontend', $e->getMessage());
            throw $e;
        }

        if (!$path || !file_exists($path)) {
             JemAttachment::logDownloadError($id, 'frontend', 'File not found');
             throw new \Exception(Text::_('JGLOBAL_RESOURCE_NOT_FOUND'), 404);
        }

        header("Content-Type: application/octet-stream");
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        header('Content-Length: ' . filesize($path));
        ob_clean();
        ob_end_flush();
        $delivered = readfile($path);

        if ($delivered !== false) {
            JemAttachment::recordDownload($id);
        } else {
            JemAttachment::logDownloadError($id, 'frontend', 'File delivery failed');
        }
        
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
        $this->checkToken('request');

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
