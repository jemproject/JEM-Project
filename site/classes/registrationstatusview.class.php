<?php
/**
 * @package JEM
 * @copyright (C) 2013-2026 joomlaeventmanager.net
 * @license https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

/** Shared presentation setup; counters are never stored in Joomla page cache. */
trait JemRegistrationStatusView
{
    public array $report = [];

    /** @return void Load authorised data and common assets for either client. */
    protected function prepareReport(): void
    {
        $app = Factory::getApplication();
        $this->report = $this->getModel()->getReport();
        $app->setHeader('Cache-Control', 'no-store, private', true);
        $document = $app->getDocument();
        $document->setTitle(Text::_('COM_JEM_RS_TITLE'));
        $document->setMetadata('robots', 'noindex, nofollow');
        $assets = $document->getWebAssetManager();
        $assets->registerAndUseStyle('com_jem.registration-status', 'com_jem/registration-status.css');
        $assets->registerAndUseScript('com_jem.registration-status', 'com_jem/registration-status.js', [], ['defer' => true]);
    }
}
