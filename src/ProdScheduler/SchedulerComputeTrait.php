<?php

namespace Sv\Algorithm\ProdScheduler;

trait SchedulerComputeTrait
{
    public array $scheduledList = [];

    use SchedulerConfigTrait;

    public function compute()
    {
        foreach ($this->list as $k => $item) {
            $itemQty = $item['item_qty'];
            $itemPhaseMaxCostTime = $this->list[$k]['phase_max_cost'];
            $reverseComputePhases = $this->list[$k]['phases_reverse'];
            $forwardComputePhases = $this->list[$k]['phases_forward'];

            $reverseComputePhasesCnt =  count($reverseComputePhases);
            if (!empty($reverseComputePhasesCnt)) {
                $i = $reverseComputePhasesCnt - 1;

                while ($i > -1) {
                    $itemPhase = $reverseComputePhases[$i];
                    $workerNum = $itemPhase['worker_num'];
                    $costTime = $itemPhase['cost_time'];

                    list($singleCost, $totalCost) = $this->getPhaseCostTime($itemQty, $itemPhaseMaxCostTime, $costTime, $workerNum);

                    if ($i === count($reverseComputePhases) - 1) {
                        if ($k === 0) {
                            $originStart = $start = $this->ISTS;
                        } else {
                            $originStart = $start = $this->list[$k - 1]['phases_forward'][0]['start'];
                        }
                    } else {
                        $nextPhase = $this->list[$k]['phases_reverse'][$i + 1];
                        $originStart = $start = $nextPhase['start'];
                        $start -= ($itemPhase['dead_time'] + $itemPhase['ahead_time']);
                        if ($itemPhase['out_time'] > 0) {
                            $start -= $itemPhase['out_time'];
                        } else {
                            $start -= $singleCost;
                        }
                    }

                    $start = $this->phaseTimeWithCalendarCompute($originStart, $start, true);
                    $start = $this->phaseTimeWithRestDayCompute($start);
                    $this->list[$k]['phases_reverse'][$i]['start'] = $start;

                    if ($itemPhase['out_time'] > 0) {
                        $end = $start + $itemPhase['out_time'];
                    } else {
                        $end = $start + $totalCost;
                        $end = $this->phaseTimeWithCalendarCompute($start, $end);
                        $end = $this->phaseTimeWithRestDayCompute($end);
                        $this->list[$k]['phases_reverse'][$i]['end'] = $end;
                    }

                    $i--;
                }
            }

            $initialPhase = $this->list[$k]['phases_reverse'][$reverseComputePhasesCnt - 1];
            if (isset($initialPhase)) {
                foreach ($forwardComputePhases as $i => $itemPhase) {
                    $workerNum = $itemPhase['worker_num'];
                    $costTime = $itemPhase['cost_time'];

                    list($singleCost, $totalCost) = $this->getPhaseCostTime($itemQty, $itemPhaseMaxCostTime, $costTime, $workerNum);
                    if ($i === 0) {
                        $originStart = $initialPhase['start'];
                        $start = $originStart + $initialPhase['dead_time'] + $initialPhase['ahead_time'];
                    } else {
                        $prevItem = $this->list[$k]['phases_forward'][$i - 1];
                        if ($prevItem['out_time'] > 0) {
                            $originStart = $start = $prevItem['end'];
                        } else {
                            $originStart = $prevItem['start'];
                            $start = $originStart + $prevItem['dead_time'] + $prevItem['ahead_time'];
                        }
                    }

                    $start = $this->phaseTimeWithCalendarCompute($originStart, $start);
                    $start = $this->phaseTimeWithRestDayCompute($start);
                    $this->list[$k]['phases_forward'][$i]['start'] = $start;

                    if ($itemPhase['out_time'] > 0) {
                        $end = $start + $itemPhase['out_time'];
                    } else {
                        $end = $start + $totalCost;
                        $end = $this->phaseTimeWithCalendarCompute($start, $end);
                        $end = $this->phaseTimeWithRestDayCompute($end);
                        $this->list[$k]['phases_forward'][$i]['end'] = $end;
                    }
                }
            }
        }

        $this->scheduledList = $this->list;
    }

    private function initialStartTimeCompute(): int
    {
        $initialScheduleDate = date(self::SCHEDULER_DATE_FORMAT, $this->ISTS);
        $dayCalendar = $this->getDayCalendar($this->ISTS);

        if ($this->computeDirection === 1) {
            if (empty($dayCalendar)) {
                $dayCalendarStartTime = $this->getDayCalendarStartTime($this->defaultDayCalendar);

                $this->ISDT = $initialScheduleDate . " " . $dayCalendarStartTime;
            } else {
                $monthCalendar = $this->getMonthCalendar();
                foreach ($monthCalendar as $k => $day) {
                    if ($initialScheduleDate === $day['date']) {
                        if ($day['is_rest'] === 1) {
                            if ($k !== count($monthCalendar)) {
                                $nextDayCalendar = $monthCalendar[$k + 1];
                                if ($nextDayCalendar['is_rest'] === 1) {
                                    continue;
                                } else {
                                    $nextDayCalendarDate = $nextDayCalendar['date'];
                                    $nextDayCalendarStartTime = $this->getDayCalendarStartTime($nextDayCalendar);

                                    $this->ISDT = $nextDayCalendarDate . " " . $nextDayCalendarStartTime;
                                }
                            }
                        } else {
                            $dayCalendarDate = $day['date'];
                            $dayCalendarStartTime = $this->getDayCalendarStartTime($day);

                            $this->ISDT = $dayCalendarDate . " " . $dayCalendarStartTime;
                        }
                    }
                }
            }
        }

        $this->ISTS = strtotime($this->ISDT);

        return $this->ISTS;
    }

    private function getPhaseCostTime(int $qty, int $maxCostTime, int $costTime, int $workerNum): array
    {
        $cost = 0;
        if ($costTime > 0) {
            if ($this->getMCTC()) {
                $cost = $maxCostTime / $workerNum;
            } else {
                $cost = $costTime / $workerNum;
            }
        }

        $singleCost = $cost * $this->getEDQ();
        $totalCost = $qty * $this->getEDQ();

        return [(int)$singleCost, (int)$totalCost];
    }

    private function phaseTimeWithCalendarCompute(int $originStart, int &$start, bool $isReverse = false): int
    {
        $originStartDate = date(self::SCHEDULER_DATE_FORMAT, $originStart);

        $calendar = $this->getDayCalendar($originStart);
        $dayStart = $calendar['dayStart'];
        $dayEnd = $calendar['dayEnd'];
        $dayDuration = $calendar['dayDuration'];
        $diff = $start - $originStart;
        if ($diff >= $dayDuration) {
            while ($diff > 0) {
                $dayCalendar = $this->getDayCalendar($originStart);
                if ($dayCalendar['dayDuration'] < $diff) {
                    $diff -= $dayCalendar['dayDuration'];
                    if ($isReverse) {
                        $originStart -= self::SCHEDULER_DAY_SECONDS;
                    } else {
                        $originStart += self::SCHEDULER_DAY_SECONDS;
                    }
                } else {
                    break;
                }
            }

            if ($isReverse) {
                $start = $originStart - $diff;
            } else {
                $start = $originStart + $diff;
            }

            $calendar = $this->getDayCalendar($start);
            $dayStart = $calendar['dayStart'];
            $dayEnd = $calendar['dayEnd'];
            $dayDuration = $calendar['dayDuration'];
        }

        if ($start >= $dayStart && $start <= $dayEnd) {
            $times = $calendar['rest'];
            foreach ($times as $t) {
                $restStart = strtotime("{$originStartDate} {$t['start']}");
                $restEnd = strtotime("{$originStartDate} {$t['end']}");

                if ($start >= $restStart && $start < $restEnd) {
                    if ($isReverse) {
                        $start -= $t['duration'];
                    } else {
                        $start += $t['duration'];
                    }
                } else {
                    $cycle = 10 * 60;
                    $cycleCount = ceil($diff / $cycle);
                    $_start = $originStart;
                    for ($i = 1; $i <= $cycleCount; $i++) {
                        if ($isReverse) {
                            $_start -= $cycle * $i;

                            if ($_start >= $restStart && $_start < $restEnd) {
                                $start -= $t['duration'];
                                break;
                            }
                        } else {
                            $_start += $cycle * $i;

                            if ($_start >= $restStart && $_start < $restEnd) {
                                $start += $t['duration'];
                                break;
                            }
                        }
                    }
                }
            }
        }

        if ($start < $dayStart) {
            if ($isReverse) {
                $diff = $dayStart - $start;

                $prevCalendar = $this->getDayCalendar($start - self::SCHEDULER_DAY_SECONDS);
                $prevDayStart = $prevCalendar['dayStart'];
                $prevDayEnd = $prevCalendar['dayEnd'];
                $prevStart = $prevDayEnd - $diff;

                $start = $this->phaseTimeWithCalendarCompute($prevDayEnd, $prevStart, $isReverse);
            } else {
                $prevCalendar = $this->getDayCalendar($start - self::SCHEDULER_DAY_SECONDS);
                $diff = $start - $prevCalendar['dayEnd'];
                $dayStart = $calendar['dayStart'];
                $start = $dayStart + $diff;
                $start = $this->phaseTimeWithCalendarCompute($dayStart, $start, $isReverse);
            }
        }

        if ($start > $dayEnd) {
            if ($isReverse) {
                $nextCalendar = $this->getDayCalendar($start + self::SCHEDULER_DAY_SECONDS);
                $nextDayStart = $nextCalendar['dayStart'];
                $diff = $start - $dayEnd;
                $start = $nextDayStart + $diff;

                $start = $this->phaseTimeWithCalendarCompute($nextDayStart, $start, $isReverse);
            } else {
                $diff = $start - $dayEnd;

                $nextCalendar = $this->getDayCalendar($start + self::SCHEDULER_DAY_SECONDS);
                $nextDayStart = $nextCalendar['dayStart'];
                $nextDayEnd = $nextCalendar['dayEnd'];
                $nextStart = $nextDayStart + $diff;

                $start = $this->phaseTimeWithCalendarCompute($nextDayStart, $nextStart, $isReverse);
            }
        }

        return $start;
    }

    private function phaseTimeWithRestDayCompute(int &$time, bool $isReverse = false): int
    {
        $calendar = $this->getMonthCalendar();

        foreach ($calendar as $c) {
            if (strtotime($c['date']) < $time && $c['is_rest'] === 1) {
                if ($isReverse) {
                    $time -= self::SCHEDULER_DAY_SECONDS;
                } else {
                    $time += self::SCHEDULER_DAY_SECONDS;
                }
            }
        }

        return $time;
    }
}
