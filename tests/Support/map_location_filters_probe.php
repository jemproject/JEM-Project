<?php
/** Native Joomla input/query probe. Disposable database; no installed site data changes. */
declare(strict_types=1);
define('_JEXEC', 1);
[$script, $site, $source] = $argv;
$base = sys_get_temp_dir() . '/jem-map2511-' . bin2hex(random_bytes(6));
mkdir($base . '/components/com_jem/helpers', 0777, true);
mkdir($base . '/components/com_jem/classes', 0777, true);
define('JPATH_SITE', $base);
require $site . '/libraries/vendor/autoload.php';
spl_autoload_register(static function (string $class) use ($site): void {
    if (str_starts_with($class, 'Joomla\\CMS\\')) {
        $path = $site . '/libraries/src/' . str_replace('\\', '/', substr($class, 11)) . '.php';
        if (is_file($path)) { require_once $path; }
    }
});
require $site . '/configuration.php';
$config = new JConfig();
if (!in_array($config->host, ['localhost','127.0.0.1'], true)) { throw new RuntimeException('Local only'); }
$server = new mysqli($config->host, $config->user, $config->password);
$database = 'jem_2511_' . bin2hex(random_bytes(7));
$server->query('CREATE DATABASE ' . $database . ' CHARACTER SET utf8mb4');
$checks=0;
$check=static function(bool $ok,string $message) use (&$checks): void { if(!$ok){throw new RuntimeException($message);} ++$checks; };
try {
    $db = new Joomla\Database\Mysqli\MysqliDriver(['host'=>$config->host,'user'=>$config->user,'password'=>$config->password,'database'=>$database,'prefix'=>'t_']);
    $container = new Joomla\DI\Container();
    $container->set('DatabaseDriver',$db,true);
    $container->alias(Joomla\Database\DatabaseInterface::class,'DatabaseDriver');
    (new ReflectionProperty(Joomla\CMS\Factory::class,'container'))->setValue(null,$container);
    Joomla\CMS\Factory::$application = new class {
        public function getIdentity(): object { return new class { public function getAuthorisedViewLevels(): array {return [1];} }; }
    };
    $modern = str_contains(file_get_contents($source.'/site/helpers/helper.php'),'function getVisibleVenueHierarchyIds(');
    $method='';
    if($modern){
        preg_match('/^    static public function getVisibleVenueHierarchyIds\([^\n]*\n.*?^    }/ms',file_get_contents($source.'/site/helpers/helper.php'),$m);
        $method=$m[0];
    }
    eval('use Joomla\\CMS\\Factory; class JemHelper {public static function config(): object {return (object)["access_level_locked_venues"=>"[1]"];}' . $method . '}');
    file_put_contents($base.'/components/com_jem/helpers/helper.php', '<?php // Helper already loaded by fixture.');
    copy($source.'/site/classes/filterinput.class.php',$base.'/components/com_jem/classes/filterinput.class.php');
    require $source.'/site/helpers/map.php';
    $check(class_exists('JemFilterInput',false),'Reader loads when JemHelper was already loaded');
    $db->setQuery('CREATE TABLE t_jem_venues (id INT PRIMARY KEY,parent_venue_id INT,published INT,access INT,region VARCHAR(80),state VARCHAR(80),city VARCHAR(80),country CHAR(2),latitude VARCHAR(20),longitude VARCHAR(20))')->execute();
    $db->setQuery("INSERT INTO t_jem_venues VALUES (1,0,1,1,'Madrid','Madrid','Madrid','ES','40','-3'),(2,0,1,1,'Catalonia','Barcelona','Barcelona','ES','41','2'),(3,0,0,1,'Hidden','Hidden','Unpublished','ES','40','1'),(4,0,1,3,'Private','Private','Private','ES','40','1'),(5,0,1,1,'Empty','Empty','No coordinates','ES','',''),(6,0,1,1,'Berlin','Berlin','Berlin','DE','52','13'),(7,3,1,1,'Child','Child','Hidden parent child','ES','40','1')")->execute();
    $helper=Joomla\Component\Jem\Site\Helper\JemMapHelper::class;
    $cities=array_column($helper::getEventLocations('ES'),'city');sort($cities);
    $expected=$modern?['Barcelona','Madrid']:['Barcelona','Hidden parent child','Madrid'];
    $check($cities===$expected,'Published/access/coordinates/country/hierarchy query');
    $check(array_column($helper::getEventLocations('DE'),'city')===['Berlin'],'Country query');
    $check($helper::getEventLocations('ZZ')===[],'Unknown country remains restriction');
    foreach(['county','city',...($modern?['region']:[])] as $field){
        foreach([['bad'],['nested'=>['bad']]] as $value){
            $params=new Joomla\Registry\Registry(['show_'.$field.'_filter'=>1,'default_'.$field=>'Madrid']);
            $r=$helper::getEventLocationFilters($params,new Joomla\Input\Input(['jem_map_filter_'.$field=>$value]),'ES');
            $check($r[$field]==='Madrid','Native array fallback '.$field);
        }
    }
    $params=new Joomla\Registry\Registry(['show_county_filter'=>1,'show_city_filter'=>1]);
    $r=$helper::getEventLocationFilters($params,new Joomla\Input\Input(['jem_map_filter_county'=>'Madrid']),'ES');
    $check(array_values($r['city_options'])===['Madrid'],'County narrows cities');
    $r=$helper::getEventLocationFilters(new Joomla\Registry\Registry(['show_city_filter'=>0,'default_city'=>'Madrid']),new Joomla\Input\Input(['jem_map_filter_city'=>'Barcelona']),'ES');
    $check($r['city']==='Madrid','Hidden default cannot be overridden');
    $r=$helper::getEventLocationFilters($params,new Joomla\Input\Input(['jem_map_filter_city'=>'Nergensdorp']),'ES');
    $check($r['city']==='Nergensdorp','Unknown city not widened');
    if($modern){
        $db->setQuery('CREATE TABLE t_jem_countries (iso2 CHAR(2),published INT)')->execute();
        $db->setQuery("INSERT INTO t_jem_countries VALUES ('ES',1),('DE',1)")->execute();
        $r=$helper::getVenueMapFilters(new Joomla\Registry\Registry(['show_country_filter'=>1,'default_country'=>'ES']),new Joomla\Input\Input(['jem_map_filter_country'=>['DE']]));
        $check($r['country']==='ES','Native Venues Map country fallback');
    }
    $code=file_get_contents($source.'/modules/mod_jem_map/mod_jem_map.php');
    $start=strpos($code,'$selectedCountry = $showCountryFilter');$code=substr($code,$start,strpos($code,';',$start)-$start+1);
    $showCountryFilter=true;$defaultCountry='ES';$app=(object)['input'=>new Joomla\Input\Input(['jem_map_filter_country'=>['DE']])];
    eval($code);$check($selectedCountry==='ES','Native module country fallback');
    echo json_encode(['result'=>'PASS','checks'=>$checks,'hierarchy'=>$modern,'database'=>'isolated','php'=>PHP_VERSION]),"\n";
} finally {
    if(isset($db)){$db->disconnect();}
    $server->query('DROP DATABASE '.$database);$server->close();
    unlink($base.'/components/com_jem/classes/filterinput.class.php');unlink($base.'/components/com_jem/helpers/helper.php');
    foreach(['/components/com_jem/classes','/components/com_jem/helpers','/components/com_jem','/components',''] as $dir){rmdir($base.$dir);}
}
