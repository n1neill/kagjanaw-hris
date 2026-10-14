<?php

namespace App\Services\Backup;

use App\Enums\BackupTrigger;
use Carbon\CarbonImmutable;

/**
 * A backup archive in storage, described by its manifest.
 */
final readonly class Backup
{
    /**
     * @param  list<string>  $migrations  The migrations the backed-up database had run.
     */
    public function __construct(
        public string $name,
        public string $path,
        public int $size,
        public CarbonImmutable $createdAt,
        public ?string $createdBy,
        public ?BackupTrigger $trigger,
        public array $migrations,
        public int $fileCount,
    ) {}

    /**
     * The size in KB, MB or GB.
     */
    public function readableSize(): string
    {
        $units = ['bytes', 'KB', 'MB', 'GB'];
        $size = $this->size;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return ($unit === 0 ? $size : number_format($size, 1)).' '.$units[$unit];
    }
}
