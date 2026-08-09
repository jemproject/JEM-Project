<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/classes/accessdecision.class.php';

final class AccessDecisionTest extends TestCase
{
    public function testAllowedDecisionProvidesAStablePublicSummary(): void
    {
        $decision = JemAccessDecision::allow(
            'component_acl',
            'joomla_core_manage',
            'edit',
            'event',
            42,
            array(),
            array('grantedPermission' => 'core.manage')
        );

        self::assertTrue($decision->isAllowed());
        self::assertSame(JemAccessDecision::ALLOWED, $decision->getCode());
        self::assertSame('component_acl', $decision->getStage());
        self::assertSame('joomla_core_manage', $decision->getSource());
        self::assertSame(200, $decision->getHttpStatus());
        self::assertArrayNotHasKey('resourceId', $decision->toArray());
        self::assertArrayNotHasKey('details', $decision->toArray());
        self::assertArrayNotHasKey('source', $decision->toArray());
    }

    public function testDeniedDecisionKeepsInternalDiagnosticsSeparate(): void
    {
        $reasons = array(array(
            'code' => JemAccessDecision::VIEW_LEVEL_DENIED,
            'stage' => 'record_view_level',
            'source' => 'joomla_view_level',
            'action' => 'edit',
        ));
        $decision = JemAccessDecision::deny(
            JemAccessDecision::VIEW_LEVEL_DENIED,
            'record_view_level',
            'joomla_view_level',
            'edit',
            'venue',
            73,
            $reasons,
            array('requiredViewLevel' => 8)
        );

        self::assertFalse($decision->isAllowed());
        self::assertSame(404, $decision->getHttpStatus());
        self::assertSame('COM_JEM_ACCESS_RECORD_NOT_FOUND', $decision->getMessageKey());
        self::assertSame($reasons, $decision->getReasons());

        $public = $decision->toArray(false);
        self::assertArrayNotHasKey('resourceId', $public);
        self::assertArrayNotHasKey('reasons', $public);
        self::assertArrayNotHasKey('code', $public);
        self::assertArrayNotHasKey('source', $public);

        $internal = $decision->toArray(true);
        self::assertSame(73, $internal['resourceId']);
        self::assertSame(JemAccessDecision::VIEW_LEVEL_DENIED, $internal['code']);
        self::assertSame('joomla_view_level', $internal['source']);
        self::assertSame(8, $internal['details']['requiredViewLevel']);
    }

    public function testInvalidRequestsAndAuthenticationHaveDefinedResponses(): void
    {
        $invalid = JemAccessDecision::deny(
            JemAccessDecision::INVALID_ACTION,
            'request',
            'jem',
            'invalid',
            'event'
        );
        $guest = JemAccessDecision::deny(
            JemAccessDecision::AUTHENTICATION_REQUIRED,
            'authentication',
            'joomla_identity',
            'edit',
            'event'
        );

        self::assertSame(400, $invalid->getHttpStatus());
        self::assertSame('COM_JEM_ERROR_INVALID_ACCESS_REQUEST', $invalid->getMessageKey());
        self::assertSame(401, $guest->getHttpStatus());
        self::assertSame('COM_JEM_ACCESS_AUTHENTICATION_REQUIRED', $guest->getMessageKey());
    }
}
