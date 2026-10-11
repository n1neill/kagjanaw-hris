<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceSource;
use App\Enums\VerifyType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use SplFileObject;

/**
 * Imports punches from a CSV file, e.g. an export from a device or a
 * spreadsheet typed up from a paper logbook.
 *
 * Expected columns (header row required, extra columns ignored):
 *     biometric_user_id,date,time
 *     1024,2026-10-05,07:58
 *
 * Rows with errors are reported by row number and skipped; the rest are
 * imported. Duplicates of punches already stored are skipped.
 */
class AttendanceCsvImporter
{
    public const COLUMNS = ['biometric_user_id', 'date', 'time'];

    public const MAX_ROWS = 20000;

    public function __construct(private AttendanceSyncService $sync) {}

    /**
     * @return array{rows: int, imported: int, duplicates: int, flagged: int, errors: list<string>}
     */
    public function import(string $path): array
    {
        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD | SplFileObject::DROP_NEW_LINE);

        $columns = null;
        $punches = [];
        $errors = [];
        $rowCount = 0;

        foreach ($file as $index => $row) {
            if (! is_array($row) || $row === [null]) {
                continue;
            }

            if ($columns === null) {
                $columns = $this->readHeader($row);

                if ($columns === null) {
                    return $this->result(0, 0, ['inserted' => 0, 'flagged' => 0], [
                        __('The first row must be the header: :columns.', ['columns' => implode(',', self::COLUMNS)]),
                    ]);
                }

                continue;
            }

            $rowCount++;
            $rowNumber = $index + 1;

            if ($rowCount > self::MAX_ROWS) {
                $errors[] = __('Only the first :max rows were read. Split larger files.', ['max' => self::MAX_ROWS]);
                $rowCount--;
                break;
            }

            $values = [];

            foreach ($columns as $name => $position) {
                $values[$name] = trim((string) ($row[$position] ?? ''));
            }

            $validator = Validator::make($values, [
                'biometric_user_id' => ['required', 'digits_between:1,9'],
                'date' => ['required', 'date_format:Y-m-d'],
                'time' => ['required', 'regex:/^([01]?\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/'],
            ], [
                'time.regex' => __('The time must be in 24-hour HH:MM format.'),
            ]);

            if ($validator->fails()) {
                $errors[] = __('Row :row: :message', ['row' => $rowNumber, 'message' => $validator->errors()->first()]);

                continue;
            }

            $punches[] = new RawPunch(
                biometricUserId: $values['biometric_user_id'],
                loggedAt: CarbonImmutable::parse($values['date'].' '.$values['time'])->startOfMinute(),
                verifyType: VerifyType::Unknown,
            );
        }

        if ($columns === null) {
            return $this->result(0, 0, ['inserted' => 0, 'flagged' => 0], [__('The file is empty.')]);
        }

        return $this->result($rowCount, count($punches), $this->sync->store($punches, AttendanceSource::CsvImport), $errors);
    }

    public static function template(): string
    {
        return implode(',', self::COLUMNS)."\n"
            ."1024,2026-10-05,07:58\n"
            ."1024,2026-10-05,12:02\n"
            ."1024,2026-10-05,12:55\n"
            ."1024,2026-10-05,17:05\n";
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

    /**
     * @param  array{inserted: int, flagged: int}  $counts
     * @param  list<string>  $errors
     * @return array{rows: int, imported: int, duplicates: int, flagged: int, errors: list<string>}
     */
    private function result(int $rows, int $validRows, array $counts, array $errors): array
    {
        return [
            'rows' => $rows,
            'imported' => $counts['inserted'],
            'duplicates' => $validRows - $counts['inserted'],
            'flagged' => $counts['flagged'],
            'errors' => $errors,
        ];
    }
}
