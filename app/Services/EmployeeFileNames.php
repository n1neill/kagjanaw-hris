<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Where an employee's uploaded files go: a folder named after the employee,
 * e.g. "employees/Dela Cruz, Juan A. (2024-0001)", holding each file under
 * the name it was uploaded with.
 */
class EmployeeFileNames
{
    /**
     * The employee's folder in an area such as "employees" or "leave-requests".
     * The employee number keeps two employees with the same name apart.
     */
    public function folder(string $area, Employee $employee): string
    {
        return $area.'/'.$this->safeName("{$employee->sortable_name} ({$employee->employee_number})");
    }

    /**
     * The original file name, numbered "scan (2).pdf" when the folder already
     * has a file by that name, so nothing is ever replaced.
     */
    public function availableFileName(string $disk, string $folder, string $originalName): string
    {
        $storage = Storage::disk($disk);
        $extension = Str::lower(pathinfo($originalName, PATHINFO_EXTENSION));
        $suffix = $extension !== '' ? ".{$extension}" : '';
        $baseName = Str::limit($this->safeName(pathinfo($originalName, PATHINFO_FILENAME)), 150, '');
        $baseName = $baseName !== '' ? $baseName : 'document';

        $fileName = $baseName.$suffix;

        for ($copy = 2; $storage->exists("{$folder}/{$fileName}"); $copy++) {
            $fileName = "{$baseName} ({$copy}){$suffix}";
        }

        return $fileName;
    }

    /**
     * A name usable as a folder or file on any disk: no path separators, none
     * of the characters Windows forbids, no leading or trailing dots or spaces.
     */
    private function safeName(string $name): string
    {
        $name = mb_convert_encoding($name, 'UTF-8', 'UTF-8');
        $name = preg_replace('/[\x00-\x1F\x7F\/\\\\:*?"<>|]+/u', ' ', $name) ?? '';

        return trim((string) preg_replace('/\s+/u', ' ', $name), ' .');
    }
}
