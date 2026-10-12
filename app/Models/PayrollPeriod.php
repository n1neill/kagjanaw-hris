<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PayrollPeriodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A semi-monthly pay period: the 1st to the 15th (half 1) or the 16th to
 * the end of the month (half 2).
 */
#[Fillable(['year', 'month', 'half', 'start_date', 'end_date'])]
class PayrollPeriod extends Model
{
    /** @use HasFactory<PayrollPeriodFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'half' => 'integer',
            'start_date' => 'immutable_date',
            'end_date' => 'immutable_date',
        ];
    }

    /**
     * The period for a month and half, created on first use.
     */
    public static function for(int $year, int $month, int $half): self
    {
        $first = CarbonImmutable::create($year, $month, 1);

        return static::query()->firstOrCreate(
            ['year' => $year, 'month' => $month, 'half' => $half],
            [
                'start_date' => $half === 1 ? $first : $first->setDay(16),
                'end_date' => $half === 1 ? $first->setDay(15) : $first->endOfMonth()->startOfDay(),
            ],
        );
    }

    /**
     * @return HasMany<PayrollRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(PayrollRun::class);
    }

    /**
     * "October 1–15, 2026" or "October 16–31, 2026".
     */
    public function label(): string
    {
        return $this->start_date->format('F j').'–'.$this->end_date->format('j, Y');
    }

    public function monthStart(): CarbonImmutable
    {
        return $this->start_date->startOfMonth();
    }
}
