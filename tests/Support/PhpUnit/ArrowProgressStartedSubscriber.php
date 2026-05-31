<?php
/**
 * Starts the custom progress bar when PHPUnit knows the test count.
 */

declare(strict_types=1);

namespace JemProject\Tests\Support\PhpUnit;

use PHPUnit\Event\TestRunner\ExecutionStarted;
use PHPUnit\Event\TestRunner\ExecutionStartedSubscriber;

final readonly class ArrowProgressStartedSubscriber implements ExecutionStartedSubscriber
{
    public function __construct(private ArrowProgressPrinter $printer)
    {
    }

    public function notify(ExecutionStarted $event): void
    {
        $this->printer->start($event->testSuite()->count());
    }
}
