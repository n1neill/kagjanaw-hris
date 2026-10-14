<?php

namespace App\Services\Reports;

/**
 * A report ready to export: a title, a subtitle (period, office, filters)
 * and one or more tables.
 */
final readonly class Report
{
    /**
     * @param  list<ReportSection>  $sections
     * @param  string  $filename  Without extension.
     */
    public function __construct(
        public string $title,
        public string $subtitle,
        public array $sections,
        public string $filename,
        public bool $landscape = true,
    ) {}
}
