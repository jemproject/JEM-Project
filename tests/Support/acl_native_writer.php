<?php

/** CLI-only worker writing exclusively to the parent test's disposable database. */
if (PHP_SAPI !== 'cli') {
    exit(1);
}

use Joomla\CMS\Table\Asset;
use Joomla\Database\DatabaseFactory;
use Joomla\Event\Dispatcher;

$request = json_decode((string) fgets(STDIN), true, 512, JSON_THROW_ON_ERROR);
if (!preg_match('/^jem_issue2358_test_[0-9]+_[a-f0-9]{8}$/D', $request['database'] ?? '')) {
    exit(2);
}

define('_JEXEC', 1);
define('JDEBUG', false);
$root = $request['root'];
require_once $root . '/includes/defines.php';
require_once $root . '/libraries/vendor/autoload.php';
spl_autoload_register(static function (string $class) use ($root): void {
    if (str_starts_with($class, 'Joomla\\CMS\\')) {
        $path = $root . '/libraries/src/' . str_replace('\\', '/', substr($class, 11)) . '.php';
        if (is_file($path)) {
            require_once $path;
        }
    }
});
require_once $root . '/configuration.php';
$config = new JConfig();
$db = (new DatabaseFactory())->getDriver('mysqli', [
    'host' => $config->host,
    'user' => $config->user,
    'password' => $config->password,
    'database' => $request['database'],
    'prefix' => 'test_',
]);
$db->setQuery('SET SESSION innodb_lock_wait_timeout=10')->execute();
$asset = new Asset($db, new Dispatcher());
$asset->name = 'com_content.article.99';
$asset->title = 'Concurrent native Joomla article';
$asset->rules = '{}';
$asset->setLocation(2, 'last-child');
echo "READY\n";
fflush(STDOUT);
if (trim((string) fgets(STDIN)) !== 'GO') {
    exit(3);
}
echo "STARTED\n";
fflush(STDOUT);
if (!$asset->check() || !$asset->store()) {
    exit(4);
}
echo "DONE\n";
