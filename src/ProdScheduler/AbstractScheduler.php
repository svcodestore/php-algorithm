<?php

namespace Sv\Algorithm\ProdScheduler;

abstract class AbstractScheduler implements SchedulerInterface
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
