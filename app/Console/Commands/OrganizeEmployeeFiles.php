<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\LeaveRequest;
use App\Services\EmployeeFileNames;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Moves 201-file documents and leave attachments into the employee's named
 * folder under their uploaded name, e.g. "employees/18/Xk2…pdf" becomes
 * "employees/Dela Cruz, Juan A. (2024-0001)/appointment.pdf". Files already
 * in place are left alone, so it can be run again, e.g. after a name change.
 */
#[Signature('files:organize {--dry-run : List the moves without making them}')]
#[Description('Move employee documents and leave attachments into folders named after the employee')]
class OrganizeEmployeeFiles extends Command
{
    private int $moved = 0;

    private int $failed = 0;

    /**
     * Execute the console command.
     */
    public function handle(EmployeeFileNames $fileNames): int
    {
        EmployeeDocument::query()->with('employee')->lazyById()->each(
            fn (EmployeeDocument $document) => $this->organize($fileNames, $document, 'employees', EmployeeDocument::DISK, 'path', $document->original_name),
        );

        LeaveRequest::query()->whereNotNull('attachment_path')->with('employee')->lazyById()->each(
            fn (LeaveRequest $request) => $this->organize($fileNames, $request, 'leave-requests', LeaveRequest::ATTACHMENT_DISK, 'attachment_path', $request->attachment_name ?? basename($request->attachment_path)),
        );

        $verb = $this->option('dry-run') ? 'would be moved' : 'moved';
        $this->info($this->moved.' '.Str::plural('file', $this->moved)." {$verb}.");

        if ($this->failed > 0) {
            $this->error($this->failed.' '.Str::plural('file', $this->failed).' could not be moved.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Move one file into its employee's folder and save the new path.
     */
    private function organize(EmployeeFileNames $fileNames, Model $record, string $area, string $diskName, string $pathColumn, string $originalName): void
    {
        /** @var Employee $employee */
        $employee = $record->getRelation('employee');
        $currentPath = $record->getAttribute($pathColumn);
        $folder = $fileNames->folder($area, $employee);

        if (dirname($currentPath) === $folder) {
            return;
        }

        $disk = Storage::disk($diskName);

        if (! $disk->exists($currentPath)) {
            $this->warn("Missing, skipped: {$currentPath}");
            $this->failed++;

            return;
        }

        $newPath = $folder.'/'.$fileNames->availableFileName($diskName, $folder, $originalName);
        $this->line("{$currentPath} → {$newPath}");
        $this->moved++;

        if ($this->option('dry-run')) {
            return;
        }

        if (! $disk->move($currentPath, $newPath)) {
            $this->error("Could not move {$currentPath}");
            $this->moved--;
            $this->failed++;

            return;
        }

        // A storage change, not an edit of the record, so it is not audited.
        $record->newQuery()->whereKey($record->getKey())->update([$pathColumn => $newPath]);

        $oldFolder = dirname($currentPath);

        if ($disk->allFiles($oldFolder) === []) {
            $disk->deleteDirectory($oldFolder);
        }
    }
}
