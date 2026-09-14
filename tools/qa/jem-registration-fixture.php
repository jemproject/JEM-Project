<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\CMS\Session\Session;
use Joomla\Console\Application;
use Joomla\Database\DatabaseInterface;
use Joomla\Session\SessionInterface;

function fail(string $message, int $code = 1): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit($code);
}

function options(array $arguments): array
{
    $result = array();

    foreach ($arguments as $argument) {
        if (preg_match('/^--([^=]+)=(.*)$/', $argument, $matches)) {
            $result[$matches[1]] = $matches[2];
        }
    }

    return $result;
}

$command = $argv[1] ?? '';
$options = options(array_slice($argv, 2));
$siteRoot = rtrim((string) ($options['site'] ?? ''), '\\/');
$userId = (int) ($options['user'] ?? 0);
$eventId = (int) ($options['event'] ?? 0);
$marker = 'JEM QA registration view audit';

if (!in_array($command, array('create', 'cleanup'), true)
    || !is_file($siteRoot . '/configuration.php')
    || $userId < 1
    || $eventId < 1) {
    fail('Usage: php jem-registration-fixture.php create|cleanup --site=PATH --user=ID --event=ID', 64);
}

define('_JEXEC', 1);
define('JPATH_BASE', $siteRoot);
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

$container = Factory::getContainer();
$container->alias('session', 'session.cli')
    ->alias('JSession', 'session.cli')
    ->alias(Session::class, 'session.cli')
    ->alias(\Joomla\Session\Session::class, 'session.cli')
    ->alias(SessionInterface::class, 'session.cli');

$application = $container->get(Application::class);
Factory::$application = $application;
$application->createExtensionNamespaceMap();
$database = $container->get(DatabaseInterface::class);

$query = $database->getQuery(true)
    ->select('*')
    ->from($database->quoteName('#__jem_register'))
    ->where($database->quoteName('event') . ' = ' . $eventId)
    ->where($database->quoteName('uid') . ' = ' . $userId);
$registration = $database->setQuery($query)->loadObject();

if ($registration && (string) $registration->comment !== $marker) {
    fail('The selected user already has a non-QA registration for this event.');
}

if ($command === 'cleanup') {
    if (!$registration) {
        echo "JEM registration fixture was already absent.\n";
        exit(0);
    }

    $registrationId = (int) $registration->id;
    $database->transactionStart();

    try {
        $query = $database->getQuery(true)
            ->select($database->quoteName('id'))
            ->from($database->quoteName('#__jem_notifications'))
            ->where($database->quoteName('registration_id') . ' = ' . $registrationId);
        $notificationIds = array_map('intval', (array) $database->setQuery($query)->loadColumn());

        if ($notificationIds) {
            $query = $database->getQuery(true)
                ->delete($database->quoteName('#__jem_notifications_attempts'))
                ->where($database->quoteName('notification_id') . ' IN (' . implode(',', $notificationIds) . ')');
            $database->setQuery($query)->execute();
        }

        $dependencies = array(
            '#__jem_notifications' => 'registration_id',
            '#__jem_register_history' => 'registration_id',
            '#__jem_register_capacity_allocations' => 'register_id',
            '#__jem_register_items' => 'register_id',
        );

        foreach ($dependencies as $table => $column) {
            $query = $database->getQuery(true)
                ->delete($database->quoteName($table))
                ->where($database->quoteName($column) . ' = ' . $registrationId);
            $database->setQuery($query)->execute();
        }

        $query = $database->getQuery(true)
            ->delete($database->quoteName('#__jem_register'))
            ->where($database->quoteName('id') . ' = ' . $registrationId)
            ->where($database->quoteName('event') . ' = ' . $eventId)
            ->where($database->quoteName('uid') . ' = ' . $userId)
            ->where($database->quoteName('comment') . ' = ' . $database->quote($marker));
        $database->setQuery($query)->execute();
        $database->transactionCommit();
    } catch (Throwable $exception) {
        $database->transactionRollback();
        throw $exception;
    }

    echo "JEM registration fixture removed.\n";
    exit(0);
}

if (!$registration) {
    require_once JPATH_SITE . '/components/com_jem/factory.php';
    $service = new JemRegistrationService($database);
    $result = $service->save(
        (object) array(
            'event' => $eventId,
            'uid' => $userId,
            'places' => 1,
            'waiting' => 0,
            'status' => 1,
            'comment' => $marker,
            'uip' => '127.0.0.1',
        ),
        array(
            'actorId' => $userId,
            'source' => 'qa.view_audit',
            'reasonCode' => 'view_audit_fixture',
            'requireNew' => true,
        )
    );
    $registration = $result->after;
}

$registrationId = (int) $registration->id;
$query = $database->getQuery(true)
    ->select($database->quoteName('id'))
    ->from($database->quoteName('#__jem_register_history'))
    ->where($database->quoteName('registration_id') . ' = ' . $registrationId)
    ->order($database->quoteName('revision') . ' DESC, ' . $database->quoteName('id') . ' DESC');
$historyId = (int) $database->setQuery($query, 0, 1)->loadResult();

if ($historyId < 1) {
    fail('The QA registration history row was not created.');
}

echo json_encode(
    array(
        'registration_id' => $registrationId,
        'registration_event_id' => $eventId,
        'registration_history_id' => $historyId,
    ),
    JSON_UNESCAPED_SLASHES
) . PHP_EOL;
