<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView;

/**
 * Raw: Typeevents
 */
class JemViewTypeevents extends HtmlView
{
    /**
     * Creates the PDF output for the Type events view.
     */
    public function display($tpl = null)
    {
        $app = Factory::getApplication();

        if ($app->input->getCmd('layout', '') !== 'pdf') {
            $app->close();

            return;
        }

        $model = $this->getModel();
        $model->setState('list.start', 0);
        $model->setState('list.limit', 0);
        $type = $model->getType();
        $typeName = $type && trim((string) ($type->name ?? '')) !== ''
            ? Text::sprintf('COM_JEM_TYPEEVENTS_TITLE', (string) $type->name)
            : Text::_('COM_JEM_EVENTS');

        JemPdfView::renderTypeEventList($typeName, $type, (array) $model->getItems(), 'jem-type-events.pdf');
    }
}
