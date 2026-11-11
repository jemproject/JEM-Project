<?php
/**
 * Exercise the unchanged Mailer invitation method, real templates, durable queue and PDF.
 * Only language/configuration/absolute-route boundaries are isolated from the unavailable site DB.
 * Included by invitation_management_probe.php; no delivery method is called.
 */
require_once $source . '/site/classes/notificationtemplatecatalog.class.php';
require_once $source . '/site/classes/notificationtemplaterenderer.class.php';
require_once $source . '/site/classes/notificationservice.class.php';
require_once $source . '/site/classes/ticketpdf.class.php';
if (!class_exists('JemConfig', false)) {
    class JemConfig {
        public static function getInstance(): self {return new self();}
        public function toRegistry(): object {return new Joomla\Registry\Registry();}
    }
}
class InvitationQueueBoundary {
    public function resolveRecipientLanguage($uid,$email): object {return (object)['resolved'=>'en-GB'];}
    public function create(array $data): object {return (new JemNotificationService())->create($data);}
}
class InvitationRouteBoundary {
    public static function getAbsoluteSiteRoute($path): string {return 'https://example.invalid/'.$path;}
}
$code=file_get_contents($source.'/plugins/plg_jem_mailer/mailer.php');
if(!preg_match('/public function onJemInvitationNotification\([^\n]*\)[^\n]*\s*\{.*?^    \}/ms',$code,$match)) {
    throw new RuntimeException('Missing invitation Mailer method');
}
// Dependencies have already been loaded from the checkout under test, never the installed plugin.
$method=preg_replace('/^\s*require_once[^\n]*;[\t \r]*$/m','',$match[0]);
$method=str_replace('new JemNotificationService()', 'new InvitationQueueBoundary()', $method);
$method=str_replace('JEMHelperRoute::','InvitationRouteBoundary::',$method);
class InvitationMailerBoundary {
    protected string $_SiteName='JEM invitation QA';
    protected function _renderNotification($subject,$body,$values,$event,$language): object {
        $ini=parse_ini_file(dirname(__DIR__,2).'/plugins/plg_jem_mailer/language/en-GB/plg_jem_mailer.ini',false,INI_SCANNER_RAW);
        $definition=JemNotificationTemplateCatalog::findByLanguageKeys($subject,$body);
        return (object)['template_id'=>$definition['id'],
            'subject'=>JemNotificationTemplateRenderer::render($ini[$subject],$values,$definition['subject_legacy_tokens']),
            'body'=>JemNotificationTemplateRenderer::render($ini[$body],$values,$definition['body_legacy_tokens']), 'htmlbody'=>''];
    }
}
eval('use Joomla\\CMS\\Factory; use Joomla\\CMS\\Language\\Text; class InvitationMailerUnderTest extends InvitationMailerBoundary {'.$method.'}');
Joomla\CMS\Factory::$language->load('com_jem',$source.'/site','en-GB',true);
$plugin=new InvitationMailerUnderTest();
$pending=$db->setQuery('SELECT * FROM #__jem_register WHERE event=11')->loadObject();
$rawToken=JemInvitationPolicy::token();
$check($plugin->onJemInvitationNotification([$pending],$rawToken,'invitation'),'Real Mailer queues invitation');
$queued=$db->setQuery('SELECT * FROM #__jem_notifications ORDER BY id DESC')->loadObject();
$check($queued->recipient_email==='seriesprice@example.invalid' && str_contains($queued->body,'Series priced'),'Mailer personalises external recipient');
$check(str_contains($queued->body,$rawToken) && str_contains($queued->body,'2099-'),'Mailer includes restricted link and explicit session date');
$check($queued->state==='queued' && (int)$queued->attempt_count===0,'No email transport during invitation transaction');
$check($plugin->onJemInvitationNotification([$free],'','accepted'),'Real Mailer queues accepted complimentary invitation');
$pdfMail=$db->setQuery('SELECT * FROM #__jem_notifications ORDER BY id DESC')->loadObject();
$attachments=json_decode($pdfMail->attachments_json,true,512,JSON_THROW_ON_ERROR);
$bytes=base64_decode($attachments[0]['content_base64'],true);
$check(str_starts_with($bytes,'%PDF-') && hash('sha256',$bytes)===$attachments[0]['sha256'],'Accepted external guest receives real PDF bytes');
$check(str_contains($attachments[0]['name'],$free->reference),'PDF filename uses reservation locator, not invitation secret');
$plugin->onJemInvitationNotification([$free],'','accepted');
$check((int)$db->setQuery('SELECT COUNT(*) FROM #__jem_notifications')->loadResult()===2,'Mailer retry keeps one snapshot per response revision');

$service->manage((int)$pending->id,$operator,true);
$check($db->setQuery('SELECT state FROM #__jem_notifications WHERE id='.(int)$queued->id)->loadResult()==='cancelled',
    'Withdrawal cancels unsent email with the obsolete capability');
