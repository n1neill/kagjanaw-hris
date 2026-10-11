<?php

namespace App\Services\Leave;

use App\Enums\LeaveLedgerEntryType;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use SplFileObject;

/**
 * Imports each employee's vacation and sick leave balances as of a cut-off
 * date (the last day of a month) from a CSV file, as the first line of their
 * leave card. Monthly credits are posted from the following month.
 *
 * Expected columns (header row required, extra columns ignored):
 *     employee_number,vacation_leave,sick_leave
 *     2021-0230,45.250,60.000
 */
class OpeningBalanceImporter
{
    public const COLUMNS = ['employee_number', 'vacation_leave', 'sick_leave'];

    public const MAX_ROWS = 5000;

    public function __construct(private LeaveLedger $ledger) {}

    /**
     * @return array{rows: int, imported: int, errors: list<string>}
     */
    public function import(string $path, CarbonImmutable $asOf, User $user): array
    {
        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD | SplFileObject::DROP_NEW_LINE);

        $columns = null;
        $rows = 0;
        $imported = 0;
        $errors = [];
        $seen = [];

        $vacationType = LeaveType::vacation();
        $sickType = LeaveType::sick();

        foreach ($file as $index => $row) {
            if (! is_array($row) || $row === [null]) {
                continue;
            }

            if ($columns === null) {
                $columns = $this->readHeader($row);

                if ($columns === null) {
                    return ['rows' => 0, 'imported' => 0, 'errors' => [
                        __('The first row must be the header: :columns.', ['columns' => implode(',', self::COLUMNS)]),
                    ]];
                }

                continue;
            }

            if (++$rows > self::MAX_ROWS) {
                $errors[] = __('Only the first :max rows were read. Split larger files.', ['max' => self::MAX_ROWS]);
                $rows--;
                break;
            }

            $rowNumber = $index + 1;
            $values = array_map(fn (int $position): string => trim((string) ($row[$position] ?? '')), $columns);

            $validator = Validator::make($values, [
                'employee_number' => ['required', 'string', 'max:30'],
                'vacation_leave' => ['required', 'decimal:0,3', 'min:0', 'max:999.999'],
                'sick_leave' => ['required', 'decimal:0,3', 'min:0', 'max:999.999'],
            ]);

            if ($validator->fails()) {
                $errors[] = __('Row :row: :message', ['row' => $rowNumber, 'message' => $validator->errors()->first()]);

                continue;
            }

            $number = mb_strtoupper($values['employee_number']);

            if (isset($seen[$number])) {
                $errors[] = __('Row :row: employee :number appears more than once.', ['row' => $rowNumber, 'number' => $number]);

                continue;
            }

            $seen[$number] = true;
            $employee = Employee::query()->with('employmentType')->where('employee_number', $number)->first();

            $problem = match (true) {
                $employee === null => __('no employee has ID :number.', ['number' => $number]),
                ! $employee->earnsLeaveCredits() => __(':number\'s employment type does not earn leave credits.', ['number' => $number]),
                $employee->date_hired->greaterThan($asOf) => __(':number was hired after :date.', ['number' => $number, 'date' => $asOf->format('M j, Y')]),
                $employee->leaveLedgerEntries()->exists() => __(':number already has leave card entries; use an adjustment instead.', ['number' => $number]),
                default => null,
            };

            if ($problem !== null) {
                $errors[] = __('Row :row: :message', ['row' => $rowNumber, 'message' => $problem]);

                continue;
            }

            DB::transaction(function () use ($employee, $values, $asOf, $user, $vacationType, $sickType): void {
                $particulars = __('Balance as of :date', ['date' => $asOf->format('M j, Y')]);

                $this->ledger->post($employee, $vacationType, LeaveLedgerEntryType::OpeningBalance, LeaveDays::toThousandths($values['vacation_leave']), $particulars, $asOf, user: $user);
                $this->ledger->post($employee, $sickType, LeaveLedgerEntryType::OpeningBalance, LeaveDays::toThousandths($values['sick_leave']), $particulars, $asOf, user: $user);
            });

            $imported++;
        }

        if ($columns === null) {
            return ['rows' => 0, 'imported' => 0, 'errors' => [__('The file is empty.')]];
        }

        return ['rows' => $rows, 'imported' => $imported, 'errors' => $errors];
    }

    public static function template(): string
    {
        return implode(',', self::COLUMNS)."\n"
            ."2021-0230,45.250,60.000\n"
            ."2015-0101,102.500,120.375\n";
    }

    /**
     * @param  list<string|null>  $row
     * @return array<string, int>|null Column positions by name, or null if a column is missing.
     */
    private function readHeader(array $row): ?array
    {
        $normalized = array_map(
            fn (?string $cell): string => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $cell))),
            $row,
        );

        $positions = [];

        foreach (self::COLUMNS as $column) {
            $position = array_search($column, $normalized, true);

            if ($position === false) {
                return null;
            }

            $positions[$column] = $position;
        }

        return $positions;
    }
}
