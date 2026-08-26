<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class RequestMethodSecurityTest extends TestCase
{
    public function testMutationEndpointsRequirePostTokensBeforeUsingPostData(): void
    {
        $methods = array(
            array('admin/controller.php', 'ajaxattachremove', true),
            array('admin/controllers/categories.php', 'saveOrderAjax', true),
            array('admin/controllers/cssmanager.php', 'copycustom', true),
            array('admin/controllers/cssmanager.php', 'deletecustom', true),
            array('admin/controllers/cssmanager.php', 'createusercss', true),
            array('admin/controllers/frontendmenu.php', 'create', false),
            array('admin/controllers/housekeeping.php', 'delete', true),
            array('admin/controllers/housekeeping.php', 'cleanupCatsEventRelations', false),
            array('admin/controllers/housekeeping.php', 'cleanupUnusedAttachmentFiles', false),
            array('admin/controllers/housekeeping.php', 'resizethumbs', false),
            array('admin/controllers/housekeeping.php', 'truncateAllData', true),
            array('admin/controllers/housekeeping.php', 'triggerarchive', false),
            array('admin/controllers/imagehandler.php', 'delete', true),
            array('admin/controllers/import.php', 'logCreatedImportOption', true),
            array('admin/controllers/sampledata.php', 'load', false),
            array('admin/controllers/specialdays.php', 'saveOrderAjax', true),
            array('admin/controllers/types.php', 'saveOrderAjax', true),
            array('admin/controllers/venues.php', 'saveOrderAjax', true),
            array('site/controller.php', 'ajaxattachremove', true),
            array('site/controllers/attendees.php', 'attendeeadd', true),
            array('site/controllers/attendees.php', 'attendeeremove', true),
            array('site/controllers/attendees.php', 'attendeetoggle', true),
        );

        foreach ($methods as list($path, $method, $usesPostData)) {
            $body = $this->method($path, $method);
            self::assertStringContainsString(
                'JemHelper::requirePostToken()',
                $body,
                $path . ':' . $method . ' must reject non-POST requests and invalid tokens.'
            );

            if ($usesPostData) {
                self::assertStringContainsString(
                    '->post->',
                    $body,
                    $path . ':' . $method . ' must read mutation input from the POST body.'
                );
            }
        }

        $housekeeping = $this->read('admin/controllers/housekeeping.php');

        foreach (array('auditImages', 'normaliseImages') as $method) {
            if (preg_match('/\bfunction\s+' . preg_quote($method, '/') . '\s*\(/', $housekeeping)) {
                self::assertStringContainsString('JemHelper::requirePostToken()', $this->method('admin/controllers/housekeeping.php', $method));
            }
        }
    }

    public function testSharedRequestPolicyEnforcesPostAndPrivateResponses(): void
    {
        $helper = $this->read('site/helpers/helper.php');
        $postGuard = $this->method('site/helpers/helper.php', 'requirePostToken');
        $privateHeaders = $this->method('site/helpers/helper.php', 'setNoStoreHeaders');

        self::assertStringContainsString("getString('REQUEST_METHOD'", $postGuard);
        self::assertStringContainsString("\$method !== 'POST'", $postGuard);
        self::assertStringContainsString("Session::checkToken('post')", $postGuard);
        self::assertStringContainsString("'Cache-Control', 'no-store, private'", $privateHeaders);
        self::assertStringContainsString("'Pragma', 'no-cache'", $privateHeaders);
        self::assertStringContainsString("'X-Content-Type-Options', 'nosniff'", $privateHeaders);
        self::assertStringContainsString('random_bytes(32)', $helper);
    }

    public function testDestructiveResetRequiresPurposeBoundOneTimeNonce(): void
    {
        $issue = $this->method('site/helpers/helper.php', 'issueActionNonce');
        $consume = $this->method('site/helpers/helper.php', 'consumeActionNonce');
        $controller = $this->method('admin/controllers/housekeeping.php', 'truncateAllData');
        $view = $this->read('admin/views/housekeeping/view.html.php');
        $layout = $this->read('admin/views/housekeeping/tmpl/default.php');

        self::assertStringContainsString('hash(\'sha256\', $purpose', $issue);
        self::assertStringContainsString('$now + max(60, min(1800', $issue);
        self::assertStringContainsString('hash_equals((string) $storedKey, $key)', $consume);
        self::assertStringContainsString('unset($nonces[$storedKey])', $consume);
        self::assertStringContainsString("->post->getString('truncate_nonce'", $controller);
        self::assertStringContainsString("consumeActionNonce('housekeeping.truncateAllData'", $controller);
        self::assertStringContainsString("issueActionNonce('housekeeping.truncateAllData'", $view);
        self::assertStringContainsString('method="post"', $layout);
        self::assertStringContainsString('name="truncate_nonce"', $layout);
        self::assertStringContainsString("HTMLHelper::_('form.token')", $layout);
    }

    public function testProtectedReadsKeepAclAndDisableCachingWithoutUrlTokens(): void
    {
        $reads = array(
            array('admin/controllers/attachments.php', 'download', "canAccessAttachment(\$object, 'access')"),
            array('admin/controllers/cssmanager.php', 'downloadcustom', "canManage('jem.tools.manage')"),
            array('admin/controllers/import.php', 'viewLog', 'assertCanImport()'),
            array('admin/controllers/import.php', 'downloadLog', 'assertCanImport()'),
            array('admin/controllers/settings.php', 'viewLog', '$this->allowEdit()'),
            array('admin/controllers/settings.php', 'downloadLog', '$this->allowEdit()'),
            array('site/controller.php', 'getfile', 'JemAttachment::getAttachmentPath'),
            array('site/controllers/attendees.php', 'export', 'assertCanManageAttendees($eventid)'),
        );

        foreach ($reads as list($path, $method, $acl)) {
            $body = $this->method($path, $method);
            self::assertStringContainsString($acl, $body, $path . ':' . $method . ' must retain its resource ACL.');
            self::assertStringContainsString('JemHelper::setNoStoreHeaders()', $body, $path . ':' . $method . ' must be private.');
            self::assertStringContainsString('sendHeaders()', $body, $path . ':' . $method . ' must emit its private headers.');
            self::assertStringNotContainsString("checkToken('get')", $body);
            self::assertStringNotContainsString("checkToken('request')", $body);
        }

        $optionalReads = array(
            array('admin/controllers/attendee.php', 'pricingOptions', 'assertCanManageAttendees()'),
            array('admin/controllers/event.php', 'venueConfigurations', '$this->allowEdit('),
        );

        foreach ($optionalReads as list($path, $method, $acl)) {
            if (!is_file(JEM_TEST_ROOT . '/' . $path)) {
                continue;
            }

            $contents = $this->read($path);

            if (!preg_match('/\bfunction\s+' . preg_quote($method, '/') . '\s*\(/', $contents)) {
                continue;
            }

            $body = $this->method($path, $method);
            self::assertStringContainsString($acl, $body);
            self::assertStringContainsString('JemHelper::setNoStoreHeaders()', $body);
            self::assertStringContainsString('sendHeaders()', $body);
        }
    }

    public function testJemOwnedLinksDoNotExposeSessionTokens(): void
    {
        $paths = array(
            'admin/views/attachments/tmpl/default.php',
            'admin/views/attendee/tmpl/default.php',
            'admin/views/event/tmpl/edit_attachments.php',
            'admin/views/venue/tmpl/edit_attachments.php',
            'site/common/views/tmpl/default_attachments.php',
            'site/common/views/tmpl/default_attachments_edit.php',
            'site/common/views/tmpl/responsive/default_attachments.php',
            'site/views/attendees/tmpl/addusers.php',
            'site/views/attendees/tmpl/responsive/addusers.php',
            'site/views/editevent/tmpl/choosearticle.php',
            'site/views/editevent/tmpl/choosecontact.php',
            'site/views/editevent/tmpl/chooseusers.php',
            'site/views/editevent/tmpl/choosevenue.php',
            'site/views/editevent/tmpl/responsive/choosecontact.php',
            'site/views/editevent/tmpl/responsive/chooseusers.php',
            'site/views/editevent/tmpl/responsive/choosevenue.php',
        );

        foreach ($paths as $path) {
            self::assertStringNotContainsString(
                'Session::getFormToken()',
                $this->read($path),
                $path . ' must not expose a session token in a JEM-owned URL.'
            );
        }
    }

    private function method(string $relativePath, string $method): string
    {
        $contents = $this->read($relativePath);

        if (!preg_match('/\bfunction\s+' . preg_quote($method, '/') . '\s*\([^)]*\)\s*(?::\s*[^\{]+)?\{/m', $contents, $match, PREG_OFFSET_CAPTURE)) {
            self::fail($relativePath . ':' . $method . ' was not found.');
        }

        $openBrace = strpos($contents, '{', $match[0][1]);
        self::assertNotFalse($openBrace);

        $depth = 0;
        $length = strlen($contents);

        for ($offset = $openBrace; $offset < $length; $offset++) {
            if ($contents[$offset] === '{') {
                $depth++;
            } elseif ($contents[$offset] === '}') {
                $depth--;

                if ($depth === 0) {
                    return substr($contents, $openBrace, $offset - $openBrace + 1);
                }
            }
        }

        self::fail($relativePath . ':' . $method . ' has an incomplete body.');
    }

    private function read(string $relativePath): string
    {
        $path = JEM_TEST_ROOT . '/' . $relativePath;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
