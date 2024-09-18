<?php

namespace Sv\Algorithm\ProdScheduler;

trait SchedulerConfigTrait
{
    public const SCHEDULER_DAY_SECONDS = 60 * 60 * 24; // 86400
    protected const SCHEDULER_DATETIME_FORMAT = 'Y-m-d H:i:s';
    protected const SCHEDULER_DATE_FORMAT = 'Y-m-d';

    public string $year = '';
    public string $month = '';
    public int $group = 1;
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
    public int $computeDirection = 1;
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
    public bool $isMaterialApply = false;

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
        $this->isMaterialApply = $config['isMaterialApply'];
        $this->ISDT = $config['start'];
        $this->ISTS = strtotime($config['start']);
        $this->initialPhase = $config['initialPhase'];
        $this->listPhase = $config['listPhase'];
        $this->EDQ = $config['edq'];
        $this->MCTC = $config['mctc'];
        $this->list = $this->parseList($config['list']);
        $this->defaultDayCalendar = $this->parseCalendar($config['calendar'] ?? '');
        $this->monthCalendar = $this->parseCalendars($config['monthCalendar']);
        $this->nextMonthCalendar = $this->parseCalendars($config['nextMonthCalendar']);
        $this->prevMonthCalendar = $this->parseCalendars($config['prevMonthCalendar']);

        $this->initialStartTimeCompute();
    }

    private function parseCalendars(array $calendar): array
    {
        if (!empty($calendar)) {
            foreach ($calendar as $k => $c) {
                $calendar[$k]['profile'] = $this->parseCalendar($c['profile'])['profile'];
            }
        }

        return $calendar;
    }

    private function parseCalendar(string $calendarStr): array
    {
        $calendar = [];
        try {
            $calendar = json_decode($calendarStr, true);
            $c = $calendar['profile'];
            $c['profile']['date'] = $calendar['date'] ?? '';

            $c['profile']['rest'] = [
                [
                    "end" => $c['profile']['times'][1]['start'],
                    "name" => "上午",
                    "start" => $c['profile']['times'][0]['end'],
                    "duration" => 5400
                ],
                [
                    "end" => $c['profile']['times'][2]['start'],
                    "name" => "下午",
                    "start" => $c['profile']['times'][1]['end'],
                    "duration" => 1800
                ],
            ];

            return $c;
        } catch (\Throwable $th) {
            return [
                'profile' => [
                    "name" => "白班",
                    "dayStart" => "07:30",
                    "dayEnd" => "20:30",
                    "dayDuration" => 14400 + 14400 + 10800,
                    "times" => [
                        [
                            "end" => "11:30",
                            "name" => "上午",
                            "start" => "07:30",
                            "duration" => 14400
                        ],
                        [
                            "end" => "17:00",
                            "name" => "下午",
                            "start" => "13:00",
                            "duration" => 14400,
                            "interval" => 5400
                        ],
                        [
                            "end" => "20:30",
                            "name" => "晚上",
                            "start" => "17:30",
                            "duration" => 10800,
                            "interval" => 1800
                        ]
                    ],
                    "rest" => [
                        [
                            "end" => "13:00",
                            "name" => "上午",
                            "start" => "11:30",
                            "duration" => 5400
                        ],
                        [
                            "end" => "17:30",
                            "name" => "下午",
                            "start" => "17:00",
                            "duration" => 1800
                        ],
                    ]
                ]
            ];
        }
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

            if (empty($reversePhase) && empty($forwardPhase)) {
                unset($list[$k]);
            }
        }

        return array_values($list);
    }

    private function parsePhase(array $phase): array
    {
        if (!empty($phase)) {
            $reversePhase = [];
            $forwardPhase = [];
            $costTime = [];

            foreach ($phase as $p) {
                $costTime[] = $p['cost_time'];

                if ($p['code_id'] == $this->initialPhase) {
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
                        if ($this->isMaterialApply && count($forwardPhase) > 0) {
                            continue;
                        }
                        $forwardPhase[] = $p;
                    }
                }
            }

            $maxCostTime = max($costTime);

            return [$maxCostTime, $reversePhase, $forwardPhase];
        }

        return [[], [], []];
    }

    private function getDayCalendarStartTime(array $calendar): string
    {
        $dayCalendarStartTime = '';
        if (isset($calendar['profile']) && isset($calendar['profile']['times']) && count($calendar['profile']['times']) > 0) {
            $profile = $calendar['profile']['times'][0];

            if (isset($profile['start'])) {
                $dayCalendarStartTime = $profile['start'];
            } elseif (isset($profile['times']) && count($profile['times']) > 0) {
                if ($profile['times'][0]['start']) {
                    $dayCalendarStartTime = $profile['times'][0]['start'];
                }
            }
        }
        if (empty($dayCalendarStartTime)) {
            $defaultDayCalendar = $this->getDefaultDayCalendar();
            $dayCalendarStartTime = $this->getDayCalendarStartTime($defaultDayCalendar);
        }

        return $dayCalendarStartTime;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function getGroup(): int
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

            $c['profile']['date'] = $date;

            return $c['profile'];
        }

        return $this->defaultDayCalendar['profile'];
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
