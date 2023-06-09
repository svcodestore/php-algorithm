<?php

namespace Sv\Algorithm\ProdScheduler;

enum AssemblelyGroups: int
{
    case First = 1;
    case Second = 2;
    case Third = 3;
    case Fourth = 4;
    case Fifth = 5;
    case Sixth = 6;
    case Seventh = 7;
    case Eighth = 8;
    case Ninth = 9;
    case Tenth = 10;
}

interface SchedulerInterface
{
    public function init(): void;

    public function schedule(): bool;

    // =============> output result <============ \\
    public function getDaySchedule(): array;

    public function getNextDaySchedule(): array;

    public function getPreviousDaySchedule(): array;

    public function getMonthSchedule(): array;

    public function getNextMonthSchedule(): array;

    public function getPreviousMonthSchedule(): array;

    public function getSchedule(): array;
    // =============> output result <============ \\

    // =============> configuration <============ \\
    public function getConfig(): array;

    public function getGroup(): AssemblelyGroups;

    public function getGroupName(AssemblelyGroups $id): string;

    public function getDefaultDayCalendar(): bool;

    // Equal division quantity
    public function getEDQ(): int;

    // Max cost time compute
    public function getMCTC(): bool;

    // Sup phase first compute
    public function getSPFC(): bool;

    public function getInitialPhase(): array;

    // Initial schedule datetime
    public function getISDT(): string;

    // Initial schedule timestamp
    public function getISTS(): int;

    // is use calendar
    public function getUseCalendar(): bool;

    // is use TPM schedule
    public function getUseTPMS(): bool;

    // is use multiple shifts compute
    public function getUseMSC(): bool;

    public function getDayCalendar(): array;

    public function getNextDayCalendar(): array;

    public function getPreviousDayCalendar(): array;

    public function getMonthCalendar(): array;

    public function getNextMonthCalendar(): array;

    public function getPreviousMonthCalendar(): array;
    // =============> configuration <============ \\
}
