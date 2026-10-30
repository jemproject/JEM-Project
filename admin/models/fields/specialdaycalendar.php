<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;

if (!class_exists('JFormFieldCalendarJem')) {
    require_once __DIR__ . '/calendarjem.php';
}
require_once JPATH_SITE . '/components/com_jem/classes/specialdaydate.class.php';

/** ISO date-only calendar which retains invalid submitted text for correction. */
class JFormFieldSpecialdaycalendar extends JFormFieldCalendarJem
{
    protected $type = 'Specialdaycalendar';

    public function filter($value, $group = null, ?Registry $input = null)
    {
        // Leave invalid input intact for Joomla's normal validation/retry flow.
        return is_string($value) ? trim($value) : $value;
    }

    public function validate($value, $group = null, ?Registry $input = null)
    {
        $valid = parent::validate($value, $group, $input);
        if ($valid !== true) {
            return $valid;
        }

        return JemSpecialDayDate::normalise($value) !== null
            ? true
            : new \RuntimeException(Text::sprintf('COM_JEM_EVENT_ERROR_INVALID_DATE', $this->title));
    }

    protected function getInput()
    {
        $data = $this->collectLayoutData();

        if ((string) $this->value !== '' && JemSpecialDayDate::normalise($this->value) === null) {
            // Initialise Joomla's picker empty; restore the submitted text afterwards.
            // Both PHP calendar rendering and JS initialisation otherwise roll it forward.
            $data['value'] = '';
            $data['dataAttribute'] .= ' data-jem-invalid-date="'
                . htmlspecialchars((string) $this->value, ENT_QUOTES, 'UTF-8') . '"';
        }

        return $this->getRenderer($this->layout)->render($data);
    }
}
