<?php
/**
 * @package    JEM
 * @copyright  (C) 2013-2026 joomlaeventmanager.net
 * @license    https://www.gnu.org/licenses/gpl-3.0 GNU/GPL
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once JEM_TEST_ROOT . '/site/classes/notificationtemplaterenderer.class.php';

final class NotificationTemplateRendererTest extends TestCase
{
    public function testFormatDetectionDistinguishesNamedLegacyMixedAndStaticText(): void
    {
        self::assertSame('named', JemNotificationTemplateRenderer::detectFormat('Hello {user_name}'));
        self::assertSame('legacy', JemNotificationTemplateRenderer::detectFormat('Hello %s'));
        self::assertSame('mixed', JemNotificationTemplateRenderer::detectFormat('Hello %s at {event_title}'));
        self::assertSame('static', JemNotificationTemplateRenderer::detectFormat('Registration confirmed'));
    }

    public function testLegacyNormalisationSupportsSequentialNumberedAndLiteralPercentMarkers(): void
    {
        self::assertSame(
            'Hello {user_name}, {event_title} is 100% ready',
            JemNotificationTemplateRenderer::normaliseLegacy(
                'Hello %s, %2$s is 100%% ready',
                array('user_name', 'event_title')
            )
        );
    }

    public function testNamedVariablesCanBeMovedRepeatedOrOmitted(): void
    {
        $template = '{event_title}: {user_name} / {user_name}';
        $values = array('user_name' => 'Ada', 'event_title' => 'JEM Day', 'unused' => 'ignored');

        self::assertSame(
            'JEM Day: Ada / Ada',
            JemNotificationTemplateRenderer::render($template, $values)
        );
    }

    public function testMixedTemplateMigratesOnlyTheLegacyPartInMemory(): void
    {
        self::assertSame(
            'Ada registered for JEM Day',
            JemNotificationTemplateRenderer::render(
                '%s registered for {event_title}',
                array('user_name' => 'Ada', 'event_title' => 'JEM Day'),
                array('user_name')
            )
        );
    }

    public function testHtmlRenderingEscapesSubstitutedValues(): void
    {
        self::assertSame(
            '<p>&lt;Ada &amp; Bob&gt;</p>',
            JemNotificationTemplateRenderer::render(
                '<p>{user_name}</p>',
                array('user_name' => '<Ada & Bob>'),
                array(),
                true
            )
        );
    }

    public function testValidationRejectsUnknownMalformedAndLegacyVariables(): void
    {
        $result = JemNotificationTemplateRenderer::validate(
            'Hello {unknown} {bad-name} %s',
            array('user_name', 'event_title'),
            array('user_name', 'event_title')
        );

        self::assertContains('legacy_markers_not_allowed', $result['errors']);
        self::assertContains('unknown_variable:unknown', $result['errors']);
        self::assertContains('invalid_variable:bad-name', $result['errors']);
        self::assertContains('missing_recommended:user_name', $result['warnings']);
        self::assertContains('missing_recommended:event_title', $result['warnings']);
    }

    public function testStaticTemplateIsValidAndOnlyWarnsAboutRecommendedVariables(): void
    {
        $result = JemNotificationTemplateRenderer::validate(
            'Your registration is confirmed.',
            array('user_name', 'event_title'),
            array('event_title')
        );

        self::assertSame(array(), $result['errors']);
        self::assertSame(array('missing_recommended:event_title'), $result['warnings']);
    }

    public function testHtmlCssBracesAreNotMistakenForNotificationVariables(): void
    {
        $result = JemNotificationTemplateRenderer::validate(
            '<style>.event { color: red; }</style><p>{event_title}</p>',
            array('event_title')
        );

        self::assertSame(array(), $result['errors']);
        self::assertSame(array('event_title'), $result['variables']);
    }

    public function testMissingRuntimeValueIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        JemNotificationTemplateRenderer::render('Hello {user_name}', array());
    }
}
