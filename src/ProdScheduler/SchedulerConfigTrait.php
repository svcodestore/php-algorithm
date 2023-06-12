<?php

namespace Sv\Algorithm\ProdScheduler;

trait SchedulerConfigTrait
{
    public const SCHEDULER_DAY_SECONDS = 60 * 60 * 24; // 86400
    protected const SCHEDULER_DATETIME_FORMAT = 'Y-m-d H:i:s';
    protected const SCHEDULER_DATE_FORMAT = 'Y-m-d';

    public string $year;
    public string $month;
    public AssemblelyGroups $group;
    public string $groupName;
    public array $defaultDayCalendar;
    // Equal division quantity
    public int $EDQ;
    // Max cost time compute
    public bool $MCTC;
    // Sup phase compute first
    public bool $SPCF;
    public string $initialPhase;
    public bool $singlePhase;
    // Initial schedule datetime
    public string $ISDT;
    // Initial schedule timestamp
    public int $ISTS;
    public bool $isUseCalendar;
    public bool $isUseTPMCalendar;
    // is use multiple shifts compute
    public bool $isUseMSC;
    public array $monthCalendar;
    public array $nextMonthCalendar;
    public array $prevMonthCalendar;
    public array $dayCalendar;
    public array $nextDayCalendar;
    public array $prevDayCalendar;

    public function init(array $config): void
    {
        $this->parseConfig($config);
    }

    private function parseConfig(array $config)
    {
    }

    public function getConfig(): array
    {
        return [];
    }

    public function getGroup(): AssemblelyGroups
    {
        return $this->group;
    }

    public function getGroupName(AssemblelyGroups $id): string
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
        return [];
    }

    public function getDayCalendar(): array
    {
        return [];
    }

    public function getNextDayCalendar(): array
    {
        return [];
    }

    public function getPreviousDayCalendar(): array
    {
        return [];
    }

    public function getMonthCalendar(): array
    {
        return [];
    }

    public function getNextMonthCalendar(): array
    {
        return [];
    }

    public function getPreviousMonthCalendar(): array
    {
        return [];
    }
}
