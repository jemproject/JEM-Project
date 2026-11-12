<?php

declare(strict_types=1);

/** Declare fixture fields without running unrelated table construction or event storage. */
final class ProgrammeParentTableFixture extends JemTableEvent
{
    public $id;
    public $dates;
    public $enddates;
    public $times;
    public $endtimes;
    public $locid;
}
