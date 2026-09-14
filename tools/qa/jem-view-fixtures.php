<?php

declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\CMS\Session\Session;
use Joomla\Console\Application;
use Joomla\Database\DatabaseInterface;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\Path;
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

if (!in_array($command, array('create', 'cleanup'), true)
    || !is_file($siteRoot . '/configuration.php')
    || $userId < 1
    || $eventId < 1) {
    fail('Usage: php jem-view-fixtures.php create|cleanup --site=PATH --user=ID --event=ID', 64);
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
$object = 'event' . $eventId;
$filename = 'jem-qa-view-audit.txt';
$query = $database->getQuery(true)
    ->select($database->quoteName('value'))
    ->from($database->quoteName('#__jem_config'))
    ->where($database->quoteName('keyname') . ' = ' . $database->quote('attachments_path'));
$relativeBase = trim((string) $database->setQuery($query)->loadResult(), '\\/');
$basePath = Path::clean(JPATH_SITE . '/' . $relativeBase);
$directory = Path::clean($basePath . '/' . $object);
$filePath = Path::clean($directory . '/' . $filename);
$baseCheck = rtrim(strtolower($basePath), '\\/') . DIRECTORY_SEPARATOR;

if ($relativeBase === '' || strpos(strtolower($filePath), $baseCheck) !== 0) {
    fail('The configured attachment path is unsafe.');
}

$query = $database->getQuery(true)
    ->select($database->quoteName('id'))
    ->from($database->quoteName('#__jem_attachments'))
    ->where($database->quoteName('object') . ' = ' . $database->quote($object))
    ->where($database->quoteName('file') . ' = ' . $database->quote($filename))
    ->where($database->quoteName('created_by') . ' = ' . $userId);
$attachmentId = (int) $database->setQuery($query)->loadResult();

if ($command === 'cleanup') {
    if ($attachmentId > 0) {
        $query = $database->getQuery(true)
            ->delete($database->quoteName('#__jem_attachments'))
            ->where($database->quoteName('id') . ' = ' . $attachmentId)
            ->where($database->quoteName('created_by') . ' = ' . $userId);
        $database->setQuery($query)->execute();
    }

    if (is_file($filePath)) {
        File::delete($filePath);
    }

    echo "JEM view fixture removed.\n";
    exit(0);
}

if (!Folder::exists($directory) && !Folder::create($directory)) {
    fail('Could not create the attachment fixture directory.');
}

if (!File::write($filePath, "JEM final release view audit fixture.\n")) {
    fail('Could not create the attachment fixture file.');
}

if ($attachmentId < 1) {
    $columns = array('object', 'file', 'name', 'description', 'frontend', 'access', 'created', 'created_by');
    $values = array(
        $database->quote($object),
        $database->quote($filename),
        $database->quote('JEM QA view audit'),
        $database->quote('Temporary attachment used by the final release view audit.'),
        0,
        1,
        $database->quote(Factory::getDate()->toSql()),
        $userId,
    );
    $query = $database->getQuery(true)
        ->insert($database->quoteName('#__jem_attachments'))
        ->columns($database->quoteName($columns))
        ->values(implode(', ', $values));
    $database->setQuery($query)->execute();
    $attachmentId = (int) $database->insertid();
}

echo json_encode(array('attachment_id' => $attachmentId), JSON_UNESCAPED_SLASHES) . PHP_EOL;
