<?php
/** Isolated SQL integration. Notification dispatch is captured, never sent externally. */
declare(strict_types=1);
define('_JEXEC', 1);
define('JPATH_CACHE', sys_get_temp_dir());
define('JPATH_COMPONENT_SITE', dirname(__DIR__, 2) . '/site');
$source = dirname(__DIR__, 2);
$joomla = $argv[1] ?? 'C:/xampp/htdocs/jl602';
define('JPATH_SITE', $joomla);
define('JPATH_ROOT', $joomla);
define('JPATH_BASE', $joomla);
define('JPATH_ADMINISTRATOR', $joomla . '/administrator');
require $joomla . '/libraries/vendor/autoload.php';
spl_autoload_register(static function (string $class) use ($joomla): void {
    if (str_starts_with($class, 'Joomla\\CMS\\')) {
        $file = $joomla . '/libraries/src/' . str_replace('\\', '/', substr($class, 11)) . '.php';
        if (is_file($file)) { require_once $file; }
    }
});
class InvitationProbePlugins { public static function importPlugin($group): void {} }
class_alias(InvitationProbePlugins::class, 'Joomla\\CMS\\Plugin\\PluginHelper');
class JemFactory {
    public static array $mail = [];
    public static bool $rejectMail = false;
    public static function getDispatcher(): object {
        return new class {
            public function triggerEvent($name, $arguments): array {
                JemFactory::$mail[] = $arguments;
                return [!JemFactory::$rejectMail];
            }
        };
    }
}
class JemHelper {
    public static function addLogEntry(...$arguments): void {}
    public static function config(): object { return (object) []; }
    public static function getEventRegistrationWindowState($event): string {
        return JemRegistrationAccessPolicy::registrationWindowState((int)$event->registra,
            $event->dates ? strtotime($event->dates.' '.($event->times ?: '00:00:00').' UTC') : 0,
            (int)strtotime($event->registra_from??''), (int)strtotime($event->registra_until??''), time());
    }
}
require $joomla . '/configuration.php';
require_once $source . '/site/classes/invitationservice.class.php';
require_once $source . '/site/classes/invitationschema.class.php';
require_once $source . '/site/classes/ticketservice.class.php';
$config = new JConfig();
$server = new mysqli($config->host, $config->user, $config->password);
$database = 'jem_invite_' . bin2hex(random_bytes(7));
$server->query('CREATE DATABASE ' . $database . ' CHARACTER SET utf8mb4');
$checks = 0;
$check = static function (bool $yes, string $name) use (&$checks): void {
    ++$checks;
    if (!$yes) { throw new RuntimeException($name); }
};
$reject = static function (callable $call, string $key) use ($check): void {
    try { $call(); throw new RuntimeException('Expected rejection: ' . $key); }
    catch (DomainException $error) { $check($error->getMessage() === $key, 'Wrong rejection: ' . $error->getMessage()); }
};
try {
    $db = new Joomla\Database\Mysqli\MysqliDriver(['host' => $config->host, 'user' => $config->user,
        'password' => $config->password, 'database' => $database, 'prefix' => 't_']);
    preg_match_all('/CREATE TABLE IF NOT EXISTS `#__jem_[^`]+`.*?ENGINE=InnoDB;/s', file_get_contents($source . '/admin/sql/install.mysql.utf8.sql'), $schemas);
    foreach ($schemas[0] as $schema) { $db->setQuery($schema)->execute(); }
    $db->setQuery('CREATE TABLE t_users (id INT PRIMARY KEY,name VARCHAR(100),email VARCHAR(254),block INT DEFAULT 0,activation VARCHAR(100) DEFAULT NULL)')->execute();
    $db->setQuery('CREATE TABLE t_user_usergroup_map (user_id INT,group_id INT)')->execute();
    $db->setQuery("INSERT INTO t_users VALUES (42,'Operator','operator@example.invalid',0,NULL),(43,'Ana','ana@example.invalid',0,NULL),(44,'Luis','luis@example.invalid',0,NULL),(45,'Blocked','blocked@example.invalid',1,NULL)")->execute();
    $db->setQuery('INSERT INTO t_user_usergroup_map VALUES (43,2),(43,3),(44,3),(45,3)')->execute();
    $db->setQuery("INSERT INTO #__jem_config(keyname,value) VALUES ('registration_schema_ready','1')")->execute();
    $container = new Joomla\DI\Container();
    $container->set('DatabaseDriver', $db, true);
    $container->alias(Joomla\Database\DatabaseInterface::class, 'DatabaseDriver');
    $container->set(Joomla\CMS\User\UserFactoryInterface::class, new class($db) {
        public function __construct(private object $db) {}
        public function loadUserById($id): object {
            $row = $this->db->setQuery('SELECT * FROM #__users WHERE id=' . (int) $id)->loadObject();
            return new class($row) {
                public int $id; public int $block; public string $name; public string $email;
                public function __construct($row) {
                    $this->id=(int)($row->id??0); $this->block=(int)($row->block??0);
                    $this->name=$row->name??''; $this->email=$row->email??'';
                }
                public function getAuthorisedViewLevels(): array { return [1]; }
                public function getAuthorisedGroups(): array { return [1,2]; }
                public function authorise($action,$asset): bool { return $this->id===42; }
            };
        }
    });
    (new ReflectionProperty(Joomla\CMS\Factory::class,'container'))->setValue(null,$container);
    Joomla\CMS\Factory::$language = new Joomla\CMS\Language\Language('en-GB');
    Joomla\CMS\Factory::$application = new class {
        public function get($key,$default=null) { return $default; }
        public function getLanguage() { return Joomla\CMS\Factory::$language; }
    };
    $operator = $container->get(Joomla\CMS\User\UserFactoryInterface::class)->loadUserById(42);
    $freshColumns=$db->getTableColumns('#__jem_register',false);
    $db->setQuery('ALTER TABLE #__jem_register DROP INDEX idx_register_guest,DROP INDEX idx_invitation_key,DROP INDEX idx_invitation_token')->execute();
    foreach (array_keys(JemInvitationSchema::columns()) as $column) {
        $db->setQuery('ALTER TABLE #__jem_register DROP COLUMN '.$db->quoteName($column))->execute();
    }
    $db->setQuery('ALTER TABLE #__jem_register MODIFY uid INT(11) NOT NULL DEFAULT 0')->execute();
    $db->setQuery('ALTER TABLE #__jem_register_history DROP COLUMN invitation_snapshot')->execute();
    JemInvitationSchema::ensure($db);
    JemInvitationSchema::ensure($db);
    $migratedColumns=$db->getTableColumns('#__jem_register',false);
    foreach (array_merge(['uid'],array_keys(JemInvitationSchema::columns())) as $column) {
        foreach (['Type','Null','Default'] as $attribute) {
            if ($migratedColumns[$column]->$attribute !== $freshColumns[$column]->$attribute) {
                throw new RuntimeException('Fresh/update schema difference: '.$column.'.'.$attribute);
            }
        }
    }
    $check(true,'Existing 5.1 beta migration has the same invitation columns as a clean installation');
    $check(isset($db->getTableColumns('#__jem_register')['invitation_key']), 'Idempotent installer schema');
    foreach ([1,2,3,4,5] as $id) {
        $event=(object)['id'=>$id,'title'=>'Invitation QA '.$id,'published'=>1,'event_status'=>'scheduled',
            'dates'=>'2099-11-'.(10+$id),'times'=>'10:00:00','introtext'=>'','fulltext'=>'','created_by_alias'=>'','metadata'=>'{}',
            'registra'=>1,'access'=>1,'maxbookeduser'=>10,'maxplaces'=>10,'reservedplaces'=>4,'recurrence_first_id'=>$id===2?1:0];
        $db->insertObject('#__jem_events',$event);
    }
    $service=new JemInvitationService($db);
    $input=['event_id'=>1,'scope'=>'single','audience'=>'users','users'=>[],'external'=>'Ana Guest; guest@example.invalid',
        'kind'=>'reserved','quantity'=>2,'free'=>1,'expires'=>'','message'=>'Welcome <script>unsafe</script>'];
    $preview=$service->compose($input,$operator);
    $check($preview['places']===2 && $preview['recipients']===1,'Preview counts');
    $check((int)$db->setQuery('SELECT COUNT(*) FROM #__jem_register')->loadResult()===0,'Preview persists no reservations');
    $send=$input+['fingerprint'=>$preview['fingerprint']];
    $report=$service->compose($send,$operator,true);
    $mail=end(JemFactory::$mail); $token=$mail[1];
    $row=$service->resolve($token)[0];
    $check($row->uid===null && $row->guest_email==='guest@example.invalid','External identity without Joomla account');
    $check((int)$row->reserved_assigned===2,'Pending guaranteed holds');
    $check((int)$db->setQuery('SELECT reservedplaces FROM #__jem_events WHERE id=1')->loadResult()===4,'Invitation does not grow organiser quota');
    $other=array_replace($input,['external'=>'Second Guest; other@example.invalid']);
    $preview=$service->compose($other,$operator);
    $service->compose($other+['fingerprint'=>$preview['fingerprint']],$operator,true);
    $check((int)$db->setQuery('SELECT COUNT(*) FROM #__jem_register WHERE uid IS NULL AND event=1')->loadResult()===2,'Two external holders in same event');
    $third=array_replace($input,['external'=>'Third Guest; third@example.invalid']);
    $reject(fn()=>$service->compose($third,$operator),'COM_JEM_INV_NOT_ENOUGH_RESERVED');
    $confirmed=$service->respond($token,true)[0];
    $check((int)$confirmed->reserved_confirmed===2 && (int)$confirmed->reserved_assigned===0,'Accept transfers origin counters');
    $check((int)$db->setQuery('SELECT reservedplaces FROM #__jem_events WHERE id=1')->loadResult()===2,'Accept transfers pool once');
    $reject(fn()=>$service->respond($token,true),'COM_JEM_INV_INVALID_LINK');
    $ticket=(new JemTicketService($db))->lookup($confirmed->reference,1,$operator);
    $check($ticket['holder']==='Ana Guest' && $ticket['payment']['state']==='free','External free ticket');
    $writer=new JemRegistrationService($db);
    $writer->save(['id'=>$confirmed->id,'places'=>1],['actorId'=>42,'source'=>'test']);
    $check((int)$db->setQuery('SELECT reservedplaces FROM #__jem_events WHERE id=1')->loadResult()===3,'Partial cancellation returns one organiser place');
    $writer->cancelByIds([(int)$confirmed->id],1,['actorId'=>42,'source'=>'test']);
    $check((int)$db->setQuery('SELECT reservedplaces FROM #__jem_events WHERE id=1')->loadResult()===4,'Full cancellation restores remaining organiser place');
    $check((new JemTicketService($db))->lookup($confirmed->reference,1,$operator)['blocked']==='COM_JEM_TK_NOT_CONFIRMED','Cancelled QR denied');
    $open=array_replace($input,['event_id'=>3,'kind'=>'open','external'=>'Open Guest; open@example.invalid']);
    $preview=$service->compose($open,$operator);
    $service->compose($open+['fingerprint'=>$preview['fingerprint']],$operator,true);
    $openMail=end(JemFactory::$mail);
    $check((int)$service->resolve($openMail[1])[0]->reserved_assigned===0,'Open invitation does not hold places');
    $service->respond($openMail[1],true);
    $check((int)$db->setQuery('SELECT reservedplaces FROM #__jem_events WHERE id=3')->loadResult()===4,'Open acceptance uses general capacity');
    $groups=array_replace($input,['event_id'=>4,'audience'=>'groups','groups'=>[2,3],'external'=>'','kind'=>'open']);
    $preview=$service->compose($groups,$operator);
    $check($preview['recipients']===2 && count($preview['excluded'])===1,'Group deduplication and inactive exclusion');
    $series=array_replace($input,['event_id'=>1,'scope'=>'series','quantity'=>1,'external'=>'Series Guest; series@example.invalid']);
    $preview=$service->compose($series,$operator);
    $check($preview['dates']===2 && $preview['places']===2,'Series preview');
    $service->compose($series+['fingerprint'=>$preview['fingerprint']],$operator,true);
    $seriesMail=end(JemFactory::$mail);
    $db->setQuery("UPDATE #__jem_events SET event_status='cancelled' WHERE id=2")->execute();
    $reject(fn()=>$service->respond($seriesMail[1],true),'COM_JEM_INV_EVENT_CLOSED');
    $check(count($service->resolve($seriesMail[1]))===2,'Failed series response preserves capability');
    $check((int)$db->setQuery("SELECT COUNT(*) FROM #__jem_register WHERE guest_email='series@example.invalid' AND status=1")->loadResult()===0,'Series rollback, no partial acceptance');
    $db->setQuery("UPDATE #__jem_events SET event_status='scheduled' WHERE id=2")->execute();
    $check(count($service->respond($seriesMail[1],true))===2,'Series acceptance');
    $deny=$container->get(Joomla\CMS\User\UserFactoryInterface::class)->loadUserById(43);
    $reject(fn()=>$service->compose($input,$deny),'JERROR_ALERTNOAUTHOR');
    $changed=$open+['fingerprint'=>'invalid'];
    $reject(fn()=>$service->compose($changed,$operator,true),'COM_JEM_INV_PREVIEW_AGAIN');
    $check((int)$db->setQuery('SELECT COUNT(*) FROM #__users')->loadResult()===4,'No Joomla accounts created');
    // A confirmed response must not erase the future invitation rule.
    $new = clone $event; $new->id=6; $new->recurrence_first_id=1;
    $new->reservedplaces=(int)$db->setQuery('SELECT reservedplaces FROM #__jem_events WHERE id=1')->loadResult();
    $db->transactionStart(); $db->insertObject('#__jem_events',$new);
    $service->propagate(1,6); $db->transactionCommit();
    $next=$db->setQuery("SELECT * FROM #__jem_register WHERE event=6 AND guest_email='series@example.invalid'")->loadObject();
    $check($next && (int)$next->status===0 && (int)$next->reserved_assigned===1,'Accepted series invitation inherited by future date');
    $check((int)$db->setQuery('SELECT reservedplaces FROM #__jem_events WHERE id=6')->loadResult()===4,'Future date restores complete organiser quota');
    $db->transactionStart(); $service->propagate(1,6); $db->transactionCommit();
    $check((int)$db->setQuery('SELECT COUNT(*) FROM #__jem_register WHERE event=6')->loadResult()===1,'Repeated propagation does not overwrite or duplicate');
    $service->manage((int)$next->id,$operator,true);
    $check((int)$db->setQuery('SELECT status FROM #__jem_register WHERE id='.(int)$confirmed->id)->loadResult()===-1,'Withdrawal leaves unrelated cancelled row unchanged');
    $new->id=7; $db->transactionStart(); $db->insertObject('#__jem_events',$new); $service->propagate(1,7); $db->transactionCommit();
    $check((int)$db->setQuery('SELECT COUNT(*) FROM #__jem_register WHERE event=7')->loadResult()===0,'Withdrawn series does not propagate again');
    $expiry=array_replace($input,['event_id'=>5,'external'=>'Expiry; expiry@example.invalid','quantity'=>1]);
    $preview=$service->compose($expiry,$operator);
    $service->compose($expiry+['fingerprint'=>$preview['fingerprint']],$operator,true);
    $expiredMail=end(JemFactory::$mail); $exp=$service->resolve($expiredMail[1])[0];
    $db->setQuery("UPDATE #__jem_register SET invitation_expires='2000-01-01 00:00:00' WHERE id=".(int)$exp->id)->execute();
    $reject(fn()=>$service->respond($expiredMail[1],true),'COM_JEM_INV_INVALID_LINK');
    $check($service->expire()===1 && $service->expire()===0,'Expiry releases holds exactly once');
    $check((int)$db->setQuery('SELECT reservedplaces FROM #__jem_events WHERE id=5')->loadResult()===4,'Expiry returns to organiser pool, not public capacity');
    $owned=array_replace($input,['event_id'=>5,'users'=>[43],'external'=>'','quantity'=>1]);
    $preview=$service->compose($owned,$operator); $service->compose($owned+['fingerprint'=>$preview['fingerprint']],$operator,true);
    $ownedMail=end(JemFactory::$mail); $own=$service->resolve($ownedMail[1])[0];
    $service->manage((int)$own->id,$operator,false); $resent=end(JemFactory::$mail);
    $reject(fn()=>$service->resolve($ownedMail[1]),'COM_JEM_INV_INVALID_LINK');
    $check(count($service->resolve($resent[1]))===1,'Resend rotates token without additional allocation');
    $reject(fn()=>$service->resolveOwned((int)$own->id,$operator),'COM_JEM_INV_INVALID_LINK');
    $check(count($service->respondOwned((int)$own->id,$deny,true))===1,'Authenticated recipient can accept own invitation');
    // Price inventory with no physical venue: paid and complimentary are different states.
    $priceEvent=clone $event; $priceEvent->id=8; $priceEvent->recurrence_first_id=0;
    $priceEvent->pricing_mode='single'; $priceEvent->pricing_revision=1;
    $priceEvent->pricing_country_code='ES'; $priceEvent->currency='EUR';
    $db->insertObject('#__jem_events',$priceEvent);
    $tax=(object)['id'=>1,'code'=>'ZERO','name'=>'Zero','tax_type'=>'zero','rate'=>'0.00','country_code'=>'ES']; $db->insertObject('#__jem_tax_rates',$tax);
    $price=(object)['id'=>1,'event_id'=>8,'code'=>'ADULT','name'=>'Admission','amount'=>'15.00','tax_rate_id'=>1,'quota'=>4]; $db->insertObject('#__jem_event_prices',$price);
    $priced=array_replace($input,['event_id'=>8,'external'=>'Complimentary; free@example.invalid','price'=>'ADULT']);
    $preview=$service->compose($priced,$operator); $service->compose($priced+['fingerprint'=>$preview['fingerprint']],$operator,true);
    $freeMail=end(JemFactory::$mail); $free=$service->respond($freeMail[1],true)[0];
    $check($free->grand_total==='0.00' && $free->payment_state==='free','Complimentary priced invitation is free, not paid');
    $freeTicket=(new JemTicketService($db))->lookup($free->reference,8,$operator);
    $check($freeTicket['blocked']==='' && $freeTicket['payment']['state']==='free','Complimentary external QR admits without payment');
    $priced=array_replace($priced,['external'=>'Paid Guest; pay@example.invalid','free'=>0]);
    $preview=$service->compose($priced,$operator); $service->compose($priced+['fingerprint'=>$preview['fingerprint']],$operator,true);
    $payMail=end(JemFactory::$mail);
    $check((int)$service->resolve($payMail[1])[0]->reserved_assigned===2,'Paid invitation holds price quota while pending');
    $db->setQuery("UPDATE #__jem_event_prices SET amount='99.00' WHERE id=1")->execute();
    $pay=$service->respond($payMail[1],true)[0];
    $check($pay->grand_total==='30.00','Guaranteed price stays at the reviewed invitation amount');
    $tickets=new JemTicketService($db); $beforePayment=$tickets->lookup($pay->reference,8,$operator);
    $check($beforePayment['blocked']==='COM_JEM_TK_PAYMENT_REQUIRED','Non-complimentary QR requires recorded payment');
    $afterPayment=$tickets->execute('payment',['reference'=>$pay->reference,'event_id'=>8,
        'operation'=>$beforePayment['operation'],'version'=>$beforePayment['version'],
        'amount'=>'30.00','currency'=>'EUR','method'=>'cash','receipt'=>'QA invitation'], $operator);
    $check($afterPayment['blocked']==='' && $afterPayment['reference']===$pay->reference,'Cash payment activates unchanged external ticket locator');
    // A guaranteed area stays occupied before acceptance, including waiting-list promotions.
    $areaEvent=clone $event; $areaEvent->id=9; $areaEvent->recurrence_first_id=0; $areaEvent->capacity_mode='areas';
    $areaEvent->venue_snapshot=json_encode(['schema'=>'jem-venue-capacity/v1','spaces'=>[
        ['id'=>1,'name'=>'Hall','layout'=>['id'=>1,'name'=>'Seated','capacity'=>4],
         'capacity_areas'=>[['id'=>11,'code'=>'VIP','name'=>'VIP','published'=>1,'capacity'=>2],
             ['id'=>12,'code'=>'GENERAL','name'=>'General','published'=>1,'capacity'=>2]]]]]);
    $db->insertObject('#__jem_events',$areaEvent);
    $areaInput=array_replace($input,['event_id'=>9,'area'=>'area:11','external'=>'Area guest; area@example.invalid']);
    $preview=$service->compose($areaInput,$operator); $service->compose($areaInput+['fingerprint'=>$preview['fingerprint']],$operator,true);
    $areaMail=end(JemFactory::$mail);
    try {
        $writer->save(['event'=>9,'uid'=>43,'status'=>1,'places'=>1],['actorId'=>42,'capacityAllocations'=>['area:11'=>1]]);
        throw new LogicException('Pending invitation area was sold twice');
    } catch (RuntimeException $error) {
        $check(str_contains($error->getMessage(),'capacity area'),'Pending assigned area blocks ordinary booking');
    }
    $areaConfirmed=$service->respond($areaMail[1],true)[0];
    $check((int)$areaConfirmed->places===2,'Held area transfers to confirmed once');
    $alloc=$db->setQuery('SELECT SUM(quantity) FROM #__jem_register_capacity_allocations WHERE register_id='.(int)$areaConfirmed->id.' AND registration_revision='.(int)$areaConfirmed->revision)->loadResult();
    $check((int)$alloc===2,'Accepted reservation preserves physical area snapshot');
    $writer->cancelByIds([(int)$areaConfirmed->id],9,['actorId'=>42]);
    $check((int)$db->setQuery('SELECT reservedplaces FROM #__jem_events WHERE id=9')->loadResult()===4,'Area cancellation returns organiser origin');
    $seriesPrice=clone $priceEvent; $seriesPrice->id=10; $db->insertObject('#__jem_events',$seriesPrice);
    $price->id=2; $price->event_id=10; $price->quota=4; $db->insertObject('#__jem_event_prices',$price);
    $seriesInput=array_replace($input,['event_id'=>10,'scope'=>'series','quantity'=>1,'price'=>'ADULT','external'=>'Series priced; seriesprice@example.invalid']);
    $preview=$service->compose($seriesInput,$operator); $service->compose($seriesInput+['fingerprint'=>$preview['fingerprint']],$operator,true);
    $seriesPriceMail=end(JemFactory::$mail); $service->respond($seriesPriceMail[1],true);
    $seriesPrice->id=11; $seriesPrice->recurrence_first_id=10;
    $db->transactionStart(); $db->insertObject('#__jem_events',$seriesPrice); $service->propagate(10,11); $db->transactionCommit();
    $check((int)$db->setQuery('SELECT COUNT(*) FROM #__jem_register WHERE event=11 AND reserved_assigned=1')->loadResult()===1,'Priced series inherits pending invitation with fresh independent prices');
    $check((int)$db->setQuery('SELECT id FROM #__jem_event_prices WHERE event_id=11')->loadResult()!==2,'New occurrence does not share mutable price ID');
    // Mailer unavailable: no committed invitation, inventory or history.
    $check((int)$db->setQuery('SELECT COUNT(*) FROM #__jem_register_history WHERE invitation_snapshot IS NOT NULL')->loadResult()>0,'Invitation provenance is audited');
    $check((int)$db->setQuery("SELECT COUNT(*) FROM #__jem_register_history WHERE invitation_snapshot LIKE '%invitation_token_hash%'")->loadResult()===0,'Audit does not contain invitation capability hashes');
    $price->id=4; $price->event_id=7; $db->insertObject('#__jem_event_prices',$price);
    $db->setQuery("UPDATE #__jem_events SET pricing_mode='single',currency='EUR',pricing_country_code='ES',pricing_revision=1 WHERE id=7")->execute();
    $changedPrice=array_replace($input,['event_id'=>7,'price'=>'ADULT','free'=>0,'quantity'=>1,'external'=>'Quote test; quote@example.invalid']);
    $review=$service->compose($changedPrice,$operator);
    $db->setQuery("UPDATE #__jem_event_prices SET amount='22.00' WHERE id=4")->execute();
    $reject(fn()=>$service->compose($changedPrice+['fingerprint'=>$review['fingerprint']],$operator,true),'COM_JEM_INV_PREVIEW_AGAIN');
    $check((int)$db->setQuery('SELECT COUNT(*) FROM #__jem_register WHERE event=7')->loadResult()===0,'Changed price invalidates preview and rolls back guarantees');
    require_once $source . '/site/classes/registrationstatus.class.php';
    $totals=JemRegistrationStatus::totals((object)['maxplaces'=>10,'reservedplaces'=>4,'confirmed'=>2,'reserved_assigned'=>2,'reserved_confirmed'=>1],
        [['capacity'=>3,'confirmed'=>1,'assigned'=>2]]);
    $check($totals['organiser_total']===5 && $totals['organiser_available']===2 && $totals['pools'][0]['free']===0,'Tickets separates organiser total and held area availability');
    $converted=array_replace($input,['event_id'=>4,'quantity'=>1,'external'=>'New member; becomes@example.invalid','kind'=>'open']);
    $preview=$service->compose($converted,$operator); $service->compose($converted+['fingerprint'=>$preview['fingerprint']],$operator,true);
    $db->setQuery("INSERT INTO #__users VALUES (46,'New member','becomes@example.invalid',0,NULL)")->execute();
    $converted=array_replace($converted,['external'=>'','users'=>[46]]);
    $reject(fn()=>$service->compose($converted,$operator),'COM_JEM_INV_NO_RECIPIENTS');
    $check((int)$db->setQuery("SELECT COUNT(*) FROM #__jem_register WHERE event=4 AND guest_email='becomes@example.invalid'")->loadResult()===1,
        'An external guest who later joins Joomla does not receive a duplicate allocation');
    $limited=new class {
        public int $id=42;
        public function authorise($action,$asset): bool {return $action==='jem.invitations.manage';}
    };
    $reject(fn()=>$service->compose($input,$limited),'JERROR_ALERTNOAUTHOR');
    $reject(fn()=>$service->recipients(['audience'=>'all'],$limited),'JERROR_ALERTNOAUTHOR');
    $ordinary=$writer->save(['event'=>4,'uid'=>44,'status'=>1,'places'=>1,'invitation_kind'=>'reserved',
        'invitation_free'=>1,'guest_email'=>'forged@example.invalid'],['actorId'=>42,'respectPlaces'=>true])->after;
    $stored=$db->setQuery('SELECT * FROM #__jem_register WHERE id='.(int)$ordinary->id)->loadObject();
    $check($stored->invitation_kind==='' && (int)$stored->invitation_free===0 && $stored->guest_email===null,
        'Ordinary writer input cannot forge trusted invitation metadata');
    $failed=array_replace($input,['event_id'=>3,'kind'=>'open','quantity'=>1,'external'=>'Mail queue failure; failure@example.invalid']);
    $review=$service->compose($failed,$operator);
    JemFactory::$rejectMail=true;
    try {$reject(fn()=>$service->compose($failed+['fingerprint'=>$review['fingerprint']],$operator,true),'COM_JEM_INV_MAILER_REQUIRED');}
    finally {JemFactory::$rejectMail=false;}
    $check((int)$db->setQuery("SELECT COUNT(*) FROM #__jem_register WHERE guest_email='failure@example.invalid'")->loadResult()===0,
        'Queue creation failure rolls back the invitation instead of holding unnotified places');
    $reject(fn()=>$writer->save(['id'=>$free->id,'places'=>3],['actorId'=>42]),'COM_JEM_INV_QUANTITY_FIXED');
    $reject(fn()=>$writer->save(['id'=>$confirmed->id,'status'=>1,'places'=>1],['actorId'=>42]),'COM_JEM_INV_USE_RESPONSE');
    $reject(fn()=>$writer->save(['id'=>$own->id,'status'=>1,'waiting'=>1],['actorId'=>42]),'COM_JEM_INV_NO_WAITING');
    $db->setQuery('UPDATE #__jem_events SET maxplaces=1 WHERE id=3')->execute();
    $reject(fn()=>$service->compose(array_replace($input,['event_id'=>3,'quantity'=>1,'external'=>'Overflow; overflow@example.invalid']),$operator),
        'COM_JEM_INV_NOT_ENOUGH_RESERVED');
    $db->transactionStart(); $service->propagate(10,5); $db->transactionCommit();
    $check((int)$db->setQuery("SELECT COUNT(*) FROM #__jem_register WHERE event=5 AND guest_email='seriesprice@example.invalid'")->loadResult()===0,
        'An unrelated copied event does not inherit a series invitation');
    require __DIR__ . '/invitation_mailer_checks.php';
    echo json_encode(['checks'=>$checks,'database'=>'isolated','mail'=>'captured, not sent'],JSON_THROW_ON_ERROR).PHP_EOL;
} finally {
    $server->query('DROP DATABASE '.$database);
}

