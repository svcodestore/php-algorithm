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
                        } else if (!empty($this->list[$k - 1]['phases_forward'])) {
                            $originStart = $start = $this->list[$k - 1]['phases_forward'][0]['end'];
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
                    $start = $this->phaseTimeWithRestDayCompute($originStart, $start);
                    $this->list[$k]['phases_reverse'][$i]['start'] = $start;

                    if ($itemPhase['out_time'] > 0) {
                        $end = $start + $itemPhase['out_time'];
                    } else {
                        $end = $start + $totalCost;
                        $end = $this->phaseTimeWithCalendarCompute($start, $end);
                        $end = $this->phaseTimeWithRestDayCompute($start, $end);
                        $this->list[$k]['phases_reverse'][$i]['end'] = $end;
                    }

                    $i--;
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
                            if ($prevItem['out_time'] > 0 && isset($prevItem['end'])) {
                                $originStart = $start = $prevItem['end'];
                            } else {
                                $originStart = $prevItem['start'];
                                $start = $originStart + $singleCost + $prevItem['dead_time'] + $prevItem['ahead_time'];
                            }
                        }

                        $start = $this->phaseTimeWithCalendarCompute($originStart, $start);
                        $start = $this->phaseTimeWithRestDayCompute($originStart, $start);
                        $this->list[$k]['phases_forward'][$i]['start'] = $start;

                        if ($itemPhase['out_time'] > 0) {
                            $end = $start + $itemPhase['out_time'];
                        } else {
                            $end = $start + $totalCost;
                            $end = $this->phaseTimeWithCalendarCompute($start, $end);
                            $end = $this->phaseTimeWithRestDayCompute($start, $end);
                            $this->list[$k]['phases_forward'][$i]['end'] = $end;
                        }
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
        $totalCost = $cost * $qty;

        return [(int)$singleCost, (int)$totalCost];
    }

    private function phaseTimeWithCalendarCompute(int $originStart, int &$start, bool $isReverse = false): int
    {
        $originStartDate = date(self::SCHEDULER_DATE_FORMAT, $originStart);

        $calendar = $this->getDayCalendar($originStart);
        if (!isset($calendar['dayStart'])) {
            $defaultCalendar = $this->getDefaultDayCalendar();
            $calendar['rest'] = $defaultCalendar['profile']['rest'];
            $calendar['dayStart'] = $defaultCalendar['profile']['dayStart'];
            $calendar['dayEnd'] = $defaultCalendar['profile']['dayEnd'];
            $calendar['dayDuration'] = $defaultCalendar['profile']['dayDuration'];
        }
        $dayStart = strtotime("{$originStartDate} {$calendar['dayStart']}");
        $dayEnd = strtotime("{$originStartDate} {$calendar['dayEnd']}");
        $dayDuration = $calendar['dayDuration'];
        $diff = abs($start - $originStart);


        if ($diff >= $dayDuration) {
            while ($diff > 0) {
                $dayCalendar = $this->getDayCalendar($originStart);
                if (isset($dayCalendar['dayDuration']) && $dayCalendar['dayDuration'] < $diff) {
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
            if (!isset($calendar['dayStart'])) {
                $defaultCalendar = $this->getDefaultDayCalendar();
                $calendar['rest'] = $defaultCalendar['profile']['rest'];
                $calendar['dayStart'] = $defaultCalendar['profile']['dayStart'];
                $calendar['dayEnd'] = $defaultCalendar['profile']['dayEnd'];
                $calendar['dayDuration'] = $defaultCalendar['profile']['dayDuration'];
            }
            $dayStart = strtotime("{$calendar['date']} {$calendar['dayStart']}");
            $dayEnd = strtotime("{$calendar['date']} {$calendar['dayEnd']}");
            $dayDuration = $calendar['dayDuration'];
            $originStartDate = date(self::SCHEDULER_DATE_FORMAT, $originStart);
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
                } else if ($diff > $t['duration']) {
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
            $prevCalendar = $this->getDayCalendar($start - self::SCHEDULER_DAY_SECONDS);
            if (!isset($prevCalendar['dayEnd'])) {
                $defaultCalendar = $this->getDefaultDayCalendar();
                $prevCalendar['rest'] = $defaultCalendar['profile']['rest'];
                $prevCalendar['dayStart'] = $defaultCalendar['profile']['dayStart'];
                $prevCalendar['dayEnd'] = $defaultCalendar['profile']['dayEnd'];
                $prevCalendar['dayDuration'] = $defaultCalendar['profile']['dayDuration'];
            }
            $prevDayEnd = strtotime("{$prevCalendar['date']} {$prevCalendar['dayEnd']}");
            if ($isReverse) {
                $diff = abs($dayStart - $start);
                $prevStart = $prevDayEnd - $diff;

                $start = $this->phaseTimeWithCalendarCompute($prevDayEnd, $prevStart, $isReverse);
            } else {
                $diff = $start - $prevDayEnd;
                $start = $dayStart + $diff;
                $start = $this->phaseTimeWithCalendarCompute($dayStart, $start, $isReverse);
            }
        }

        if ($start > $dayEnd) {
            $nextCalendar = $this->getDayCalendar($start + self::SCHEDULER_DAY_SECONDS);
            if (isset($nextCalendar['dayStart'])) {
                $nextDayStart = strtotime("{$nextCalendar['date']} {$nextCalendar['dayStart']}");
            } else {
                $s = date('H:i:s', $dayStart);
                $nextDayStart = strtotime("{$nextCalendar['date']} {$s}");
            }

            if ($isReverse) {
                $diff = $start - $dayEnd;
                $start = $dayStart - $diff;

                $start = $this->phaseTimeWithCalendarCompute($dayStart, $start, $isReverse);
            } else {
                $diff = $start - $dayEnd;
                $nextStart = $nextDayStart + $diff;

                $start = $this->phaseTimeWithCalendarCompute($nextDayStart, $nextStart, $isReverse);
            }
        }

        return $start;
    }

    private function phaseTimeWithRestDayCompute(int $start, int &$time, bool $isReverse = false): int
    {
        $calendar = $this->getMonthCalendar();

        foreach ($calendar as $c) {
            if (strtotime($c['date']) <= $time && strtotime($c['date']) >= $start && $c['is_rest'] === 1) {
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
