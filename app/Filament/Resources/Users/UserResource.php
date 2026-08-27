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
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->leftJoin('student_profiles', 'student_profiles.user_id', '=', 'users.id')
                ->leftJoin('researcher_profiles', 'researcher_profiles.user_id', '=', 'users.id')
                ->leftJoin('universities as classification_university', function ($join): void {
                    $join->on('classification_university.id', '=', 'users.university_id')
                        ->orOn('classification_university.id', '=', 'student_profiles.university_id')
                        ->orOn('classification_university.id', '=', 'researcher_profiles.university_id');
                })
                ->select('users.*')
                ->addSelect('student_profiles.inee')
                ->selectRaw("COALESCE(student_profiles.program, researcher_profiles.speciality, researcher_profiles.research_domain, '-') as filiere")
                ->selectRaw("COALESCE(classification_university.name, '-') as classification_university_name")
                ->orderBy('users.account_type')
                ->orderBy('classification_university.name')
                ->orderByRaw("COALESCE(student_profiles.program, researcher_profiles.speciality, researcher_profiles.research_domain, '')")
                ->orderBy('users.name'))
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('inee')->label('INEE')->searchable()->sortable(),
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
                SelectFilter::make('status')->label('Statut')->options(['invited' => 'Invité', 'active' => 'Actif', 'suspended' => 'Suspendu', 'disabled' => 'Désactivé']),
            ])
            ->recordActions([
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
