<?php

declare(strict_types=1);

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Session\Session as CmsSession;
use Joomla\Session\Session;
use Joomla\Session\SessionInterface;

if ($argc !== 7) {
    fwrite(STDERR, "Usage: pricing_quote_concurrent_worker.php <joomla-root> <event-id> <price-id> <access-id> <group-id> <now>\n");
    exit(2);
}

$root = rtrim(str_replace('\\', '/', $argv[1]), '/');
$eventId = (int) $argv[2];
$priceId = (int) $argv[3];
$accessId = (int) $argv[4];
$groupId = (int) $argv[5];
$now = (string) $argv[6];

define('_JEXEC', 1);
define('JPATH_BASE', $root);
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['DOCUMENT_ROOT'] = $root;

require_once $root . '/includes/defines.php';
require_once $root . '/includes/framework.php';
restore_error_handler();
restore_error_handler();
restore_exception_handler();

$container = Factory::getContainer();
$container->alias('session.web', 'session.web.site')
    ->alias('session', 'session.web.site')
    ->alias('JSession', 'session.web.site')
    ->alias(CmsSession::class, 'session.web.site')
    ->alias(Session::class, 'session.web.site')
    ->alias(SessionInterface::class, 'session.web.site');
Factory::$application = $container->get(SiteApplication::class);

require_once $root . '/components/com_jem/classes/pricingquote.class.php';
$db = $container->get('DatabaseDriver');
$service = new JemPricingQuoteService($db);
$operationReference = JemRegistrationIdentity::generateOperationReference();

try {
    $result = $service->withLockedQuote(
        $eventId,
        array(array('event_price_id' => $priceId, 'quantity' => 1)),
        array(
            'expectedPricingRevision' => 2,
            'accessLevels' => array($accessId),
            'userGroups' => array($groupId),
            'now' => $now,
        ),
        $operationReference,
        static function (array $quote) use ($db, $eventId): array {
            // Keep the event row locked briefly so the competing worker must
            // wait and then re-read committed inventory before it can quote.
            usleep(350000);
            $reference = JemRegistrationIdentity::generateRegistrationReference();
            $registration = (object) array(
                'event' => $eventId,
                'uid' => -random_int(1000, 999999999),
                'places' => (int) $quote['quantity'],
                'uregdate' => gmdate('Y-m-d H:i:s'),
                'uip' => '127.0.0.1',
                'waiting' => 0,
                'status' => 1,
                'comment' => '',
                'reference' => $reference,
                'created' => gmdate('Y-m-d H:i:s'),
                'modified' => gmdate('Y-m-d H:i:s'),
                'revision' => 1,
                'pricing_mode' => 'multiple',
                'currency' => $quote['currency'],
                'subtotal_net' => $quote['subtotal_net'],
                'discount_total' => '0.00',
                'tax_total' => $quote['tax_total'],
                'management_fee_net' => '0.00',
                'management_fee_tax' => '0.00',
                'management_fee_gross' => '0.00',
                'grand_total' => $quote['grand_total'],
                'payment_state' => 'not_required',
                'price_locked_at' => gmdate('Y-m-d H:i:s'),
            );
            $db->insertObject('#__jem_register', $registration, 'id');
            $registerId = (int) $registration->id;

            foreach ($quote['lines'] as $lineNumber => $line) {
                $item = (object) array(
                    'register_id' => $registerId,
                    'registration_revision' => 1,
                    'line_number' => $lineNumber + 1,
                    'line_kind' => 'admission',
                    'event_price_id' => $line['event_price_id'],
                    'capacity_pool_id' => $line['capacity_pool_id'],
                    'item_code' => $line['code'],
                    'item_name' => $line['name'],
                    'quantity' => $line['quantity'],
                    'currency' => $quote['currency'],
                    'price_includes_tax' => $quote['prices_include_tax'],
                    'unit_net' => $line['unit_net'],
                    'unit_tax' => $line['unit_tax'],
                    'unit_gross' => $line['unit_gross'],
                    'line_net' => $line['line_net'],
                    'line_tax' => $line['line_tax'],
                    'line_gross' => $line['line_gross'],
                    'tax_code' => $line['tax_code'],
                    'tax_name' => $line['tax_name'],
                    'tax_type' => $line['tax_type'],
                    'tax_rate' => $line['tax_rate'],
                    'calculation_mode' => 'admission',
                    'calculation_basis' => 'gross',
                    'condition_snapshot' => json_encode($line['conditions'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'created' => gmdate('Y-m-d H:i:s'),
                );
                $db->insertObject('#__jem_register_items', $item);
            }

            return array('status' => 'confirmed', 'register_id' => $registerId);
        }
    );

    echo json_encode($result, JSON_UNESCAPED_SLASHES), PHP_EOL;
} catch (InvalidArgumentException|RuntimeException $error) {
    echo json_encode(array('status' => 'rejected', 'message' => $error->getMessage()), JSON_UNESCAPED_SLASHES), PHP_EOL;
}
