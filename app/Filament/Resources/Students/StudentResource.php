<?php

namespace App\Filament\Resources\Students;

use App\Filament\Resources\Students\Pages\ListStudents;
use App\Filament\Resources\Students\Pages\ViewStudent;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class StudentResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?string $navigationLabel = 'Étudiants';

    protected static ?string $modelLabel = 'étudiant';

    protected static ?string $pluralModelLabel = 'étudiants';

    protected static string|UnitEnum|null $navigationGroup = 'ADMINISTRATION';

    protected static ?int $navigationSort = 10;

    protected static ?string $slug = 'students';

    public static function canAccess(): bool
    {
        return Gate::allows('students.view');
    }

    public static function canViewAny(): bool
    {
        return Gate::allows('students.view');
    }

    public static function canView($record): bool
    {
        return $record instanceof User
            && $record->account_type === 'etudiant'
            && Gate::allows('students.view');
    }

    public static function getEloquentQuery(): Builder
    {
        $profileColumns = [
            'id', 'user_id', 'university_id', 'first_name', 'last_name', 'inee', 'ine_status', 'ine_verified_at',
            'phone', 'other_phone', 'date_of_birth', 'birth_place', 'sex', 'nationality', 'address', 'region',
            'faculty', 'program', 'study_level', 'academic_year', 'emergency_contact_name', 'emergency_contact_phone',
            'created_at', 'updated_at',
        ];
        if (Gate::allows('students.view_sensitive_data')) $profileColumns = [...$profileColumns, 'national_id', 'nip'];
        if (Gate::allows('students.view_parent_information')) $profileColumns = [...$profileColumns,
            'father_first_name', 'father_last_name', 'father_residence_country', 'father_function',
            'mother_first_name', 'mother_last_name', 'mother_residence_country', 'mother_function',
        ];

        return parent::getEloquentQuery()
            ->where('users.account_type', 'etudiant')
            ->whereHas('studentProfile')
            ->with([
                'studentProfile' => fn ($relation) => $relation->select($profileColumns),
                'studentProfile.university',
            ]);
    }

    public static function table(Table $table): Table
    {
        $canSearchSensitive = Gate::allows('students.view_sensitive_data');

        return $table
            ->columns([
                TextColumn::make('name')->label('Étudiant')->searchable()->sortable(),
                TextColumn::make('studentProfile.first_name')->label('Prénom(s)')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('studentProfile.last_name')->label('Nom')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('email')->label('Email')->searchable()->toggleable(),
                TextColumn::make('studentProfile.inee')->label('INEE')->searchable()->toggleable(),
                TextColumn::make('studentProfile.phone')->label('Téléphone')->placeholder('Non renseigné')->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('studentProfile', fn (Builder $profile): Builder => $profile->where('phone', 'like', '%'.$search.'%')))->toggleable(),
                TextColumn::make('studentProfile.university.name')->label('Université')->placeholder('Non renseignée')->searchable(),
                TextColumn::make('studentProfile.program')->label('Filière')->placeholder('Non renseignée')->searchable()->toggleable(),
                TextColumn::make('studentProfile.study_level')->label('Niveau')->placeholder('Non renseigné')->toggleable(),
                TextColumn::make('studentProfile.academic_year')->label('Année académique')->placeholder('Non renseignée')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')->label('Compte')->badge()->color(fn (string $state): string => match ($state) {
                    'active' => 'success',
                    'suspended' => 'warning',
                    'disabled', 'inactive' => 'danger',
                    default => 'gray',
                })->sortable(),
                TextColumn::make('created_at')->label('Inscrit le')->date('d/m/Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('studentProfile.national_id')->label('CNIB')->formatStateUsing(fn (?string $state): string => filled($state) ? '••••••••' : 'Non renseignée')->visible($canSearchSensitive),
                TextColumn::make('studentProfile.nip')->label('NIP')->formatStateUsing(fn (?string $state): string => filled($state) ? '••••••••' : 'Non renseigné')->visible($canSearchSensitive),
            ])
            ->filters([
                SelectFilter::make('status')->label('Statut du compte')->options([
                    'active' => 'Actif', 'invited' => 'En attente', 'suspended' => 'Suspendu', 'disabled' => 'Désactivé',
                ]),
                SelectFilter::make('university')->label('Université')->options(fn (): array => \App\Models\University::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => $query->when($data['value'] ?? null, fn (Builder $query, string $id): Builder => $query->whereHas('studentProfile', fn (Builder $profile): Builder => $profile->where('university_id', $id)))),
                Filter::make('academic')->label('Informations académiques')
                    ->schema([
                        \Filament\Forms\Components\TextInput::make('program')->label('Filière'),
                        \Filament\Forms\Components\TextInput::make('study_level')->label('Niveau'),
                        \Filament\Forms\Components\TextInput::make('academic_year')->label('Année'),
                        \Filament\Forms\Components\TextInput::make('region')->label('Région'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->whereHas('studentProfile', function (Builder $profile) use ($data): void {
                        foreach (['program', 'study_level', 'academic_year', 'region'] as $field) {
                            if (filled($data[$field] ?? null)) {
                                $profile->where($field, 'like', '%'.$data[$field].'%');
                            }
                        }
                    })),
                SelectFilter::make('application_status')->label('Statut du dossier')->options([
                    'soumis' => 'Soumis', 'verification' => 'En vérification', 'incomplet' => 'Incomplet',
                    'eligible' => 'Validé', 'rejete' => 'Rejeté', 'rejected' => 'Rejeté',
                ])->query(fn (Builder $query, array $data): Builder => $query->when($data['value'] ?? null, fn (Builder $query, string $status): Builder => $query->whereHas('applications', fn (Builder $applications): Builder => $applications->where('status', $status)))),
                Filter::make('registration_date')->label('Date d’inscription')
                    ->schema([
                        DatePicker::make('from')->label('Du'),
                        DatePicker::make('until')->label('Au'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('users.created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('users.created_at', '<=', $date))),
                Filter::make('sensitive_identifiers')->label('Identifiants sensibles')->visible($canSearchSensitive)
                    ->schema([
                        \Filament\Forms\Components\TextInput::make('national_id')->label('CNIB'),
                        \Filament\Forms\Components\TextInput::make('nip')->label('NIP'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $fields = array_values(array_filter(['national_id', 'nip'], fn (string $field): bool => filled($data[$field] ?? null)));
                        if ($fields) app(\App\Services\AuditLogger::class)->record('students.sensitive_identifiers.searched', 'student_profiles', null, [], ['fields' => $fields]);

                        return $query->when($fields, fn (Builder $query): Builder => $query->whereHas('studentProfile', function (Builder $profile) use ($data, $fields): void {
                            foreach ($fields as $field) $profile->where($field, $data[$field]);
                        }));
                    }),
            ])
            ->recordUrl(fn (User $record): string => static::getUrl('view', ['record' => $record]))
            ->recordActions([ViewAction::make()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        $components = [
            Section::make('Vérifier les informations personnelles')->columns(2)->schema([
                TextEntry::make('studentProfile.first_name')->label('Prénom(s)')->placeholder('Non renseigné'),
                TextEntry::make('studentProfile.last_name')->label('Nom')->placeholder('Non renseigné'),
                TextEntry::make('name')->label('Nom du compte'),
                TextEntry::make('email')->label('Email'),
                TextEntry::make('studentProfile.phone')->label('Téléphone')->placeholder('Non renseigné'),
                TextEntry::make('studentProfile.other_phone')->label('Autre téléphone')->placeholder('Non renseigné'),
                TextEntry::make('studentProfile.date_of_birth')->label('Date de naissance')->date('d/m/Y')->placeholder('Non renseignée'),
                TextEntry::make('studentProfile.birth_place')->label('Lieu de naissance')->placeholder('Non renseigné'),
                TextEntry::make('studentProfile.sex')->label('Sexe')->placeholder('Non renseigné'),
                TextEntry::make('studentProfile.nationality')->label('Nationalité')->placeholder('Non renseignée'),
                TextEntry::make('studentProfile.address')->label('Adresse')->placeholder('Non renseignée'),
                TextEntry::make('studentProfile.region')->label('Région')->placeholder('Non renseignée'),
            ]),
            Section::make('Informations académiques')->columns(2)->schema([
                TextEntry::make('studentProfile.university.name')->label('Université')->placeholder('Non renseignée'),
                TextEntry::make('studentProfile.faculty')->label('Faculté / UFR')->placeholder('Non renseignée'),
                TextEntry::make('studentProfile.program')->label('Filière')->placeholder('Non renseignée'),
                TextEntry::make('studentProfile.study_level')->label('Niveau')->placeholder('Non renseigné'),
                TextEntry::make('studentProfile.academic_year')->label('Année académique')->placeholder('Non renseignée'),
                TextEntry::make('studentProfile.inee')->label('INEE')->placeholder('Non renseigné'),
                TextEntry::make('status')->label('Statut du compte')->badge(),
                TextEntry::make('created_at')->label('Inscrit le')->dateTime('d/m/Y H:i'),
            ]),
            Section::make('Identification officielle')->columns(2)->schema([
                TextEntry::make('studentProfile.inee')->label('INEE')->placeholder('Non renseigné'),
                TextEntry::make('sensitive_data_notice')->label('CNIB et NIP')->state('Masqués. Utilisez l’action de consultation autorisée pour les afficher.'),
            ]),
            Section::make('Contact d’urgence')->columns(2)->schema([
                TextEntry::make('studentProfile.emergency_contact_name')->label('Nom')->placeholder('Non renseigné'),
                TextEntry::make('studentProfile.emergency_contact_phone')->label('Téléphone')->placeholder('Non renseigné'),
            ]),
            Section::make('Demandes')->schema([
                TextEntry::make('applications_summary')->label('Candidatures')->state(fn (?User $record): string => static::applicationSummary($record)),
            ])->visible(fn (): bool => Gate::allows('applications.view')),
        ];

        if (Gate::allows('students.view_documents')) {
            $components[] = Section::make('Documents')->schema([
                TextEntry::make('documents_summary')->label('Pièces déposées')->state(fn (?User $record): string => static::documentSummary($record)),
            ]);
        }

        if (Gate::allows('students.view_finances') || Gate::allows('finance.view')) {
            $components[] = Section::make('Financements et décaissements')->columns(2)->schema([
                TextEntry::make('financial_summary')->label('Synthèse financière')->state(fn (?User $record): string => static::financialSummary($record)),
                TextEntry::make('payments_summary')->label('Paiements')->state(fn (?User $record): string => static::paymentSummary($record)),
            ]);
        }

        $components[] = Section::make('Notifications')->schema([
            TextEntry::make('notifications_summary')->label('Dernières notifications')->state(fn (?User $record): string => static::notificationSummary($record)),
        ])->visible(fn (): bool => Gate::allows('students.view_history'));

        $components[] = Section::make('Historique administratif')->schema([
            TextEntry::make('history_summary')->label('Événements récents')->state(fn (?User $record): string => static::historySummary($record)),
        ])->visible(fn (): bool => Gate::allows('students.view_history'));

        if (Auth::user()?->can('students.view_parent_information')) {
            $components[] = Section::make('Parents / tuteurs')->columns(2)->schema([
                TextEntry::make('studentProfile.father_first_name')->label('Prénom du père')->placeholder('Non renseigné'),
                TextEntry::make('studentProfile.father_last_name')->label('Nom du père')->placeholder('Non renseigné'),
                TextEntry::make('studentProfile.father_function')->label('Profession du père')->placeholder('Non renseignée'),
                TextEntry::make('studentProfile.father_residence_country')->label('Pays de résidence du père')->placeholder('Non renseigné'),
                TextEntry::make('studentProfile.mother_first_name')->label('Prénom de la mère')->placeholder('Non renseigné'),
                TextEntry::make('studentProfile.mother_last_name')->label('Nom de la mère')->placeholder('Non renseigné'),
                TextEntry::make('studentProfile.mother_function')->label('Profession de la mère')->placeholder('Non renseignée'),
                TextEntry::make('studentProfile.mother_residence_country')->label('Pays de résidence de la mère')->placeholder('Non renseigné'),
            ]);
        }

        return $schema->components($components);
    }

    private static function applicationSummary(?User $student): string
    {
        if (! $student) return 'Aucune demande disponible.';
        $rows = DB::table('applications')->leftJoin('programs', 'programs.id', '=', 'applications.program_id')
            ->where('applications.applicant_id', $student->id)
            ->select('applications.reference', 'applications.status', 'applications.budget', 'applications.submitted_at', 'programs.name as program_name')
            ->latest('applications.created_at')->limit(50)->get();

        return $rows->isEmpty() ? 'Aucune demande enregistrée.' : $rows->map(fn ($row): string => implode(' · ', array_filter([
            $row->reference, $row->program_name, $row->status,
            $row->budget !== null ? number_format((float) $row->budget, 0, ',', ' ').' FCFA' : null,
            $row->submitted_at ? \Carbon\Carbon::parse($row->submitted_at)->format('d/m/Y') : null,
        ])))->implode("\n");
    }

    private static function documentSummary(?User $student): string
    {
        if (! $student) return 'Aucun document disponible.';
        $rows = DB::table('application_documents')->join('applications', 'applications.id', '=', 'application_documents.application_id')
            ->join('documents', 'documents.id', '=', 'application_documents.document_id')
            ->where('applications.applicant_id', $student->id)
            ->select('documents.title', 'documents.document_type', 'application_documents.status', 'documents.created_at')
            ->latest('documents.created_at')->limit(50)->get();

        return $rows->isEmpty() ? 'Aucun document lié aux demandes.' : $rows->map(fn ($row): string => implode(' · ', array_filter([
            $row->title, $row->document_type, $row->status, \Carbon\Carbon::parse($row->created_at)->format('d/m/Y'),
        ])))->implode("\n");
    }

    private static function financialSummary(?User $student): string
    {
        if (! $student) return 'Aucune donnée financière disponible.';
        $requested = (float) DB::table('applications')->where('applicant_id', $student->id)->sum('budget');
        $commitments = DB::table('financial_commitments')->join('applications', 'applications.id', '=', 'financial_commitments.application_id')
            ->where('applications.applicant_id', $student->id)
            ->select('financial_commitments.id', 'financial_commitments.reference', 'financial_commitments.amount', 'financial_commitments.currency', 'financial_commitments.status')
            ->unionAll(DB::table('financial_commitments')->where('beneficiary_id', $student->id)->whereNull('application_id')->select('id', 'reference', 'amount', 'currency', 'status'))
            ->get();
        if ($commitments->isEmpty()) {
            return 'Demandé : '.number_format($requested, 0, ',', ' ').' FCFA'."\nAccordé : 0 FCFA\nDécaissé : 0 FCFA\nRestant : 0 FCFA";
        }

        $committed = (float) $commitments->sum('amount');
        $disbursed = (float) DB::table('disbursements')->whereIn('commitment_id', $commitments->pluck('id'))->whereNotNull('disbursed_at')->sum('amount');
        $currency = $commitments->first()->currency;

        return implode("\n", [
            'Demandé : '.number_format($requested, 0, ',', ' ').' '.$currency,
            'Accordé : '.number_format($committed, 0, ',', ' ').' '.$currency,
            'Décaissé : '.number_format($disbursed, 0, ',', ' ').' '.$currency,
            'Restant : '.number_format(max(0, $committed - $disbursed), 0, ',', ' ').' '.$currency,
            'Engagements : '.$commitments->map(fn ($row): string => $row->reference.' · '.$row->status)->implode(', '),
        ]);
    }

    private static function paymentSummary(?User $student): string
    {
        if (! $student) return 'Aucun paiement disponible.';
        $payments = DB::table('payment_records')->join('disbursements', 'disbursements.id', '=', 'payment_records.disbursement_id')
            ->join('financial_commitments', 'financial_commitments.id', '=', 'disbursements.commitment_id')
            ->leftJoin('applications', 'applications.id', '=', 'financial_commitments.application_id')
            ->where(function ($query) use ($student): void {
                $query->where('applications.applicant_id', $student->id)
                    ->orWhere('financial_commitments.beneficiary_id', $student->id)
                    ->orWhere('payment_records.beneficiary_id', $student->id);
            })
            ->select('payment_records.provider_reference', 'payment_records.payment_method', 'payment_records.amount', 'payment_records.status', 'payment_records.paid_at')
            ->latest('payment_records.created_at')->limit(50)->get();

        return $payments->isEmpty() ? 'Aucun paiement enregistré.' : $payments->map(fn ($row): string => implode(' · ', array_filter([
            $row->provider_reference, $row->payment_method,
            number_format((float) $row->amount, 0, ',', ' ').' FCFA', $row->status,
            $row->paid_at ? \Carbon\Carbon::parse($row->paid_at)->format('d/m/Y') : null,
        ])))->implode("\n");
    }

    private static function notificationSummary(?User $student): string
    {
        if (! $student) return 'Aucune notification disponible.';
        $rows = DB::table('notifications')->where('notifiable_type', User::class)->where('notifiable_id', $student->id)
            ->latest()->limit(20)->get(['type', 'data', 'read_at', 'created_at']);

        return $rows->isEmpty() ? 'Aucune notification enregistrée.' : $rows->map(function ($row): string {
            $data = json_decode($row->data, true) ?: [];
            return implode(' · ', array_filter([
                $data['subject'] ?? str_replace('.', ' ', $row->type),
                $row->read_at ? 'Lue' : 'Non lue',
                \Carbon\Carbon::parse($row->created_at)->format('d/m/Y H:i'),
            ]));
        })->implode("\n");
    }

    private static function historySummary(?User $student): string
    {
        if (! $student) return 'Aucun historique disponible.';
        $profileId = $student->studentProfile?->id;
        $rows = DB::table('audit_logs')->where(function ($query) use ($student, $profileId): void {
            $query->where(function ($query) use ($student): void { $query->where('auditable_type', 'users')->where('auditable_id', (string) $student->id); });
            if ($profileId) $query->orWhere(function ($query) use ($profileId): void { $query->where('auditable_type', 'student_profiles')->where('auditable_id', (string) $profileId); });
        })->leftJoin('users as actors', 'actors.id', '=', 'audit_logs.user_id')
            ->latest('audit_logs.created_at')->limit(30)
            ->get(['audit_logs.event', 'audit_logs.created_at', 'actors.name as actor_name']);

        return $rows->isEmpty() ? 'Aucun événement administratif enregistré.' : $rows->map(fn ($row): string => implode(' · ', array_filter([
            \Carbon\Carbon::parse($row->created_at)->format('d/m/Y H:i'), $row->event, $row->actor_name,
        ])))->implode("\n");
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStudents::route('/'),
            'view' => ViewStudent::route('/{record}'),
        ];
    }
}