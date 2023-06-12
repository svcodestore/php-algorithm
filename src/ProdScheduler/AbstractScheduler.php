<?php

namespace Sv\Algorithm\ProdScheduler;

abstract class AbstractScheduler
{
    use SchedulerComputeTrait;

    public function schedule(): void
    {
        $this->compute();
    }

    public function getMonthSchedule(): array
    {
        return $this->scheduledList;
    }
}
