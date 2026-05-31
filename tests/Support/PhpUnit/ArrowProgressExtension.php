<?php
/**
 * PHPUnit extension that replaces dot progress with a compact arrow bar.
 */

declare(strict_types=1);

namespace JemProject\Tests\Support\PhpUnit;

use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;

final class ArrowProgressExtension implements Extension
{
    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        $printer = new ArrowProgressPrinter(
            (int) ($parameters->has('width') ? $parameters->get('width') : 28)
        );

        $facade->replaceProgressOutput();
        $facade->registerSubscribers(
            new ArrowProgressStartedSubscriber($printer),
            new ArrowProgressFinishedSubscriber($printer)
        );
    }
}
