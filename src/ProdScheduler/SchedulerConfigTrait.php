<?php

namespace Sv\Algorithm\ProdScheduler;

trait SchedulerConfigTrait
{
    public const SCHEDULER_DAY_SECONDS = 60 * 60 * 24; // 86400
    protected const SCHEDULER_DATETIME_FORMAT = 'Y-m-d H:i:s';
    protected const SCHEDULER_DATE_FORMAT = 'Y-m-d';

    public string $year = '';
    public string $month = '';
    public AssemblelyGroups $group = AssemblelyGroups::First;
    public string $groupName = '';
    public array $list = [];
    public array $listPhase = [];
    public array $defaultDayCalendar = [];
    // Equal division quantity
    public int $EDQ = 0;
    // Max cost time compute
    public bool $MCTC = false;
    // Sup phase compute first
    public bool $SPCF = false;
    public string $initialPhase = '';
    public array $initialPhaseInfo = [];
    public bool $singlePhase = false;
    // Initial schedule datetime
    public string $ISDT = '';
    // Initial schedule timestamp
    public int $ISTS = 0;
    public ComputeDirection $computeDirection = ComputeDirection::Forward;
    public bool $isUseCalendar = false;
    public bool $isUseTPMCalendar = false;
    // is use multiple shifts compute
    public bool $isUseMSC = false;
    public array $monthCalendar = [];
    public array $nextMonthCalendar = [];
    public array $prevMonthCalendar = [];
    public array $dayCalendar = [];
    public array $nextDayCalendar = [];
    public array $prevDayCalendar = [];
    public array $config = [];

    public function init(array $config): void
    {
        $this->parseConfig($config);
    }

    private function parseConfig(array $config)
    {
        $this->config = $config;
        $this->year = $config['year'];
        $this->month = $config['month'];
        $this->groupName = $config['groupName'];
        $this->computeDirection = $config['computeDirection'];
        $this->ISDT = $config['start'];
        $this->ISTS = strtotime($config['start']);
        $this->list = $this->parseList($config['list']);
        $this->listPhase = $config['listPhase'];
        $this->defaultDayCalendar = $config['calendar'];
        $this->EDQ = $config['edq'];
        $this->monthCalendar = $config['monthCalendar'];
        $this->nextMonthCalendar = $config['nextMonthCalendar'];
        $this->prevMonthCalendar = $config['prevMonthCalendar'];
        $this->initialPhase = $config['initialPhase'];

        $this->initialStartTimeCompute();
    }

    private function parseCalendar(array $calendar): array
    {
        return $calendar;
    }

    private function parseList(array $list): array
    {
        foreach ($list as $k => $item) {
            $itemCode = $item['item_code'];
            $phase = array_filter($this->listPhase, function ($e) use ($itemCode) {
                return $e['code'] === $itemCode;
            });

            list($maxCostTime, $reversePhase, $forwardPhase) = $this->parsePhase($phase);
            $list[$k]['phase_max_cost'] = $maxCostTime;
            $list[$k]['phases_reverse'] = $reversePhase;
            $list[$k]['phases_forward'] = $forwardPhase;
        }

        return $list;
    }

    private function parsePhase(array $phase): array
    {
        if (!empty($phase)) {
            $reversePhase = [];
            $forwardPhase = [];
            $costTime = [];

            foreach ($phase as $p) {
                $costTime[] = $p['cost_time'];

                if ($p['code_id'] === $this->initialPhase) {
                    array_push(
                        $reversePhase,
                        ...array_filter(
                            $phase,
                            function ($e) {
                                return $e['master'] === 0;
                            }
                        )
                    );

                    $reversePhase[] = $p;
                } else if ($p['master'] === 1) {
                    if ($p['code_id'] < $this->initialPhase) {
                        $reversePhase[] = $p;
                    } else {
                        $forwardPhase[] = $p;
                    }
                }
            }

            $maxCostTime = max($costTime);

            return [$maxCostTime, $reversePhase, $forwardPhase];
        }

        return [];
    }

    private function getDayCalendarStartTime(array $calendar): string
    {
        $dayCalendarStartTime = '';
        if (isset($calendar['profile']) && count($calendar['profile']) > 0) {
            $profile = $calendar['profile'][0];
            if (isset($profile['times']) && count($profile['times']) > 0) {
                if ($profile['times'][0]['start']) {
                    $dayCalendarStartTime = $profile['times'][0]['start'];
                }
            }
        }

        if (empty($dayCalendarStartTime)) {
            $defaultDayCalendar = $this->getDefaultDayCalendar();
            if (isset($defaultDayCalendar['profile']) && count($defaultDayCalendar['profile']) > 0) {
                $profile = $defaultDayCalendar['profile'][0];
                if (isset($profile['times']) && count($profile['times']) > 0) {
                    if ($profile['times'][0]['start']) {
                        $dayCalendarStartTime = $profile['times'][0]['start'];
                    }
                }
            }
        }

        return $dayCalendarStartTime;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function getGroup(): AssemblelyGroups
    {
        return $this->group;
    }

    public function getGroupName(): string
    {
        return $this->groupName;
    }

    public function getDefaultDayCalendar(): array
    {
        return $this->defaultDayCalendar;
    }

    public function getEDQ(): int
    {
        return $this->EDQ;
    }

    public function getMCTC(): bool
    {
        return $this->MCTC;
    }

    public function getSPCF(): bool
    {
        return $this->SPCF;
    }

    public function getISDT(): string
    {
        return $this->ISDT;
    }

    public function getISTS(): int
    {
        return $this->ISTS;
    }

    public function getSinglePhase(): bool
    {
        return $this->singlePhase;
    }

    public function getUseMSC(): bool
    {
        return $this->isUseMSC;
    }

    public function getUseCalendar(): bool
    {
        return $this->isUseCalendar;
    }

    public function getUseTPMS(): bool
    {
        return $this->isUseTPMCalendar;
    }

    public function getInitialPhase(): string
    {
        return $this->initialPhase;
    }

    public function getInitialPhaseInfo(): array
    {
        return $this->initialPhaseInfo;
    }

    public function setDayCalendar(array $calendar): void
    {
        $this->dayCalendar = $calendar;
    }

    public function getDayCalendar(int $ts = 0): array
    {
        if ($ts) {
            $date = date(self::SCHEDULER_DATE_FORMAT, $ts);
            $c = [];
            foreach ($this->monthCalendar as $calendar) {
                if ($calendar['date'] === $date) {
                    $c = $calendar;
                    break;
                }
            }

            if (empty($c)) {
                foreach ($this->nextMonthCalendar as $calendar) {
                    if ($calendar['date'] === $date) {
                        $c = $calendar;
                        break;
                    }
                }
            }

            if (empty($c)) {
                foreach ($this->prevMonthCalendar as $calendar) {
                    if ($calendar['date'] === $date) {
                        $c = $calendar;
                        break;
                    }
                }
            }

            return $c;
        }

        return $this->dayCalendar;
    }

    public function getNextDayCalendar(): array
    {
        return $this->nextDayCalendar;
    }

    public function getPreviousDayCalendar(): array
    {
        return $this->previousDayCalendar;
    }

    public function getMonthCalendar(): array
    {
        return $this->monthCalendar;
    }

    public function getNextMonthCalendar(): array
    {
        return $this->nextMonthCalendar;
    }

    public function getPreviousMonthCalendar(): array
    {
        return $this->previousMonthCalendar;
    }
}
