<?php
/** Execute current uninstall callbacks on disposable menu trees; never change a Joomla site. */
declare(strict_types=1);
define('_JEXEC', 1);
if (getenv('JEM_TEST_MENU_DATABASES') !== '1') {
    throw new RuntimeException('Set JEM_TEST_MENU_DATABASES=1 to create disposable test databases.');
}
$root = $argv[1];
$source = $argv[2];
$mode = $argv[3];
define('JPATH_ROOT', $root);
define('JPATH_ADMINISTRATOR', $root . '/administrator');
define('JPATH_SITE', __DIR__ . '/issue-2472-no-site');
if (is_dir(JPATH_SITE)) {
    throw new RuntimeException('The disposable site path must not exist.');
}
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
$server = new mysqli($config->host, $config->user, $config->password);
$database = 'jem_menu_coverage_' . bin2hex(random_bytes(8));
$server->query('CREATE DATABASE ' . $database . ' CHARACTER SET utf8mb4');
$db = null;
try {
    $db = new Joomla\Database\Mysqli\MysqliDriver([
        'host' => $config->host, 'user' => $config->user, 'password' => $config->password,
        'database' => $database, 'prefix' => 't_',
    ]);
    $container = new Joomla\DI\Container();
    $container->set('DatabaseDriver', $db, true);
    (new ReflectionProperty(Joomla\CMS\Factory::class, 'container'))->setValue(null, $container);
    Joomla\CMS\Factory::$language = new class {
        public function load(...$args): bool { return true; }
        public function _($key, ...$args): string { return $key; }
    };
    Joomla\CMS\Factory::$application = new class {
        public function getLanguage(): object { return Joomla\CMS\Factory::$language; }
        public function getDispatcher(): object { return new Joomla\Event\Dispatcher(); }
        public function get($key, $default = null) { return $key === 'live_site' ? 'http://localhost/joomla/' : $default; }
    };
    $execute = static function (string $sql) use ($db): void { $db->setQuery($sql)->execute(); };
    $execute('CREATE TABLE t_menu LIKE ' . $db->quoteName($config->db) . '.' . $db->quoteName($config->dbprefix . 'menu'));
    $execute('CREATE TABLE t_jem_config(keyname VARCHAR(255) PRIMARY KEY,value MEDIUMTEXT) ENGINE=InnoDB');
    $execute("INSERT INTO t_jem_config VALUES('globalattribs'," . $db->quote(json_encode([
        'global_cleanup_db_on_uninstall' => (int) ($mode === 'full'),
        'global_cleanup_images_on_uninstall' => 0,
    ])) . ')');
    $execute('CREATE TABLE t_extensions(extension_id INT,type VARCHAR(32),element VARCHAR(64)) ENGINE=InnoDB');
    $execute("INSERT INTO t_extensions VALUES(900,'component','com_jem')");
    $execute('CREATE TABLE t_modules_menu(moduleid INT,menuid INT) ENGINE=InnoDB');
    $execute('INSERT INTO t_modules_menu VALUES(1,21),(1,-21),(1,25),(1,0)');
    $execute('CREATE TABLE t_associations(id INT,context VARCHAR(64)) ENGINE=InnoDB');
    $execute("INSERT INTO t_associations VALUES(21,'com_menus.item'),(21,'com_content.item'),(25,'com_menus.item')");
    // id, parent, title, type, link, publication, access, component, menu type, params.
    $rows = [
        [1,0,'ROOT','heading','',1,1,0,'mainmenu','{}'],
        [10,1,'JEM generated root','heading','#',1,1,0,'jem-frontend-menu','{}'],
        [11,10,'JEM generated group','url','#',1,1,0,'jem-frontend-menu','{}'],
        [12,11,'JEM view layout heading','heading','#',1,1,0,'jem-frontend-menu','{}'],
        [13,12,'Generated Events List','component','index.php?option=com_jem&view=eventslist',1,1,200,'jem-frontend-menu','{}'],
        [14,11,'Generated Calendar Registered','component','index.php?option=com_jem&view=calendar',1,2,200,'jem-frontend-menu','{}'],
        [15,10,'JEM module heading','heading','#',1,1,0,'jem-frontend-menu','{}'],
        [16,15,'JEM module layout','component','index.php?option=com_jem&view=modulepreview&module_id=5',1,1,200,'jem-frontend-menu','{}'],
        [17,10,'Administrator shortcut','url','http://localhost/administrator/index.php',1,3,0,'jem-frontend-menu','{}'],
        [20,1,'Custom menu group','heading','#',1,1,0,'mainmenu','{}'],
        [21,20,'Custom JEM event','component','index.php?option=com_jem&view=event&id=4',1,1,200,'mainmenu','{}'],
        [22,20,'Custom JEM URL','url','index.php?option=com_jem&view=search',1,1,0,'mainmenu','{}'],
        [23,20,'JEM reordered query','component','index.php?view=eventslist&option=com_jem',1,1,200,'mainmenu','{}'],
        [24,20,'Alias to JEM','alias','index.php?Itemid=21',1,1,0,'mainmenu','{"aliasoptions":21}'],
        [25,20,'Unrelated article','component','index.php?option=com_content&view=article&id=1',1,1,300,'mainmenu','{}'],
        [26,20,'Unpublished JEM','component','index.php?option=com_jem&view=day',0,1,200,'mainmenu','{}'],
        [27,20,'Trashed JEM','component','index.php?option=com_jem&view=venues',-2,1,200,'mainmenu','{}'],
        [28,20,'Special JEM','component','index.php?option=com_jem&view=editevent',1,3,200,'mainmenu','{}'],
        [29,20,'Different com_jem_other','component','index.php?option=com_jem_other&view=list',1,1,301,'mainmenu','{}'],
        [30,20,'Different comXjem','component','index.php?option=comXjem&view=list',1,1,302,'mainmenu','{}'],
        [31,20,'Absolute local JEM URL','url','http://localhost/joomla/index.php?option=com_jem&view=eventslist',1,1,0,'mainmenu','{}'],
        [32,20,'Different Joomla site URL','url','http://localhost/another-site/index.php?option=com_jem',1,1,0,'mainmenu','{}'],
        [33,21,'Foreign child below JEM link','component','index.php?option=com_content&view=article&id=2',1,1,300,'mainmenu','{}'],
        [40,1,'Marked generated root','heading','#',1,1,0,'custom-demo-menu','{"jem_generated_menu":1}'],
        [41,40,'Marked group JEM child','component','index.php?option=com_jem&view=day',1,1,200,'custom-demo-menu','{}'],
        [42,20,'Alias to generated root','alias','index.php?Itemid=40',1,1,0,'mainmenu','{"aliasoptions":40}'],
        [43,20,'Alias chain to JEM','alias','index.php?Itemid=24',1,1,0,'mainmenu','{"aliasoptions":24}'],
        [44,20,'Itemid chain to JEM','url','index.php?Itemid=43',1,1,0,'mainmenu','{}'],
        [45,20,'HTML query separators','component','index.php?view=day&amp;option=com_jem',1,1,200,'mainmenu','{}'],
        [46,20,'Backend JEM shortcut','url','http://localhost/joomla/administrator/index.php?option=com_jem',1,3,0,'mainmenu','{}'],
        [47,20,'Alias cycle A','alias','index.php?Itemid=48',1,1,0,'mainmenu','{"aliasoptions":48}'],
        [48,20,'Alias cycle B','alias','index.php?Itemid=47',1,1,0,'mainmenu','{"aliasoptions":47}'],
    ];
    $nodes = [];
    foreach ($rows as $row) {
        [$id,$parent,$title,$type,$link,$published,$access,$component,$menutype,$params] = $row;
        $nodes[$id] = (object) [
            'id'=>$id,'parent_id'=>$parent,'title'=>$title,'type'=>$type,'link'=>$link,
            'published'=>$published,'access'=>$access,'component_id'=>$component,
            'menutype'=>$menutype,'params'=>$params,'client_id'=>0,'language'=>'*',
            'alias'=>'case-' . $id,'path'=>'case-' . $id,
        ];
    }
    foreach ([1=>'root',10=>'jem-frontend',11=>'events',12=>'view-events-eventslist',15=>'module-mod-jem'] as $id=>$alias) {
        $nodes[$id]->alias = $alias;
    }
    $coordinate = 0;
    $walk = static function (int $id, int $level) use (&$walk, &$nodes, &$coordinate): void {
        $nodes[$id]->lft = $coordinate++;
        $nodes[$id]->level = $level;
        foreach ($nodes as $child) {
            if ($child->parent_id === $id) { $walk($child->id, $level + 1); }
        }
        $nodes[$id]->rgt = $coordinate++;
    };
    $walk(1, 0);
    $menuColumns = $db->getTableColumns('t_menu');
    foreach ($nodes as $node) {
        if (array_key_exists('img', $menuColumns)) { $node->img = ''; }
        $db->insertObject('t_menu', $node);
    }
    $read = static fn() => $db->setQuery('SELECT id,parent_id,title,type,link,published,access,component_id,lft,rgt,path FROM t_menu ORDER BY id')->loadAssocList();
    $before = $read();
    require $source;
    $installer = new com_jemInstallerScript();
    ob_start();
    try { $installer->uninstall(null); } finally { ob_end_clean(); }
    $after = $read();
    (new ReflectionMethod($installer, 'fixJemMenuItems'))->invoke($installer);
    $afterRepair = $read();
    $orphans = $db->setQuery('SELECT c.id FROM t_menu c LEFT JOIN t_menu p ON p.id=c.parent_id WHERE c.parent_id<>0 AND p.id IS NULL')->loadColumn();
    echo json_encode([
        'joomla_root'=>$root,'source'=>$source,'mode'=>$mode,
        'before'=>$before,'after_uninstall'=>$after,'after_reinstall_repair'=>$afterRepair,
        'orphan_menu_ids'=>$orphans,
        'module_assignments'=>$db->setQuery('SELECT * FROM t_modules_menu ORDER BY menuid')->loadAssocList(),
        'associations'=>$db->setQuery('SELECT * FROM t_associations ORDER BY id,context')->loadAssocList(),
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
} finally {
    $db?->disconnect();
    if (preg_match('/^jem_menu_coverage_[a-f0-9]{16}$/D', $database)) {
        $server->query('DROP DATABASE ' . $database);
    }
    $server->close();
}
