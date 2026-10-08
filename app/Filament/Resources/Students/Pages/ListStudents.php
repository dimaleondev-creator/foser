<?php

namespace App\Filament\Resources\Students\Pages;

use App\Filament\Resources\Students\StudentResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Gate;

class ListStudents extends ListRecords
{
    protected static string $resource = StudentResource::class;

    public function mount(): void
    {
        parent::mount();
        app(\App\Services\AuditLogger::class)->record('students.list.viewed', 'student_profiles', null, [], ['fields' => ['name', 'inee', 'phone', 'university', 'program', 'status']]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportStudents')
                ->label('Exporter CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->visible(fn (): bool => Gate::allows('students.export'))
                ->authorize(fn (): bool => Gate::allows('students.export'))
                ->action(function () {
                    Gate::authorize('students.export');
                    app(\App\Services\AuditLogger::class)->record('students.exported', 'student_profiles', null, [], ['format' => 'csv', 'scope' => 'standard']);

                    return response()->streamDownload(function (): void {
                        $handle = fopen('php://output', 'wb');
                        fputcsv($handle, ['Nom', 'Email', 'INEE', 'Téléphone', 'Université', 'Filière', 'Niveau', 'Année académique', 'Statut', 'Inscrit le']);
                        StudentResource::getEloquentQuery()->whereHas('studentProfile')->orderBy('users.name')->chunk(500, function ($students) use ($handle): void {
                            foreach ($students as $student) {
                                fputcsv($handle, [
                                    $student->name,
                                    $student->email,
                                    $student->studentProfile?->inee,
                                    $student->studentProfile?->phone,
                                    $student->studentProfile?->university?->name,
                                    $student->studentProfile?->program,
                                    $student->studentProfile?->study_level,
                                    $student->studentProfile?->academic_year,
                                    $student->status,
                                    $student->created_at?->toDateString(),
                                ]);
                            }
                        });
                        fclose($handle);
                    }, 'etudiants-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
                }),
        ];
    }
}