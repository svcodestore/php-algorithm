<?php

namespace Sv\Algorithm\ProdScheduler;

class Scheduler extends AbstractScheduler implements SchedulerInterface
{

    public function __construct(array $config)
    {
        $this->init($config);
    }

    public function getSchedule(): array
    {
        return $this->getMonthSchedule();
    }

    public function getDaySchedule(int $timestamp): array
    {
        return $this->getSchedule();
    }
}
