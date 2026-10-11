<?php

namespace Database\Factories;

use App\Enums\DocumentType;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Creates the database row only; tests that read the file must put it on the
 * (faked) disk at the factory's path themselves.
 *
 * @extends Factory<EmployeeDocument>
 */
class EmployeeDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(DocumentType::cases());

        return [
            'employee_id' => Employee::factory(),
            'document_type' => $type,
            'title' => $type->label(),
            'original_name' => Str::slug($type->label()).'.pdf',
            'path' => fn (array $attributes): string => 'employees/'.$attributes['employee_id'].'/'.Str::random(40).'.pdf',
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(20_000, 2_000_000),
            'uploaded_by' => null,
        ];
    }
}
