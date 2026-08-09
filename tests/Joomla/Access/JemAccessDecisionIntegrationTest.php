<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/JoomlaTestCase.php';

final class JemAccessDecisionIntegrationTest extends JoomlaTestCase
{
    protected function setUp(): void
    {
        self::bootJoomlaSite();

        require_once JPATH_BASE . '/components/com_jem/factory.php';
        require_once JPATH_BASE . '/components/com_jem/helpers/helper.php';
    }

    public function testInstalledJemUserKeepsBooleanApiAndProvidesDetailedDecision(): void
    {
        $user = JemFactory::getUser();

        self::assertTrue(method_exists($user, 'can'));
        self::assertTrue(method_exists($user, 'getAccessDecision'));

        $decision = $user->getAccessDecision('edit', 'event', 1, 1);

        // Joomla's integration bootstrap has no authenticated browser identity.
        self::assertFalse($decision->isAllowed());
        self::assertSame(JemAccessDecision::AUTHENTICATION_REQUIRED, $decision->getCode());
        self::assertSame('authentication', $decision->getStage());
        self::assertSame('joomla_identity', $decision->getSource());
        self::assertSame(401, $decision->getHttpStatus());
        self::assertFalse($user->can('edit', 'event', 1, 1));
    }
}
