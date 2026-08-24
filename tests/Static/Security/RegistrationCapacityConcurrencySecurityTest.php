<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class RegistrationCapacityConcurrencySecurityTest extends TestCase
{
    public function testCapacityWriterLocksBeforeRecalculatingAndWriting(): void
    {
        $service = $this->read('/site/classes/registrationservice.class.php');
        $save = $this->method($service, 'save');
        $locked = $this->method($service, 'saveLocked');
        $capacity = $this->method($service, 'applyCapacityPolicy');
        $registration = $this->method($service, 'loadForUpdate');
        $event = $this->method($service, 'lockEvent');

        self::assertTrue(strpos($save, 'transactionStart(') < strpos($save, 'lockEvent('));
        self::assertTrue(strpos($save, 'lockEvent(') < strpos($save, 'loadForUpdate('));
        self::assertTrue(strpos($locked, 'applyCapacityPolicy(') < strpos($locked, "updateObject('#__jem_register'"));
        self::assertTrue(strpos($locked, 'applyCapacityPolicy(') < strpos($locked, "insertObject('#__jem_register'"));
        self::assertStringContainsString("' FOR UPDATE'", $registration);
        self::assertStringContainsString("' FOR UPDATE'", $event);
        self::assertStringContainsString('transactionCommit()', $save);
        self::assertStringContainsString('transactionRollback()', $save);
        self::assertStringContainsString('SUM(GREATEST(', $capacity);
        self::assertStringContainsString("status') . ' = 1'", $capacity);
        self::assertStringContainsString("waiting') . ' = 0'", $capacity);
        self::assertStringContainsString("id') . ' <> '", $capacity);
    }

    public function testSeriesLocksAllEventsInStableOrderAndCommitsOnce(): void
    {
        $service = $this->read('/site/classes/registrationservice.class.php');
        $batch = $this->method($service, 'saveMany');
        $lockLoop = strpos($batch, 'foreach ($eventIds as $eventId)');
        $writeLoop = strpos($batch, 'foreach ($normalisedRows as $row)', $lockLoop);

        self::assertTrue(strpos($batch, 'sort($eventIds, SORT_NUMERIC)') < strpos($batch, 'transactionStart('));
        self::assertNotFalse($writeLoop);
        self::assertTrue($lockLoop < $writeLoop);
        self::assertTrue(strpos($batch, 'lockEvent(') < strpos($batch, 'saveLocked('));
        self::assertTrue(strpos($batch, 'saveLocked(') < strpos($batch, 'transactionCommit()'));
        self::assertStringContainsString('transactionRollback()', $batch);
    }

    public function testFrontendUsesTheAtomicBatchForNewAndExistingRegistrations(): void
    {
        $model = $this->read('/site/models/event.php');
        $registration = $this->method($model, 'userregister');
        $single = $this->method($model, '_doRegister');

        self::assertStringContainsString('$pending[] = $row;', $registration);
        self::assertStringContainsString('JemRegistrationService($this->_db))->saveMany(', $registration);
        self::assertStringNotContainsString('$this->_doRegister(', $registration);
        self::assertStringContainsString('JemRegistrationService($this->_db))->save(', $single);
        self::assertStringNotContainsString("updateObject('#__jem_register'", $single);
        self::assertStringNotContainsString("insertObject('#__jem_register'", $single);
        self::assertStringNotContainsString('$status != $oldstat', $single);
    }

    private function read(string $path): string
    {
        $file = JEM_TEST_ROOT . $path;
        self::assertFileExists($file);

        return (string) file_get_contents($file);
    }

    private function method(string $source, string $name): string
    {
        $start = strpos($source, 'function ' . $name . '(');
        self::assertNotFalse($start, 'Missing method ' . $name);
        $brace = strpos($source, '{', $start);
        self::assertNotFalse($brace);
        $depth = 0;

        for ($position = $brace, $length = strlen($source); $position < $length; $position++) {
            if ($source[$position] === '{') {
                $depth++;
            } elseif ($source[$position] === '}' && --$depth === 0) {
                return substr($source, $start, $position - $start + 1);
            }
        }

        self::fail('Unclosed method ' . $name);
    }
}
