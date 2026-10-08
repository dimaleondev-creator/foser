<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Utilisateurs';

    protected static string|UnitEnum|null $navigationGroup = 'UTILISATEURS';

    public static function canAccess(): bool
    {
        return Gate::allows('users.view') && Auth::user()?->account_type !== 'universite';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->helperText('Laissez vide pour envoyer une invitation sécurisée.')
                    ->nullable()
                    ->dehydrated(fn (?string $state): bool => filled($state)),
                Select::make('account_type')
                    ->label('Type de compte')
                    ->options([
                        'chercheur' => 'Chercheur', 'universite' => 'Responsable universitaire',
                        'admin' => 'Admin', 'directeur_general' => 'Directeur général',
                        'gestionnaire' => 'Gestionnaire', 'agent_dossier' => 'Agent dossier',
                        'agent_finance' => 'Agent finance', 'agent_recherche' => 'Agent recherche',
                        'agent_communication' => 'Agent communication', 'evaluateur' => 'Évaluateur',
                        'partenaire' => 'Partenaire', 'etudiant' => 'Étudiant',
                    ])->default('etudiant')->required(),
                Select::make('status')
                    ->options(['invited' => 'Invité', 'active' => 'Actif', 'suspended' => 'Suspendu', 'disabled' => 'Désactivé'])
                    ->default('invited')->required(),
                Select::make('university_id')
                    ->label('Université associée')
                    ->relationship('university', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get): bool => $get('account_type') === 'universite')
                    ->required(fn (\Filament\Schemas\Components\Utilities\Get $get): bool => $get('account_type') === 'universite'),
                TagsInput::make('evaluation_expertise')
                    ->label('Domaines d’expertise')
                    ->placeholder('Ajouter un domaine')
                    ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get): bool => $get('account_type') === 'evaluateur')
                    ->dehydrated(fn (\Filament\Schemas\Components\Utilities\Get $get): bool => $get('account_type') === 'evaluateur'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->leftJoin('student_profiles', 'student_profiles.user_id', '=', 'users.id')
                ->leftJoin('researcher_profiles', 'researcher_profiles.user_id', '=', 'users.id')
                ->leftJoin('users as ine_verifier', 'ine_verifier.id', '=', 'student_profiles.ine_verified_by')
                ->leftJoin('universities as classification_university', function ($join): void {
                    $join->on('classification_university.id', '=', 'users.university_id')
                        ->orOn('classification_university.id', '=', 'student_profiles.university_id')
                        ->orOn('classification_university.id', '=', 'researcher_profiles.university_id');
                })
                ->select('users.*')
                ->addSelect('student_profiles.inee')
                ->addSelect('student_profiles.ine_status', 'student_profiles.ine_verified_at')
                ->addSelect('student_profiles.first_name as ine_first_name', 'student_profiles.last_name as ine_last_name', 'student_profiles.created_at as ine_created_at')
                ->addSelect('ine_verifier.name as ine_verified_by_name')
                ->selectRaw("COALESCE(student_profiles.program, researcher_profiles.speciality, researcher_profiles.research_domain, '-') as filiere")
                ->selectRaw("COALESCE(classification_university.name, '-') as classification_university_name")
                ->orderBy('users.account_type')
                ->orderBy('classification_university.name')
                ->orderByRaw("COALESCE(student_profiles.program, researcher_profiles.speciality, researcher_profiles.research_domain, '')")
                ->orderBy('users.name'))
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('ine_first_name')->label('Prénom(s)')->placeholder('-')->toggleable(),
                TextColumn::make('ine_last_name')->label('Nom')->placeholder('-')->toggleable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('inee')->label('INEE')->searchable()->sortable(),
                TextColumn::make('ine_status')->label('Statut INE')->badge()
                    ->color(fn (?string $state): string => match ($state) { 'verified' => 'success', 'rejected' => 'danger', 'suspended' => 'gray', default => 'warning' })
                    ->placeholder('Non déclaré'),
                TextColumn::make('ine_verified_at')->label('Vérifié le')->dateTime('d/m/Y H:i')->placeholder('-'),
                TextColumn::make('ine_verified_by_name')->label('Vérifié par')->placeholder('-'),
                TextColumn::make('ine_created_at')->label('Créé le')->dateTime('d/m/Y H:i')->placeholder('-')->toggleable(),
                TextColumn::make('account_type')->label('Type')->badge()->sortable(),
                TextColumn::make('classification_university_name')->label('Université')->placeholder('-')->searchable()->sortable(),
                TextColumn::make('filiere')->label('Filière / domaine')->placeholder('-')->searchable()->sortable(),
                TextColumn::make('status')->badge(),
            ])
            ->filters([
                SelectFilter::make('account_type')->label('Type')->options([
                    'admin' => 'Administrateur', 'etudiant' => 'Étudiant', 'chercheur' => 'Chercheur',
                    'universite' => 'Responsable universitaire', 'formateur' => 'Formateur',
                ]),
                SelectFilter::make('university_id')->label('Université')->options(fn (): array => \App\Models\University::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when($data['value'] ?? null, fn (Builder $query, string $university): Builder => $query->where(function (Builder $query) use ($university): void {
                            $query->where('users.university_id', $university)
                                ->orWhere('student_profiles.university_id', $university)
                                ->orWhere('researcher_profiles.university_id', $university);
                        }));
                    }),
                Filter::make('filiere')->label('Filière / domaine')
                    ->form([TextInput::make('value')->label('Rechercher une filière ou un domaine')])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(filled($data['value'] ?? null), fn (Builder $query): Builder => $query->where(function (Builder $query) use ($data): void {
                            $value = '%' . $data['value'] . '%';
                            $query->where('student_profiles.program', 'like', $value)
                                ->orWhere('researcher_profiles.speciality', 'like', $value)
                                ->orWhere('researcher_profiles.research_domain', 'like', $value);
                        }));
                    }),
                Filter::make('inee')->label('INEE')
                    ->form([TextInput::make('value')->label('Code INEE')])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(filled($data['value'] ?? null), fn (Builder $query): Builder => $query->where('student_profiles.inee', $data['value']))),
                SelectFilter::make('ine_status')->label('Statut INE')->options([
                    'verified' => 'Vérifié', 'pending' => 'En attente', 'rejected' => 'Rejeté', 'suspended' => 'Suspendu',
                ])->query(fn (Builder $query, array $data): Builder => $query->when($data['value'] ?? null, fn (Builder $query, string $status): Builder => $query->where('student_profiles.ine_status', $status))),
                SelectFilter::make('status')->label('Statut')->options(['invited' => 'Invité', 'active' => 'Actif', 'suspended' => 'Suspendu', 'disabled' => 'Désactivé']),
            ])
            ->recordActions([
                Action::make('ineDetails')
                    ->label('Détails INE')
                    ->icon('heroicon-o-identification')
                    ->schema([
                        TextInput::make('name')->label('Étudiant')->disabled(),
                        TextInput::make('ine_first_name')->label('Prénom(s)')->disabled(),
                        TextInput::make('ine_last_name')->label('Nom')->disabled(),
                        TextInput::make('inee')->label('INE')->disabled(),
                        TextInput::make('ine_status')->label('Statut')->disabled(),
                        TextInput::make('ine_verified_at')->label('Vérifié le')->disabled(),
                        TextInput::make('ine_verified_by_name')->label('Vérifié par')->disabled(),
                        TextInput::make('ine_created_at')->label('Créé le')->disabled(),
                    ])
                    ->fillForm(fn (User $record): array => [
                        'name' => $record->name,
                        'ine_first_name' => $record->ine_first_name,
                        'ine_last_name' => $record->ine_last_name,
                        'inee' => $record->inee,
                        'ine_status' => $record->ine_status,
                        'ine_verified_at' => $record->ine_verified_at,
                        'ine_verified_by_name' => $record->ine_verified_by_name,
                        'ine_created_at' => $record->ine_created_at,
                    ])
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fermer')
                    ->visible(fn (User $record): bool => $record->account_type === 'etudiant' && Gate::allows('users.view') && filled($record->inee)),
                Action::make('verifyIne')->label('Vérifier l’INE')->icon('heroicon-o-check-circle')->color('success')
                    ->requiresConfirmation()->action(fn (User $record) => app(\App\Services\IneAdministrationService::class)->changeStatus(Auth::user(), $record, 'verified'))
                    ->visible(fn (User $record): bool => $record->account_type === 'etudiant' && filled($record->inee) && in_array($record->ine_status, ['pending', 'rejected'], true) && Gate::allows('users.update')),
                Action::make('rejectIne')->label('Rejeter l’INE')->icon('heroicon-o-x-circle')->color('danger')
                    ->requiresConfirmation()->action(fn (User $record) => app(\App\Services\IneAdministrationService::class)->changeStatus(Auth::user(), $record, 'rejected'))
                    ->visible(fn (User $record): bool => $record->account_type === 'etudiant' && filled($record->inee) && $record->ine_status === 'pending' && Gate::allows('users.update')),
                Action::make('suspendIne')->label('Suspendre l’INE')->icon('heroicon-o-no-symbol')->color('warning')
                    ->requiresConfirmation()->action(fn (User $record) => app(\App\Services\IneAdministrationService::class)->changeStatus(Auth::user(), $record, 'suspended'))
                    ->visible(fn (User $record): bool => $record->account_type === 'etudiant' && filled($record->inee) && $record->ine_status === 'verified' && Gate::allows('users.update')),
                Action::make('reactivateIne')->label('Réactiver l’INE')->icon('heroicon-o-arrow-path')->color('success')
                    ->requiresConfirmation()->action(fn (User $record) => app(\App\Services\IneAdministrationService::class)->changeStatus(Auth::user(), $record, 'verified'))
                    ->visible(fn (User $record): bool => $record->account_type === 'etudiant' && filled($record->inee) && $record->ine_status === 'suspended' && Gate::allows('users.update')),
                Action::make('invite')
                    ->label('Renvoyer l’invitation')
                    ->icon('heroicon-o-envelope')
                    ->visible(fn (User $record): bool => $record->account_type !== 'etudiant' && Gate::allows('users.update'))
                    ->action(fn (User $record): string => app(\App\Services\AccountInvitationService::class)->invite($record, Auth::user())),
                EditAction::make()
                    ->visible(fn (): bool => Gate::allows('users.update')),
                DeleteAction::make()
                    ->visible(fn (): bool => Gate::allows('users.delete')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => Gate::allows('users.delete')),
                    ])->visible(fn (): bool => Gate::allows('users.delete')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsers::route('/'),
        ];
    }

}
