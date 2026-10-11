<?php

namespace App\Models;

use App\Services\Attendance\ScheduleRules;
use Database\Factories\WorkScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Office hours used to compute Daily Time Records. The default schedule
 * applies to every employee.
 */
#[Fillable(['name', 'am_start', 'am_end', 'pm_start', 'pm_end', 'grace_minutes', 'working_days', 'is_default'])]
class WorkSchedule extends Model
{
    /** @use HasFactory<WorkScheduleFactory> */
    use HasFactory;

    /**
     * ISO-8601 day numbers (1 = Monday ... 7 = Sunday) with their names.
     *
     * @var array<int, string>
     */
    public const DAY_NAMES = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'grace_minutes' => 'integer',
            'working_days' => 'array',
            'is_default' => 'boolean',
        ];
    }

    /**
     * The schedule applied to employees; created with standard office hours if missing.
     */
    public static function current(): self
    {
        return static::query()->where('is_default', true)->first()
            ?? static::create([
                'name' => 'Regular Office Hours',
                'am_start' => '08:00',
                'am_end' => '12:00',
                'pm_start' => '13:00',
                'pm_end' => '17:00',
                'grace_minutes' => 15,
                'working_days' => [1, 2, 3, 4, 5],
                'is_default' => true,
            ]);
    }

    public function rules(): ScheduleRules
    {
        return ScheduleRules::fromTimes($this->am_start, $this->am_end, $this->pm_start, $this->pm_end, $this->grace_minutes);
    }

    public function isWorkingDay(int $isoDayOfWeek): bool
    {
        return in_array($isoDayOfWeek, array_map('intval', $this->working_days), true);
    }

    /**
     * "8:00-12:00" / "1:00-5:00" style label for one session, as printed on the DTR.
     */
    public function sessionLabel(string $start, string $end): string
    {
        return DailyTimeRecord::displayTime($start).'-'.DailyTimeRecord::displayTime($end);
    }
}
