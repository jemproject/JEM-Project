<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class RegistrationAccessSecurityTest extends TestCase
{
    public function testFrontendRegistrationUsesTheCentralPolicyBeforeWriting(): void
    {
        $model = (string) file_get_contents(JEM_TEST_ROOT . '/site/models/event.php');
        $method = $this->method($model, 'userregister');

        self::assertStringContainsString('JemRegistrationAccessPolicy::decide(', $method);
        self::assertStringContainsString('getAuthoritativeRegistrationEvents(', $method);
        self::assertStringContainsString('getOwnedCancellationEvent(', $method);
        $write = strpos($method, 'saveMany(');
        if ($write === false) {
            $write = strpos($method, '_doRegister(');
        }
        self::assertNotFalse($write);
        self::assertLessThan($write, strpos($method, 'JemRegistrationAccessPolicy::decide('));
    }

    public function testHiddenCancellationLoaderRequiresTheUsersRegistrationAndSelectsNoContent(): void
    {
        $model = (string) file_get_contents(JEM_TEST_ROOT . '/site/models/event.php');
        $method = $this->method($model, 'getOwnedCancellationEvent');

        self::assertStringContainsString("#__jem_register", $method);
        self::assertStringContainsString('r.uid = ', $method);
        self::assertStringNotContainsString('title', $method);
        self::assertStringNotContainsString('introtext', $method);
        self::assertStringNotContainsString('fulltext', $method);
    }

    public function testEverySeriesOccurrenceIsReloadedThroughTheEventAccessModel(): void
    {
        $model = (string) file_get_contents(JEM_TEST_ROOT . '/site/models/event.php');
        $method = $this->method($model, 'getAuthoritativeRegistrationEvents');

        self::assertStringContainsString('foreach ($candidates as $candidate)', $method);
        self::assertStringContainsString('$this->getItem($candidateId)', $method);
        self::assertStringContainsString("unset(\$this->_item[\$candidateId])", $method);
    }

    public function testPolicyIsLoadedForAllFrontendEntryPoints(): void
    {
        $factory = (string) file_get_contents(JEM_TEST_ROOT . '/site/factory.php');

        self::assertStringContainsString('classes/registrationaccesspolicy.class.php', $factory);
    }

    private function method(string $source, string $name): string
    {
        $start = strpos($source, 'function ' . $name . '(');
        self::assertNotFalse($start, 'Missing method ' . $name);

        $brace = strpos($source, '{', $start);
        self::assertNotFalse($brace);
        $depth = 0;
        $length = strlen($source);

        for ($position = $brace; $position < $length; $position++) {
            if ($source[$position] === '{') {
                $depth++;
            } elseif ($source[$position] === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($source, $start, $position - $start + 1);
                }
            }
        }

        self::fail('Unclosed method ' . $name);
    }
}
