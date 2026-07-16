<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @copyright  (C) 2005-2009 Christoph Lukes
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;

/**
 * Raw: Category
 */
class JemViewCategory extends HtmlView
{
    /**
     * Creates the output for the Category view
     */
    public function display($tpl = null)
    {
        $settings  = JemHelper::config();
        $settings2 = JemHelper::globalattribs();
        $app       = Factory::getApplication();
        $jinput    = $app->input;

        $year  = (int)$jinput->getInt('yearID', date("Y"));
        $month = (int)$jinput->getInt('monthID', date("m"));
        $catid = (int)$jinput->getInt('id', 0);
        if (empty($catid)) {
            $catid = (int)$app->getParams()->get('id', 0);
        }
        $layout = $jinput->getCmd('layout', '');

        if ($layout === 'pdf') {
            $model = $this->getModel('Category');
            $model->setId($catid);
            $model->setState('list.start', 0);
            $model->setState('list.limit', 0);
            $category = $model->getCategory();

            if (empty($category)) {
                $app->close();

                return;
            }

            if (empty($category->user_has_access_category)) {
                $user = JemFactory::getUser();
                if ($user->get('guest') || !$user->get('id')) {
                    $app->enqueueMessage(Text::_('COM_JEM_LOGIN_TO_ACCESS'), 'warning');
                    $app->redirect(Route::_('index.php?option=com_users&view=login&return=' . base64_encode($app->input->server->getString('REQUEST_URI')), false));

                    return;
                }

                throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
            }

            $description = Text::_('COM_JEM_NO_DESCRIPTION');
            if (!empty($category->description)) {
                $category->text = $category->description;
                $category->title = $category->catname ?? $category->title ?? Text::_('COM_JEM_CATEGORY');
                $params = $app->getParams();
                PluginHelper::importPlugin('content');
                $app->triggerEvent('onContentPrepare', array('com_jem.category', &$category, &$params, 0));
                $description = (string) $category->text;
            }

            $categoryName = trim((string) ($category->catname ?? $category->title ?? ''));
            if ($categoryName === '') {
                $categoryName = Text::_('COM_JEM_CATEGORY');
            }

            JemPdfView::renderCategoryDetail(
                $categoryName,
                $category,
                (array) $model->getChildren(),
                (array) $model->getItems(),
                $description,
                'jem-category-' . $catid . '.pdf'
            );

            return;
        }

        if ($settings2->get('global_show_ical_icon','0')==1) {
            // Get data from the model
            $model = $this->getModel('CategoryCal');
            $model->setId($catid);
            $model->setState('list.start',0);
            $model->setState('list.limit',$settings->ical_max_items);
            $model->setDate(mktime(0, 0, 1, $month, 1, $year));

            $rows = $model->getItems();

            // initiate new CALENDAR
            $category = $model->getCategories($catid);
            $vcal     = JemHelper::getCalendarTool();
            $filename = "events_category_" . $category[0]->catname . "_" . $year . str_pad($month, 2, '0', STR_PAD_LEFT) . ".ics";

            if (!empty($rows)) {
                foreach ($rows as $row) {
                    JemHelper::icalAddEvent($vcal, $row);
                }
            }

            // generate and redirect output to user browser
            JemHelper::sendCalendar($vcal, $filename);
        }
    }
}
