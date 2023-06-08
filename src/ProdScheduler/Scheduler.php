<?php

namespace Sv\Algorithm\ProdScheduler;

class Scheduler extends AbstractScheduler implements SchedulerInterface
{
    use SchedulerComputeTrait;

    public function __construct()
    {
    }
}
