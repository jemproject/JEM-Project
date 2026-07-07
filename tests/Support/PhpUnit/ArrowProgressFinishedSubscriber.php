<?php
/**
 * Updates the custom progress bar after each completed test.
 */

declare(strict_types=1);

namespace JemProject\Tests\Support\PhpUnit;

use PHPUnit\Event\Test\Finished;
use PHPUnit\Event\Test\FinishedSubscriber;

final readonly class ArrowProgressFinishedSubscriber implements FinishedSubscriber
{
    public function __construct(private ArrowProgressPrinter $printer)
    {
    }

    public function notify(Finished $event): void
    {
        $this->printer->advance();
    }
}
