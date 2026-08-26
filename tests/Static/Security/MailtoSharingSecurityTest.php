<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class MailtoSharingSecurityTest extends TestCase
{
    public function testControllerRequiresAuthenticatedProtectedPost(): void
    {
        $controller = $this->read('site/controllers/mailto.php');

        self::assertStringContainsString('JemHelper::requirePostToken();', $controller);
        self::assertStringContainsString('JemHelper::setNoStoreHeaders();', $controller);
        self::assertStringContainsString('JemMailtoHelper::canCurrentUserSend($app)', $controller);
        self::assertStringNotContainsString('JemMailtoHelper::isCaptchaAvailable($app)', $controller);
        self::assertStringContainsString('JemMailtoHelper::validateHash($post_link)', $controller);
        self::assertStringNotContainsString('?: $post_link', $controller);
    }

    public function testAuthenticatedAccountControlsTheMailEnvelope(): void
    {
        $controller = $this->read('site/controllers/mailto.php');

        self::assertStringContainsString("\$data['sender'] = (string) \$user->name;", $controller);
        self::assertStringContainsString("\$data['emailfrom'] = (string) \$user->email;", $controller);
        self::assertStringContainsString("\$app->get('mailfrom', '')", $controller);
        self::assertStringContainsString('$mailer->addReplyTo($replyAddress, $replyName)', $controller);
        self::assertStringNotContainsString('$mailer->setSender($from)', $controller);
        self::assertStringNotContainsString('$e->getMessage()', $controller);
    }

    public function testLimitsAreConsumedBeforeDeliveryAndContainNoRecipientIdentity(): void
    {
        $controller = $this->read('site/controllers/mailto.php');
        $helper = $this->read('site/helpers/mailtohelper.php');

        self::assertStringContainsString('public const ACCOUNT_RATE_LIMIT = 5;', $helper);
        self::assertStringContainsString('public const SESSION_RATE_LIMIT = 5;', $helper);
        self::assertStringContainsString('public const IP_RATE_LIMIT = 20;', $helper);
        self::assertStringContainsString("hash_hmac('sha256'", $helper);
        self::assertStringContainsString("flock(\$handle, LOCK_EX)", $helper);
        self::assertLessThan(
            strpos($controller, '$mailer->send()'),
            strpos($controller, 'JemMailtoHelper::consumeSubmissionLimits(')
        );
    }

    public function testMailViewIsPrivateAndCannotUseAnUnboundLink(): void
    {
        $view = $this->read('site/views/mailto/view.html.php');

        self::assertStringContainsString('JemHelper::setNoStoreHeaders();', $view);
        self::assertStringContainsString("'X-Robots-Tag', 'noindex, nofollow'", $view);
        self::assertStringContainsString('JemMailtoHelper::canCurrentUserSend($app)', $view);
        self::assertStringNotContainsString('JemMailtoHelper::isCaptchaAvailable($app)', $view);
        self::assertStringContainsString('$resolvedLink = JemMailtoHelper::validateHash($link);', $view);
        self::assertStringNotContainsString('?: $link', $view);
    }

    public function testGuestsReceiveOnlyTheLocalCopyLinkAction(): void
    {
        $output = $this->read('site/classes/output.class.php');
        $javascript = $this->read('media/js/share-link.js');

        self::assertStringContainsString('if ($app->getIdentity()->guest)', $output);
        self::assertStringNotContainsString('JemMailtoHelper::isCaptchaAvailable($app)', $output);
        self::assertStringContainsString('data-jem-share-link', $output);
        self::assertStringContainsString('navigator.clipboard.writeText(link)', $javascript);
        self::assertStringContainsString("document.execCommand('copy')", $javascript);
        self::assertStringContainsString('window.prompt(', $javascript);
        self::assertStringNotContainsString('https://', $javascript);
        self::assertStringNotContainsString('http://', $javascript);
    }

    public function testSuccessfulMailShareLogsTheJemItemWithoutRecipientData(): void
    {
        $controller = $this->read('site/controllers/mailto.php');
        $plugin = $this->read('plugins/plg_actionlog_jem/src/Extension/Jem.php');
        $language = $this->read('plugins/plg_actionlog_jem/language/en-GB/plg_actionlog_jem.ini');

        self::assertGreaterThan(
            strpos($controller, '$mailer->send()'),
            strpos($controller, '$this->triggerActionLog(')
        );
        self::assertStringContainsString("'onJemMailtoSent' => 'onJemMailtoSent'", $plugin);
        self::assertStringContainsString("'context' => 'com_jem.mailto.event'", $plugin);
        self::assertStringContainsString("'id' => \$id", $plugin);
        self::assertStringContainsString("'title' => \$title", $plugin);
        self::assertStringNotContainsString('emailto', $plugin);
        self::assertStringNotContainsString("['recipient']", strtolower($plugin));
        self::assertStringNotContainsString("\$recipient", $plugin);
        self::assertStringContainsString('PLG_ACTIONLOG_JEM_MAIL_SHARED=', $language);
    }

    public function testFormUsesAuthenticatedIdentityWithoutAForcedCaptchaDependency(): void
    {
        $form = $this->read('site/models/forms/mailto.xml');
        $settings = $this->read('admin/views/settings/view.html.php');

        self::assertStringContainsString('type="captcha"', $form);
        self::assertStringContainsString('validate="captcha"', $form);
        self::assertStringContainsString('readonly="true"', $form);
        self::assertStringNotContainsString('namespace="com_jem.mailto"', $form);
        self::assertStringNotContainsString('COM_JEM_SETTINGS_MAILTO_CAPTCHA_WARNING', $settings);
        self::assertStringNotContainsString("PluginHelper::isEnabled('captcha', \$captcha)", $settings);
    }

    private function read(string $relativePath): string
    {
        $contents = file_get_contents(dirname(__DIR__, 3) . '/' . $relativePath);
        self::assertIsString($contents, 'Unable to read ' . $relativePath);

        return $contents;
    }
}
