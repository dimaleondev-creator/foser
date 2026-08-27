<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ApplicationCompletenessService
{
    public function check(string $applicationId): array
    {
        $application = DB::table('applications')->where('id', $applicationId)->firstOrFail();
        $missingFields = collect(['project_title', 'summary', 'description', 'domain', 'objectives', 'methodology', 'calendar', 'budget'])
            ->filter(fn (string $field): bool => blank($application->{$field} ?? null))->values()->all();
        $missingDocuments = app(StudentApplicationWorkflow::class)->missingDocuments($application->id, $application->program_id);

        return [
            'is_complete' => $missingFields === [] && $missingDocuments === [],
            'missing_fields' => $missingFields,
            'missing_documents' => $missingDocuments,
            'warnings' => [],
            'errors' => [],
        ];
    }
}