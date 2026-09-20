<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Layout\LayoutHelper;

echo LayoutHelper::render('category.customfields', array(
    'form'          => $this->form,
    'configuration' => $this->categoryCustomFields,
    'legacyFields'  => $this->legacyEventCustomFields,
    'joomlaFields'  => $this->joomlaEventCustomFields,
    'joomlaGroups'  => $this->joomlaEventFieldGroups,
));
