<?php
/** Issue 2490: real pricing/capacity and booking services in a disposable database. */
declare(strict_types=1);

define('_JEXEC', 1);
if ($argc !== 3) {
    throw new RuntimeException('Pass the Joomla library/configuration root and JEM source root.');
}
$joomla = $argv[1];
$source = $argv[2];
define('JPATH_SITE', $joomla);
define('JPATH_ROOT', $joomla);
define('JPATH_BASE', $joomla);
define('JPATH_ADMINISTRATOR', $joomla . '/administrator');
define('JPATH_CACHE', sys_get_temp_dir());
define('JPATH_LANGUAGES', $joomla . '/language');
require $joomla . '/libraries/vendor/autoload.php';
spl_autoload_register(static function (string $class) use ($joomla): void {
    if (str_starts_with($class, 'Joomla\\CMS\\')) {
        $file = $joomla . '/libraries/src/' . str_replace('\\', '/', substr($class, 11)) . '.php';
        if (is_file($file)) { require_once $file; }
    }
});
require $joomla . '/configuration.php';
$config = new JConfig();
$server = new mysqli($config->host, $config->user, $config->password);
$database = 'jem_2490_' . bin2hex(random_bytes(7));
$server->query('CREATE DATABASE ' . $database . ' CHARACTER SET utf8mb4');
$db = null;
$checks = 0;
$results = [];
/** Count an assertion and stop with a precise fixture/scenario error if it fails. */
function check2490(bool $value, string $message): void
{
    global $checks;
    $checks++;
    if (!$value) { throw new RuntimeException($message); }
}
/** Build a trusted test holder with public visibility, not request-provided ACL. */
function holder2490(int $id): object
{
    return new class ($id) {
        public function __construct(public int $id) {}
        public function getAuthorisedViewLevels(): array { return [1]; }
        public function getAuthorisedGroups(): array { return [2]; }
    };
}
try {
    $db = new Joomla\Database\Mysqli\MysqliDriver([
        'host'=>$config->host, 'user'=>$config->user, 'password'=>$config->password,
        'database'=>$database, 'prefix'=>'t_',
    ]);
    $container = new Joomla\DI\Container();
    $container->set('DatabaseDriver', $db, true);
    $container->alias(Joomla\Database\DatabaseInterface::class, 'DatabaseDriver');
    $container->alias(Joomla\Database\DatabaseDriver::class, 'DatabaseDriver');
    (new ReflectionProperty(Joomla\CMS\Factory::class, 'container'))->setValue(null, $container);
    Joomla\CMS\Factory::$language = new Joomla\CMS\Language\Language('en-GB');
    $strings = array_merge(
        parse_ini_file($source . '/site/language/en-GB/com_jem.ini', false, INI_SCANNER_RAW),
        parse_ini_file($source . '/admin/language/en-GB/com_jem.ini', false, INI_SCANNER_RAW)
    );
    (new ReflectionProperty(Joomla\CMS\Factory::$language, 'strings'))->setValue(Joomla\CMS\Factory::$language, $strings);
    Joomla\CMS\Factory::$application = new class {
        public function getIdentity(): object { return holder2490(42); }
        public function getLanguage(): object { return Joomla\CMS\Factory::$language; }
        public function get($key, $default=null): mixed { return $default; }
    };
    $install = file_get_contents($source . '/admin/sql/install.mysql.utf8.sql');
    preg_match_all('/CREATE TABLE IF NOT EXISTS `#__jem_[^`]+`.*?ENGINE=InnoDB;/s', $install, $schemas);
    foreach ($schemas[0] as $schema) { $db->setQuery($schema)->execute(); }
    foreach (['users','usergroups','viewlevels','user_usergroup_map'] as $table) {
        $db->setQuery('CREATE TABLE t_' . $table . ' LIKE ' . $db->quoteName($config->db) . '.' . $db->quoteName($config->dbprefix . $table))->execute();
    }
    $db->setQuery("INSERT INTO #__users (id,name,username,email,password,params,registerDate) VALUES (42,'QA 2490','qa2490','qa2490@example.invalid','','{}','2026-11-08 10:00:00'),(43,'QA 2490 B','qa2490b','qa2490b@example.invalid','','{}','2026-11-08 10:00:00')")->execute();
    $db->setQuery("INSERT INTO #__viewlevels (id,title,rules) VALUES (1,'Public','[1]')")->execute();
    foreach (['defaultCurrency'=>'EUR','defaultCountry'=>'ES','registration_schema_ready'=>'1'] as $key=>$value) {
        $row=(object)['keyname'=>$key,'value'=>$value]; $db->insertObject('#__jem_config',$row);
    }
    foreach (['ES'=>'Spain','FR'=>'France'] as $iso=>$country) {
        $row=(object)['iso2'=>$iso,'iso3'=>$iso==='ES'?'ESP':'FRA','un'=>$iso==='ES'?724:250,'name'=>$country,'continent'=>'EU']; $db->insertObject('#__jem_countries',$row);
    }
    require $source . '/admin/classes/venuecapacity.class.php';
    require $source . '/site/classes/config.class.php';
    require $source . '/site/classes/money.class.php';
    require $source . '/site/classes/pricingcountry.class.php';
    $code=preg_replace('/^require_once .*;\r?\n/m','',file_get_contents($source.'/admin/classes/eventpricingcapacity.class.php'),-1,$count);
    check2490($count===4,'Unexpected source includes');
    eval(substr($code,5));
    require $source . '/site/classes/pricedregistration.class.php';
    require $source . '/site/classes/registrationcancellation.class.php';
    $tax=(object)['code'=>'qa2490-es','name'=>'QA ES zero','tax_type'=>'zero','rate'=>'0.00','country_code'=>'ES','published'=>1];
    $db->insertObject('#__jem_tax_rates',$tax,'id');
    $venue=(object)['venue'=>'QA2490 physical venue','alias'=>'qa2490','capacity'=>50,'country'=>'ES','published'=>1];
    $db->insertObject('#__jem_venues',$venue,'id');
    $configuration=JemVenueCapacityService::normaliseFormData(['spaces'=>[[
        'space_name'=>'Room','space_code'=>'room','layout_name'=>'General','layout_code'=>'general','layout_capacity'=>50,
        'areas'=>[['name'=>'Front','code'=>'front','capacity'=>25,'published'=>1],['name'=>'Back','code'=>'back','capacity'=>25,'published'=>1]],
    ]]],50);
    JemVenueCapacityService::saveDefaultConfiguration((int)$venue->id,$configuration);
    $requirements=JemEventPricingCapacityService::getVenueRequirements((int)$venue->id);
    check2490($requirements['capacity_ready'],'Physical fixture not ready');
    $cases=[];
    foreach (['no_venue','venue_only','profile','custom_areas'] as $layout) {
        foreach (['classic','single','multiple'] as $mode) {
            $cases[] = [$layout,$mode];
        }
    }
    $fixtures=[];
    foreach ($cases as [$layout,$mode]) {
        $key=$layout.'_'.$mode;
        $physical=in_array($layout,['profile','custom_areas'],true);
        $priced=$mode!=='classic';
        $data=[
            'title'=>'QA2490 '.$key,'alias'=>'qa2490-'.$key,'introtext'=>'','fulltext'=>'','metadata'=>'{}','created_by_alias'=>'',
            'dates'=>'2030-01-01','published'=>1,'access'=>1,'locid'=>$layout==='no_venue'?0:(int)$venue->id,
            'online_meeting_url'=>$layout==='no_venue'?'https://example.invalid/meeting':'',
            'venue_allocation_mode'=>$physical?($layout==='profile'?'profile':'custom'):'none',
            'capacity_mode'=>$layout==='custom_areas'?'areas':($physical?'configured':'classic'),
            'pricing_mode'=>$mode,'pricing_country_code'=>'ES','maxplaces'=>50,'reservedplaces'=>0,
            'minbookeduser'=>1,'maxbookeduser'=>10,'registra'=>1,'waitinglist'=>0,'unregistra'=>1,
            'prices_include_tax'=>1,'default_tax_rate_id'=>$priced?(int)$tax->id:null,
        ];
        if ($priced) {
            $data['event_prices']=[['id'=>0,'code'=>'general','name'=>'General','amount'=>'12.50','tax_rate_id'=>(int)$tax->id,'published'=>1]];
            if ($mode==='multiple') {
                $data['event_prices'][]=['id'=>0,'code'=>'reduced','name'=>'Reduced','amount'=>'6.00','tax_rate_id'=>(int)$tax->id,'published'=>1];
            }
            if ($layout==='custom_areas') {
                foreach ($data['event_prices'] as $i=>&$price) { $price['capacity_pool_id']='source:'.$requirements['pool_candidates'][$i]['code']; }
                unset($price);
            }
        }
        $context=JemEventPricingCapacityService::prepareEventData($data,0);
        $event=(object)$data; $db->insertObject('#__jem_events',$event,'id');
        $id=(int)$event->id;
        JemEventPricingCapacityService::saveChildren($id,$context);
        $prices=$db->setQuery('SELECT * FROM #__jem_event_prices WHERE event_id='.$id.' ORDER BY id')->loadAssocList();
        check2490(count($prices)===($mode==='single'?1:($mode==='multiple'?2:0)), $key.': wrong saved prices');
        check2490(($data['venue_snapshot']!==null)===$physical,$key.': wrong snapshot');
        check2490($data['pricing_country_code']===($priced?'ES':''),$key.': wrong country');
        $service=new JemRegistrationService($db);
        if ($priced) {
            $commercial=new JemPricedRegistrationService($db,static fn():string=>'2029-11-08 10:00:00');
            $identity=holder2490(42);
            $quoteContext=JemPricingQuoteContext::fromIdentity($identity,(int)$data['pricing_revision']);
            $selection=[['event_price_id'=>(int)$prices[0]['id'],'quantity'=>2]];
            $quote=$commercial->quote($id,$selection,$quoteContext);
            check2490($quote['grand_total']==='25.00',$key.': quote amount');
            check2490($quote['quantity']===2,$key.': quote quantity');
            $commercialOptions=['actorId'=>42,'source'=>'test.2490'];
            $saved=$commercial->confirm($id,$selection,$quoteContext,$quote['quote_fingerprint'],JemRegistrationIdentity::generateOperationReference(),[],$commercialOptions);
            check2490($saved->after->grand_total==='25.00',$key.': persisted amount');
            $next=$commercial->quote($id,[['event_price_id'=>(int)$prices[0]['id'],'quantity'=>1]],JemPricingQuoteContext::fromIdentity(holder2490(43),(int)$data['pricing_revision']));
            check2490($next['event_used']===2 && $next['event_remaining']===47,$key.': remaining capacity '.json_encode([$next['event_used'],$next['event_remaining']]));
            if (!$physical) { check2490(empty($prices[0]['capacity_pool_id']),$key.': invented physical pool'); }
        } else {
            $options=['actorId'=>42,'source'=>'test.2490'];
            if ($layout==='custom_areas') {
                $capacity=$service->capacityOptions($id);
                $options['capacityAllocations']=[(string)$capacity->options[0]['key']=>2];
            }
            $saved=$service->save((object)['event'=>$id,'uid'=>42,'places'=>2,'status'=>1],$options);
        }
        check2490((int)$saved->after->places===2 && (int)$saved->after->status===1,$key.': reservation');
        $active=(int)$db->setQuery('SELECT COALESCE(SUM(places),0) FROM #__jem_register WHERE event='.$id.' AND status=1 AND waiting=0')->loadResult();
        check2490($active===2,$key.': inventory');
        $fixtures[$key]=['id'=>$id,'data'=>$data,'prices'=>$prices,'registration'=>$saved->after];
        $results[$key]=['event_id'=>$id,'prices'=>count($prices),'places'=>$active,'amount'=>$priced?'25.00':null];
    }
    // Every rejection must precede persistence and preserve the event, prices and registrations.
    foreach (['missing_country','invalid_country','missing_prices','foreign_tax','foreign_pool','remove_booked_allocation','nonpositive_capacity','missing_area'] as $case) {
        $fixture=$fixtures[in_array($case,['remove_booked_allocation','missing_area'],true)?'custom_areas_single':'no_venue_single'];
        $data=$fixture['data']; $data['event_prices']=$fixture['prices'];
        $settings=JemConfig::getInstance()->toRegistry();
        $settings->set('defaultTaxCountry',''); $settings->set('defaultCountry','ES');
        if ($case==='missing_country') {$settings->set('defaultCountry','');}
        if ($case==='invalid_country') {$settings->set('defaultTaxCountry','ZZ');}
        if ($case==='missing_prices') {$data['event_prices']=[];}
        if ($case==='foreign_tax') {$settings->set('defaultTaxCountry','FR');}
        if ($case==='foreign_pool') {$data['event_prices'][0]['capacity_pool_id']=PHP_INT_MAX;}
        if ($case==='remove_booked_allocation') {$data['venue_allocation_mode']='none';}
        if ($case==='nonpositive_capacity') {$data['maxplaces']=0;}
        if ($case==='missing_area') {$data['event_prices'][0]['capacity_pool_id']=null;}
        $before=json_encode($db->setQuery('SELECT * FROM #__jem_events WHERE id='.$fixture['id'])->loadAssoc());
        try {
            JemEventPricingCapacityService::prepareEventData($data,$fixture['id']);
            throw new RuntimeException($case.': expected rejection');
        } catch (InvalidArgumentException $error) {
            check2490($before===json_encode($db->setQuery('SELECT * FROM #__jem_events WHERE id='.$fixture['id'])->loadAssoc()),$case.': event changed');
            check2490($fixture['prices']===$db->setQuery('SELECT * FROM #__jem_event_prices WHERE event_id='.$fixture['id'].' ORDER BY id')->loadAssocList(),$case.': prices changed');
            $results[$case]=['rejected'=>true,'error'=>$error->getMessage()];
        }
    }
    // A title-only pricing save must preserve the same price IDs and amounts.
    JemConfig::getInstance()->toRegistry()->set('defaultTaxCountry','');
    JemConfig::getInstance()->toRegistry()->set('defaultCountry','ES');
    $fixture=$fixtures['no_venue_multiple'];
    $data=$fixture['data'];
    $context=JemEventPricingCapacityService::prepareEventData($data,$fixture['id']);
    JemEventPricingCapacityService::saveChildren($fixture['id'],$context);
    $after=$db->setQuery('SELECT * FROM #__jem_event_prices WHERE event_id='.$fixture['id'].' ORDER BY id')->loadAssocList();
    check2490(array_column($after,'id')===array_column($fixture['prices'],'id'),'Edit changed price IDs');
    check2490(array_column($after,'amount')===['12.50','6.00'],'Edit changed amounts');
    $results['edit_preserves_prices']=['passed'=>true];

    // Global defaults apply to new configuration; a venue country takes priority.
    $settings=JemConfig::getInstance()->toRegistry();
    $settings->set('defaultTaxCountry','FR');
    check2490(JemPricingCountry::forVenue('ES')==='ES','Venue country precedence');
    check2490(JemPricingCountry::forVenue('')==='FR','Dedicated tax-country setting');
    $fixture=$fixtures['no_venue_single'];
    $commercial=new JemPricedRegistrationService($db,static fn():string=>'2029-11-08 10:00:00');
    $existingQuote=$commercial->quote($fixture['id'],[['event_price_id'=>(int)$fixture['prices'][0]['id'],'quantity'=>1]],JemPricingQuoteContext::fromIdentity(holder2490(43),(int)$fixture['data']['pricing_revision']));
    check2490($existingQuote['grand_total']==='12.50','Settings changed saved pricing country');
    $settings->set('defaultTaxCountry','');
    check2490(JemPricingCountry::forVenue('')==='ES','Existing Default Country fallback');
    $results['country_precedence_and_snapshot']=['passed'=>true];

    // Choosing one admission from each physical area produces matching allocations.
    $fixture=$fixtures['custom_areas_multiple'];
    $selection=array_map(static fn(array $row):array=>['event_price_id'=>(int)$row['id'],'quantity'=>1],$fixture['prices']);
    $quoteContext=JemPricingQuoteContext::fromIdentity(holder2490(43),(int)$fixture['data']['pricing_revision']);
    $quote=$commercial->quote($fixture['id'],$selection,$quoteContext);
    check2490($quote['grand_total']==='18.50','Mixed area price total');
    $saved=$commercial->confirm($fixture['id'],$selection,$quoteContext,$quote['quote_fingerprint'],JemRegistrationIdentity::generateOperationReference(),[],['actorId'=>42,'source'=>'test.2490']);
    $allocations=$db->setQuery('SELECT * FROM #__jem_register_capacity_allocations WHERE register_id='.(int)$saved->after->id)->loadAssocList();
    check2490(count($allocations)===2 && array_sum(array_column($allocations,'quantity'))===2,'Commercial/physical area quantities differ');
    $results['mixed_area_booking']=['passed'=>true,'amount'=>$quote['grand_total']];

    // Copy has its own prices, without creating a fictitious venue or capacity pool.
    $fixture=$fixtures['no_venue_multiple'];
    $copy=$fixture['data'];
    $copy['event_prices']=JemEventPricingCapacityService::prepareCopiedPriceRows($fixture['prices'],$fixture['id']);
    $context=JemEventPricingCapacityService::prepareEventData($copy,0);
    $event=(object)$copy;$db->insertObject('#__jem_events',$event,'id');
    JemEventPricingCapacityService::saveChildren((int)$event->id,$context);
    $copied=$db->setQuery('SELECT * FROM #__jem_event_prices WHERE event_id='.(int)$event->id)->loadAssocList();
    check2490(count($copied)===2 && !array_intersect(array_column($copied,'id'),array_column($fixture['prices'],'id')),'Copy reused source prices');
    check2490(array_column($copied,'amount')===['12.50','6.00'],'Copy changed prices');
    $results['copy_without_venue']=['passed'=>true];

    // An unbooked physical event can become general-capacity without losing its tariffs.
    $fixture=$fixtures['custom_areas_single'];
    $data=$fixture['data'];
    $data['event_prices']=JemEventPricingCapacityService::prepareCopiedPriceRows($fixture['prices'],$fixture['id']);
    $context=JemEventPricingCapacityService::prepareEventData($data,0);
    $event=(object)$data;$db->insertObject('#__jem_events',$event,'id');
    JemEventPricingCapacityService::saveChildren((int)$event->id,$context);
    $savedPrices=$db->setQuery('SELECT * FROM #__jem_event_prices WHERE event_id='.(int)$event->id)->loadAssocList();
    $data['event_prices']=$savedPrices;$data['venue_allocation_mode']='none';
    $context=JemEventPricingCapacityService::prepareEventData($data,(int)$event->id);
    $data['id']=(int)$event->id;$event=(object)$data;$db->updateObject('#__jem_events',$event,'id',true);
    JemEventPricingCapacityService::saveChildren((int)$event->id,$context);
    $converted=$db->setQuery('SELECT * FROM #__jem_event_prices WHERE event_id='.(int)$event->id)->loadAssocList();
    check2490(count($converted)===1 && $converted[0]['id']===$savedPrices[0]['id'] && $converted[0]['amount']==='12.50','Removing unbooked allocation lost price');
    check2490($converted[0]['capacity_pool_id']===null,'General-capacity price retained obsolete area');
    $results['remove_unbooked_allocation']=['passed'=>true];

    // Preserve automatic price assignment when exactly one physical area exists.
    $singleConfiguration=JemVenueCapacityService::normaliseFormData(['spaces'=>[[
        'space_name'=>'Single room','space_code'=>'single-room','layout_name'=>'General','layout_code'=>'general','layout_capacity'=>50,
        'areas'=>[['name'=>'Only area','code'=>'only-area','capacity'=>50,'published'=>1]],
    ]]],50);
    $singleVenue=(object)['venue'=>'QA2490 one area','alias'=>'qa2490-one-area','capacity'=>50,'country'=>'ES','published'=>1];
    $db->insertObject('#__jem_venues',$singleVenue,'id');
    JemVenueCapacityService::saveDefaultConfiguration((int)$singleVenue->id,$singleConfiguration);
    $data=$fixtures['no_venue_single']['data'];
    $data['locid']=(int)$singleVenue->id;$data['venue_allocation_mode']='profile';$data['capacity_mode']='areas';
    $data['event_prices']=[['id'=>0,'code'=>'general','name'=>'General','amount'=>'12.50','tax_rate_id'=>(int)$tax->id,'published'=>1]];
    $context=JemEventPricingCapacityService::prepareEventData($data,0);
    $event=(object)$data;$db->insertObject('#__jem_events',$event,'id');
    JemEventPricingCapacityService::saveChildren((int)$event->id,$context);
    $price=$db->setQuery('SELECT * FROM #__jem_event_prices WHERE event_id='.(int)$event->id)->loadAssoc();
    check2490((int)$price['capacity_pool_id']>0,'Single area not assigned automatically');
    $selection=[['event_price_id'=>(int)$price['id'],'quantity'=>2]];
    $quoteContext=JemPricingQuoteContext::fromIdentity(holder2490(43),(int)$data['pricing_revision']);
    $quote=$commercial->quote((int)$event->id,$selection,$quoteContext);
    $saved=$commercial->confirm((int)$event->id,$selection,$quoteContext,$quote['quote_fingerprint'],JemRegistrationIdentity::generateOperationReference(),[],['actorId'=>42,'source'=>'test.2490']);
    $allocated=(int)$db->setQuery('SELECT SUM(quantity) FROM #__jem_register_capacity_allocations WHERE register_id='.(int)$saved->after->id)->loadResult();
    check2490($allocated===2,'Single-area allocation differs from quoted quantity');
    $results['single_area_auto_assignment']=['passed'=>true];
} finally {
    if ($db) { $db->disconnect(); }
    if (preg_match('/^jem_2490_[a-f0-9]{14}$/D',$database)) { $server->query('DROP DATABASE '.$database); }
    $server->close();
}
echo json_encode(['php'=>PHP_VERSION,'checks'=>$checks,'results'=>$results],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),PHP_EOL;
