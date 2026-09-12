<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class RegistrationRouteTest extends TestCase
{
    public function testRegistrationRoutePreservesItsIdentifier(): void
    {
        $router = (string) file_get_contents(JEM_TEST_ROOT . '/site/router.php');

        self::assertMatchesRegularExpression(
            '/\$viewsWithId\s*=\s*\[[^]]*\'registration\'[^]]*\]/s',
            $router
        );
        self::assertStringContainsString("\$viewConfig->setKey('id');", $router);

        $noMenuRules = (string) file_get_contents(
            JEM_TEST_ROOT . '/site/services/JemNomenuRules.php'
        );

        $registrationRule = <<<'REGEX'
/case 'registration':.*?\$vars\['id'\] = \$id\[0\];.*?\$vars\['view'\] = 'registration';/s
REGEX;

        self::assertMatchesRegularExpression($registrationRule, $noMenuRules);
    }
}
