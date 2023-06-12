<?php

namespace Sv\Algorithm\ProdScheduler;

trait SchedulerComputeTrait
{
    public array $scheduledList = [];

    use SchedulerConfigTrait;

    public function compute()
    {
    }

    private function initialStartTimeCompute(): int
    {
        return 0;
    }

    private function adjustPhasePosition(bool $isAdjust = false)
    {
    }

    private function phaseCostTime(): int
    {
        return 0;
    }

    private function phaseStartedTimeCompute(bool $isReverse = false): int
    {
        return 0;
    }

    private function phaseCompletedTimeCompute(): int
    {
        return 0;
    }
}
