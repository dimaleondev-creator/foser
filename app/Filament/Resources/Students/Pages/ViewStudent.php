<?php

namespace App\Filament\Resources\Students\Pages;

use App\Filament\Resources\Students\StudentResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Gate;

class ViewStudent extends ViewRecord
{
    protected static string $resource = StudentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('editStudent')
                ->label('Modifier le profil')
                ->icon('heroicon-o-pencil-square')
                ->visible(fn (): bool => Gate::allows('students.edit'))
                ->authorize(fn (): bool => Gate::allows('students.edit'))
                ->schema(function (): array {
                    $fields = [
                        TextInput::make('name')->label('Nom complet')->required()->maxLength(255),
                        TextInput::make('email')->label('Email')->email()->required()->maxLength(255),
                        TextInput::make('phone')->label('Téléphone')->maxLength(40),
                        TextInput::make('other_phone')->label('Autre téléphone')->maxLength(40),
                        TextInput::make('address')->label('Adresse')->maxLength(255),
                        TextInput::make('region')->label('Région')->maxLength(100),
                        TextInput::make('faculty')->label('Faculté / UFR')->maxLength(160),
                        TextInput::make('program')->label('Filière')->maxLength(255),
                        TextInput::make('study_level')->label('Niveau')->maxLength(80),
                        TextInput::make('academic_year')->label('Année académique')->maxLength(20),
                    ];

                    if (Gate::allows('students.edit_sensitive_data')) {
                        $fields[] = TextInput::make('national_id')->label('CNIB')->maxLength(120);
                        $fields[] = TextInput::make('nip')->label('NIP')->maxLength(20);
                    }

                    if (Gate::allows('students.edit_parent_information')) {
                        foreach (['father_first_name', 'father_last_name', 'father_function', 'father_residence_country', 'mother_first_name', 'mother_last_name', 'mother_function', 'mother_residence_country'] as $field) {
                            $fields[] = TextInput::make($field)->label(ucfirst(str_replace('_', ' ', $field)))->maxLength(160);
                        }
                    }

                    return $fields;
                })
                ->fillForm(function (User $record): array {
                    $fields = ['phone', 'other_phone', 'address', 'region', 'faculty', 'program', 'study_level', 'academic_year'];
                    if (Gate::allows('students.edit_sensitive_data')) $fields = [...$fields, 'national_id', 'nip'];
                    if (Gate::allows('students.edit_parent_information')) $fields = [...$fields, 'father_first_name', 'father_last_name', 'father_function', 'father_residence_country', 'mother_first_name', 'mother_last_name', 'mother_function', 'mother_residence_country'];

                    return array_merge([
                        'name' => $record->name,
                        'email' => $record->email,
                    ], $record->studentProfile?->only($fields) ?? []);
                })
                ->action(function (User $record, array $data): void {
                    Gate::authorize('students.edit');
                    $rules = [
                        'name' => ['required', 'string', 'max:255'],
                        'email' => ['required', 'email', 'max:255', \Illuminate\Validation\Rule::unique('users', 'email')->ignore($record->id)],
                        'phone' => ['nullable', 'string', 'max:40'],
                        'other_phone' => ['nullable', 'string', 'max:40'],
                        'address' => ['nullable', 'string', 'max:255'],
                        'region' => ['nullable', 'string', 'max:100'],
                        'faculty' => ['nullable', 'string', 'max:160'],
                        'program' => ['nullable', 'string', 'max:255'],
                        'study_level' => ['nullable', 'string', 'max:80'],
                        'academic_year' => ['nullable', 'string', 'max:20'],
                    ];
                    if (array_key_exists('national_id', $data) || array_key_exists('nip', $data)) {
                        Gate::authorize('students.edit_sensitive_data');
                        $rules['national_id'] = ['nullable', 'string', 'max:120'];
                        $rules['nip'] = ['nullable', 'string', 'max:20'];
                    }
                    $parentFields = ['father_first_name', 'father_last_name', 'father_function', 'father_residence_country', 'mother_first_name', 'mother_last_name', 'mother_function', 'mother_residence_country'];
                    if (array_intersect($parentFields, array_keys($data))) {
                        Gate::authorize('students.edit_parent_information');
                        foreach ($parentFields as $field) $rules[$field] = ['nullable', 'string', 'max:160'];
                    }

                    $validated = Validator::make($data, $rules)->validate();
                    $record->forceFill(['name' => $validated['name'], 'email' => $validated['email']])->save();
                    $profileFields = array_diff_key($validated, ['name' => true, 'email' => true]);
                    if ($record->studentProfile && $profileFields) $record->studentProfile->forceFill($profileFields)->save();
                    app(\App\Services\AuditLogger::class)->record('students.profile.updated', 'users', (string) $record->id, [], ['fields' => array_keys($validated)]);
                }),
            Action::make('viewSensitiveData')
                ->label('Afficher CNIB / NIP')
                ->icon('heroicon-o-eye')
                ->visible(fn (): bool => Gate::allows('students.view_sensitive_data'))
                ->authorize(fn (): bool => Gate::allows('students.view_sensitive_data'))
                ->schema([
                    TextInput::make('national_id')->label('CNIB')->disabled(),
                    TextInput::make('nip')->label('NIP')->disabled(),
                ])
                ->fillForm(function (User $record): array {
                    return app(\App\Services\AdminStudentDataAccessService::class)->sensitiveIdentifiers($record);
                })
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Fermer'),
            Action::make('suspendAccount')
                ->label(fn (User $record): string => $record->status === 'suspended' ? 'Réactiver le compte' : 'Suspendre le compte')
                ->color(fn (User $record): string => $record->status === 'suspended' ? 'success' : 'warning')
                ->requiresConfirmation()
                ->visible(fn (User $record): bool => Gate::allows('students.suspend') && in_array($record->status, ['active', 'suspended'], true))
                ->action(function (User $record): void {
                    $previousStatus = $record->status;
                    $record->forceFill(['status' => $previousStatus === 'suspended' ? 'active' : 'suspended'])->save();
                    app(\App\Services\AuditLogger::class)->record('students.account.status_changed', 'users', (string) $record->id, ['status' => $previousStatus], ['status' => $record->status]);
                }),
        ];
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);

        app(\App\Services\AuditLogger::class)->record('students.profile.viewed', 'users', (string) $this->record->id, [], ['fields' => ['personal', 'academic', 'phone']]);

        if (auth()->user()?->can('students.view_parent_information') && $this->record->studentProfile) {
            app(\App\Services\AdminStudentDataAccessService::class)->parentInformation($this->record);
        }
    }
}