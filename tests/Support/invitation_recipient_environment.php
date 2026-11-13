<?php
/** Recipient fixtures using Joomla User/Access and isolated SQLite, without the installed site. */
declare(strict_types=1);

use Joomla\CMS\Factory;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\Registry\Registry;

$db = Factory::getContainer()->get('DatabaseDriver');
$db->setQuery('CREATE TABLE #__viewlevels (id INTEGER PRIMARY KEY,rules TEXT)')->execute();
$db->setQuery("INSERT INTO #__viewlevels VALUES (1,'[1]'),(2,'[2]'),(3,'[3]')")->execute();
$db->setQuery('CREATE TABLE #__usergroups (id INTEGER PRIMARY KEY,title TEXT,lft INTEGER,rgt INTEGER)')->execute();
$db->setQuery("INSERT INTO #__usergroups VALUES (1,'Public',1,6),(2,'Registered',2,3),(3,'Special',4,5)")->execute();
$db->setQuery('CREATE TABLE #__user_usergroup_map (user_id INTEGER,group_id INTEGER)')->execute();
$db->setQuery("INSERT INTO #__users VALUES (43,'Ana','ana@example.test',0,''),(44,'Special member','special@example.test',0,'')")->execute();
$db->setQuery('INSERT INTO #__user_usergroup_map VALUES (42,3),(43,2),(44,3)')->execute();
$db->setQuery('CREATE TABLE #__jem_categories (id INTEGER PRIMARY KEY,published INTEGER,access INTEGER)')->execute();
$db->setQuery('CREATE TABLE #__jem_cats_event_relations (catid INTEGER,itemid INTEGER)')->execute();
$db->setQuery('INSERT INTO #__jem_categories VALUES (1,1,1),(2,1,3)')->execute();
$db->setQuery('INSERT INTO #__jem_cats_event_relations VALUES (1,7)')->execute();
$db->setQuery('CREATE TABLE #__jem_types (id INTEGER PRIMARY KEY,entity INTEGER,published INTEGER,access INTEGER)')->execute();
$db->setQuery('INSERT INTO #__jem_types VALUES (1,1,1,1),(2,1,1,3)')->execute();
$db->setQuery('CREATE TABLE #__jem_venues (id INTEGER PRIMARY KEY,published INTEGER,access INTEGER,parent_venue_id INTEGER,created_by INTEGER)')->execute();
$db->setQuery('INSERT INTO #__jem_venues VALUES (1,1,1,0,42),(2,1,3,0,42)')->execute();
// Core Access reads com_users only to find the guest group, not installed extension data.
(new ReflectionProperty(ComponentHelper::class, 'components'))->setValue(null, [
    'com_users' => new Joomla\CMS\Component\ComponentRecord(['enabled' => 1, 'params' => new Registry(['guest_usergroup' => 1])]),
]);
Factory::getContainer()->set(UserFactoryInterface::class, new class ($db) implements UserFactoryInterface {
    /** @param object $db Isolated connection. */
    public function __construct(private readonly object $db) {}
    /** @param int $id Recipient. @return User Core Joomla user using real view-level resolution. */
    public function loadUserById(int $id): User
    {
        $row = $this->db->setQuery('SELECT * FROM #__users WHERE id=' . $id)->loadObject();
        $user = new User();
        foreach ((array) $row as $key => $value) {
            $user->$key = $value;
        }
        return $user;
    }
    /** @param string $username Unused in these tests. @return User Empty identity. */
    public function loadUserByUsername(string $username): User { return new User(); }
});
Factory::$application = new class {
    /** @return mixed Stable site settings, no installed configuration. */
    public function get($key, $default = null) { return $default; }
    /** @return User Deliberately more privileged than the recipient. */
    public function getIdentity() { return Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById(42); }
};
