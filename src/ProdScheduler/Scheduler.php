<?php

namespace Sv\Algorithm\ProdScheduler;

class Scheduler extends AbstractScheduler implements SchedulerInterface
{

    public function __construct(array $config)
    {
        $this->init($config);

        $this->schedule();
    }

    public function getSchedule(): array
    {
        return $this->getMonthSchedule();
    }

    public function getDaySchedule(int $timestamp): array
    {
        $monthSchedule = $this->getSchedule();
        $day = date('Y-m-d', $timestamp);
        $daySchedule = [];
        foreach($monthSchedule as $schedule) {
            $phasesReverse = $schedule['phases_reverse'];
            $phasesForward = $schedule['phases_forward'];
            $flag = true;
            foreach($phasesReverse as $p) {
                if ($p['start'] >= strtotime($day) && $p['start'] <= strtotime($day) + self::SCHEDULER_DAY_SECONDS) {
                    $daySchedule[] = $schedule;
                    $flag = false;
                    break;
                }
            }
            if ($flag) {
                foreach($phasesForward as $p) {
                    if ($p['start'] >= strtotime($day) && $p['start'] <= strtotime($day) + self::SCHEDULER_DAY_SECONDS) {
                        $daySchedule[] = $schedule;
                    }
                }
            }
        }

        return $daySchedule;
    }
}
