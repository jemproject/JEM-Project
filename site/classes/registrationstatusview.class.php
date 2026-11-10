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
        foreach ([
            'COM_JEM_TK_ACL_PAY', 'COM_JEM_TK_ACL_PAY_DESC', 'COM_JEM_TK_ACL_ADMIT',
            'COM_JEM_TK_ACL_ADMIT_DESC', 'COM_JEM_TK_OPEN', 'COM_JEM_TK_LOOKUP_NOTICE',
            'COM_JEM_TK_CHOOSE_EVENT', 'COM_JEM_TK_REFERENCE', 'COM_JEM_TK_LOOKUP',
            'COM_JEM_TK_CAMERA', 'COM_JEM_TK_CAMERA_DEVICE', 'COM_JEM_TK_STOP_CAMERA',
            'COM_JEM_TK_CAMERA_ERROR', 'COM_JEM_TK_INVALID_QR', 'COM_JEM_TK_DOCUMENT',
            'COM_JEM_TK_DOWNLOAD', 'COM_JEM_TK_PDF_NOTICE', 'COM_JEM_TK_PDF_UNAVAILABLE',
            'COM_JEM_TK_PLACES', 'COM_JEM_TK_REMAINING', 'COM_JEM_TK_CASHIER',
            'COM_JEM_TK_GATE', 'COM_JEM_TK_PAYMENT_NOTICE', 'COM_JEM_TK_AMOUNT',
            'COM_JEM_TK_METHOD', 'COM_JEM_TK_CASH', 'COM_JEM_TK_TRANSFER',
            'COM_JEM_TK_RECEIPT', 'COM_JEM_TK_CONFIRM_PAYMENT', 'COM_JEM_TK_CONFIRM_ADMISSION',
            'COM_JEM_TK_AREA', 'COM_JEM_TK_GENERAL', 'COM_JEM_TK_QUANTITY',
            'COM_JEM_TK_ASK_PAYMENT', 'COM_JEM_TK_ASK_ADMISSION', 'COM_JEM_TK_TOTAL',
            'COM_JEM_TK_COLLECTED', 'COM_JEM_TK_DUE', 'COM_JEM_TK_REFUND_DUE',
            'COM_JEM_TK_PAYMENT_FREE', 'COM_JEM_TK_PAYMENT_PAID', 'COM_JEM_TK_PAYMENT_UNPAID',
            'COM_JEM_TK_PAYMENT_UNKNOWN', 'COM_JEM_TK_PAYMENT_REFUND_PENDING', 'COM_JEM_TK_PAYMENT',
            'COM_JEM_TK_ADMISSION', 'COM_JEM_TK_OPERATOR', 'COM_JEM_TK_AUDIT',
            'COM_JEM_TK_WORKING', 'COM_JEM_TK_RECORDED', 'COM_JEM_TK_ALREADY_RECORDED',
            'COM_JEM_TK_NOT_FOUND', 'COM_JEM_TK_INVALID_AMOUNT', 'COM_JEM_TK_INVALID_QUANTITY',
            'COM_JEM_TK_INVALID_OPERATION', 'COM_JEM_TK_STALE', 'COM_JEM_TK_EVENT_CLOSED',
            'COM_JEM_TK_NOT_CONFIRMED', 'COM_JEM_TK_PAYMENT_REQUIRED', 'COM_JEM_TK_USED',
            'COM_JEM_TK_RECEIPT_REQUIRED', 'COM_JEM_TK_CURRENCY_CHANGED', 'COM_JEM_TK_IDENTITY_LOCKED',
            'COM_JEM_TK_ERROR', 'COM_JEM_RS_ENTERED',
        ] as $key) {
            Text::script($key);
        }
        $assets->registerAndUseScript('com_jem.ticket-desk', 'com_jem/ticket-desk.js', ['version' => (string) filemtime(JPATH_ROOT . '/media/com_jem/js/ticket-desk.js')], ['type' => 'module']);

        $assets->registerAndUseStyle('com_jem.registration-status', 'com_jem/registration-status.css', ['version' => (string) filemtime(JPATH_ROOT . '/media/com_jem/css/registration-status.css')]);
        $assets->registerAndUseScript('com_jem.registration-status', 'com_jem/registration-status.js', ['version' => (string) filemtime(JPATH_ROOT . '/media/com_jem/js/registration-status.js')], ['defer' => true]);
    }
}
