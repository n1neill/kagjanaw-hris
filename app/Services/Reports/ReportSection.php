<?php

namespace App\Services\Reports;

/**
 * One table of a report: column headings, rows and an optional totals row.
 * Money columns hold decimal strings ("1234.50"); number columns hold
 * integers or decimal strings.
 */
final readonly class ReportSection
{
    /**
     * @param  list<string>  $columns
     * @param  list<list<string|int|float|null>>  $rows
     * @param  list<string|int|float|null>|null  $totals
     * @param  list<int>  $moneyColumns  Indexes of peso columns.
     * @param  list<int>  $numberColumns  Indexes of other numeric columns.
     */
    public function __construct(
        public array $columns,
        public array $rows,
        public ?array $totals = null,
        public array $moneyColumns = [],
        public array $numberColumns = [],
        public ?string $heading = null,
    ) {}

    public function isMoney(int $column): bool
    {
        return in_array($column, $this->moneyColumns, true);
    }

    public function isNumeric(int $column): bool
    {
        return $this->isMoney($column) || in_array($column, $this->numberColumns, true);
    }
}
