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

/**
 * Raw: Categories
 */
class JemViewCategories extends HtmlView
{
    /**
     * Creates the PDF output for the Categories view.
     */
    public function display($tpl = null)
    {
        $app = Factory::getApplication();

        if ($app->input->getCmd('layout', '') !== 'pdf') {
            $app->close();

            return;
        }

        $model = $this->getModel();
        $model->setState('limitstart', 0);
        $model->setState('limit', 0);

        $categoryType = $model->getType();
        $title = Text::_('COM_JEM_CATEGORIES');

        if ($model->isTypeFilterRequested() && $categoryType && trim((string) ($categoryType->name ?? '')) !== '') {
            $title = Text::sprintf('COM_JEM_TYPECATEGORIES_TITLE', (string) $categoryType->name);
        }

        $rows = (array) $model->getData();
        $params = $app->getParams('com_jem');
        $previewLimit = max(0, (int) $params->get('detcat_nr', 3));

        if ($params->get('show_category_events', 1) && $previewLimit > 0) {
            foreach ($rows as $row) {
                $row->events = $model->getEventdata($row->id, $previewLimit);
            }
        }

        $typeItems = (array) $model->getTypeItems();
        if ($model->isTypeFilterRequested() && !$categoryType && !empty($rows)) {
            $typeOrder = array();
            $position = 0;
            foreach ($typeItems as $typeId => $typeItem) {
                $typeOrder[(int) $typeId] = $position++;
            }

            usort($rows, static function ($left, $right) use ($typeOrder) {
                $leftType = (int) ($left->type_id ?? 0);
                $rightType = (int) ($right->type_id ?? 0);
                $leftOrder = $leftType > 0 && isset($typeOrder[$leftType]) ? $typeOrder[$leftType] : PHP_INT_MAX;
                $rightOrder = $rightType > 0 && isset($typeOrder[$rightType]) ? $typeOrder[$rightType] : PHP_INT_MAX;

                if ($leftOrder !== $rightOrder) {
                    return $leftOrder <=> $rightOrder;
                }

                return strcasecmp((string) ($left->catname ?? ''), (string) ($right->catname ?? ''));
            });
        }

        JemPdfView::renderCategoryList($title, $rows, $typeItems, 'jem-categories.pdf');
    }
}
