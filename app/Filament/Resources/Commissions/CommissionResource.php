<?php

namespace App\Filament\Resources\Commissions;

use App\Filament\Resources\Commissions\Pages\CreateCommission;
use App\Filament\Resources\Commissions\Pages\EditCommission;
use App\Filament\Resources\Commissions\Pages\ManageCommissions;
use App\Models\Application;
use App\Models\Commission;
use App\Models\CommissionDecision;
use App\Models\CommissionMember;
use App\Models\Call;
use App\Models\Program;
use App\Models\User;
use App\Services\CommissionWorkflowService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class CommissionResource extends Resource
{
    protected static ?string $model = Commission::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Commissions';
    protected static string|UnitEnum|null $navigationGroup = 'CANDIDATURES';
    protected static ?string $recordTitleAttribute = 'name';

    private static function actor(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    public static function canAccess(): bool
    {
        return Gate::allows('viewAny', Commission::class);
    }

    public static function canCreate(): bool
    {
        return Gate::allows('create', Commission::class);
    }

    public static function canEdit($record): bool
    {
        return Gate::allows('update', $record)
            && ($record->status === 'draft' || ($record->status === 'scheduled' && ! $record->convocation_sent_at));
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['call', 'program']);
        $user = Auth::user();

        if ($user instanceof User && ! $user->can('commissions.manage')) {
            $query->whereHas('members', fn (Builder $members) => $members->where('user_id', $user->id));
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nom de la commission')->required()->maxLength(255),
            Select::make('type')->label('Type')->options([
                'scholarship' => 'Bourses et aides',
                'research' => 'Recherche',
                'innovation' => 'Innovation',
                'other' => 'Autre',
            ])->required(),
            Select::make('program_id')->label('Programme')->options(fn (): array => Program::query()->where('status', 'published')->orderBy('name')->pluck('name', 'id')->all())->searchable()->preload()->live()->required(),
            Select::make('call_id')->label('Appel à candidatures')->options(fn (Get $get): array => Call::query()->where('program_id', $get('program_id'))->where('status', 'published')->orderByDesc('opens_at')->pluck('title', 'id')->all())->searchable()->required(),
            DateTimePicker::make('scheduled_at')->label('Date et heure')->seconds(false)->required(),
            TextInput::make('venue')->label('Lieu')->maxLength(255),
            TextInput::make('quorum_percentage')->label('Quorum obligatoire (%)')->numeric()->integer()->minValue(1)->maxValue(100)->default(50)->required(),
            Textarea::make('description')->label('Description')->maxLength(5000)->columnSpanFull(),
            Textarea::make('agenda')->label('Ordre du jour')->required()->maxLength(10000)->columnSpanFull(),
            Textarea::make('convocation_text')->label('Texte de convocation')->maxLength(10000)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Commission')->searchable()->sortable(),
            TextColumn::make('type')->label('Type')->badge(),
            TextColumn::make('program.name')->label('Programme')->searchable(),
            TextColumn::make('call.title')->label('Appel')->searchable()->limit(35),
            TextColumn::make('scheduled_at')->label('Réunion')->dateTime('d/m/Y H:i')->sortable(),
            TextColumn::make('status')->label('Statut')->badge(),
            TextColumn::make('members_count')->counts('members')->label('Membres'),
            TextColumn::make('applications_count')->counts('applications')->label('Dossiers'),
        ])->recordActions([
            Action::make('open')->label('Ouvrir le dossier')->icon('heroicon-o-arrow-top-right-on-square')->url(fn (Commission $record): string => route('commissions.show', $record)),
            EditAction::make()->label('Modifier')->visible(fn (Commission $record): bool => static::canEdit($record)),
            Action::make('add_member')->label('Ajouter un membre')->icon('heroicon-o-user-plus')->form([
                Select::make('user_id')->label('Personne')->options(fn (): array => User::query()->where('status', 'active')->whereIn('account_type', ['super_admin', 'admin', 'directeur_general', 'gestionnaire', 'agent_dossier', 'agent_finance', 'agent_recherche', 'evaluateur', 'chercheur', 'researcher'])->orderBy('name')->get()->mapWithKeys(fn (User $user): array => [$user->id => $user->name.' · '.$user->email])->all())->searchable()->required(),
                Select::make('role')->label('Rôle')->options(['president' => 'Président', 'secretary' => 'Secrétaire', 'rapporteur' => 'Rapporteur', 'member' => 'Membre'])->required(),
            ])->visible(fn (Commission $record): bool => Gate::allows('manageMembers', $record))->action(fn (Commission $record, array $data) => app(CommissionWorkflowService::class)->addMember(static::actor(), $record, (int) $data['user_id'], $data['role']))->successNotificationTitle('Membre ajouté.'),
            Action::make('remove_member')->label('Retirer un membre')->icon('heroicon-o-user-minus')->color('danger')->form(fn (Commission $record): array => [
                Select::make('member_id')->label('Membre')->options($record->members()->with('user')->get()->mapWithKeys(fn (CommissionMember $member): array => [$member->id => $member->user->name.' · '.$member->role])->all())->required(),
            ])->visible(fn (Commission $record): bool => Gate::allows('manageMembers', $record))->action(fn (Commission $record, array $data) => app(CommissionWorkflowService::class)->removeMember(static::actor(), $record, $data['member_id']))->requiresConfirmation()->successNotificationTitle('Membre retiré.'),
            Action::make('attach_application')->label('Ajouter un dossier')->icon('heroicon-o-document-plus')->form(fn (Commission $record): array => [
                Select::make('application_id')->label('Candidature')->options(fn (): array => Application::query()->where('call_id', $record->call_id)->where('program_id', $record->program_id)->where('workflow_status', 'commission_review')->whereHas('evaluations')->whereDoesntHave('evaluations', fn ($query) => $query->where('status', '!=', 'validated'))->with('applicant')->get()->mapWithKeys(fn (Application $application): array => [$application->id => $application->reference.' · '.($application->applicant?->name ?? 'Candidat')])->all())->searchable()->required(),
                Textarea::make('observations')->label('Observations')->maxLength(5000),
            ])->visible(fn (Commission $record): bool => Gate::allows('manageApplications', $record))->action(fn (Commission $record, array $data) => app(CommissionWorkflowService::class)->attachApplication(static::actor(), $record, $data['application_id'], $data['observations'] ?? null))->successNotificationTitle('Dossier ajouté à l’ordre du jour.'),
            Action::make('attendance')->label('Présences')->icon('heroicon-o-clipboard-document-check')->form(fn (Commission $record): array => [
                Select::make('member_id')->label('Membre')->options($record->members()->with('user')->get()->mapWithKeys(fn (CommissionMember $member): array => [$member->id => $member->user->name.' · '.$member->role])->all())->required(),
                Select::make('attendance_status')->label('Présence')->options(['present' => 'Présent', 'absent' => 'Absent'])->required(),
            ])->visible(fn (Commission $record): bool => Gate::allows('manageAttendance', $record) && $record->status === 'scheduled')->action(fn (Commission $record, array $data) => app(CommissionWorkflowService::class)->markAttendance(static::actor(), $record, $data['member_id'], $data['attendance_status']))->successNotificationTitle('Présence enregistrée.'),
            Action::make('schedule')->label('Planifier')->icon('heroicon-o-calendar')->visible(fn (Commission $record): bool => Gate::allows('update', $record) && $record->status === 'draft')->action(fn (Commission $record) => app(CommissionWorkflowService::class)->schedule(static::actor(), $record))->successNotificationTitle('Commission planifiée.'),
            Action::make('convocations')->label('Émettre les convocations')->icon('heroicon-o-envelope')->visible(fn (Commission $record): bool => Gate::allows('update', $record) && $record->status === 'scheduled' && ! $record->convocation_sent_at)->action(fn (Commission $record) => app(CommissionWorkflowService::class)->sendConvocations(static::actor(), $record))->requiresConfirmation()->successNotificationTitle('Convocations émises.'),
            Action::make('start')->label('Ouvrir la séance')->icon('heroicon-o-play')->visible(fn (Commission $record): bool => Gate::allows('update', $record) && $record->status === 'scheduled')->action(fn (Commission $record) => app(CommissionWorkflowService::class)->startMeeting(static::actor(), $record))->requiresConfirmation()->successNotificationTitle('Séance ouverte.'),
            Action::make('vote')->label('Voter')->icon('heroicon-o-hand-thumb-up')->form(fn (Commission $record): array => [
                Select::make('commission_application_id')->label('Dossier')->options($record->applications()->with('application')->get()->mapWithKeys(fn ($item): array => [$item->id => $item->application->reference])->all())->required(),
                Select::make('vote')->label('Vote')->options(['favorable' => 'Favorable', 'defavorable' => 'Défavorable', 'abstention' => 'Abstention'])->required(),
                Textarea::make('comment')->label('Observation confidentielle')->maxLength(3000),
            ])->visible(fn (Commission $record): bool => $record->members()->where('user_id', Auth::id())->where('attendance_status', 'present')->exists() && $record->status === 'in_progress')->action(fn (Commission $record, array $data) => app(CommissionWorkflowService::class)->castVote(static::actor(), $record, $data['commission_application_id'], $data['vote'], $data['comment'] ?? null))->successNotificationTitle('Vote enregistré.'),
            Action::make('propose_decision')->label('Proposer une décision')->icon('heroicon-o-scale')->form(fn (Commission $record): array => [
                Select::make('commission_application_id')->label('Dossier')->options($record->applications()->with('application')->get()->mapWithKeys(fn ($item): array => [$item->id => $item->application->reference])->all())->required(),
                Select::make('decision')->label('Délibération')->options(['accepted' => 'Accepté', 'rejected' => 'Rejeté', 'adjourned' => 'Ajourné', 'complement_requested' => 'Complément demandé'])->required(),
                Textarea::make('justification')->label('Justification')->required()->maxLength(10000),
            ])->visible(fn (Commission $record): bool => Gate::allows('decide', $record))->action(fn (Commission $record, array $data) => app(CommissionWorkflowService::class)->proposeDecision(static::actor(), $record, $data['commission_application_id'], $data['decision'], $data['justification']))->successNotificationTitle('Décision proposée pour validation.'),
            Action::make('validate_decision')->label('Valider une décision')->icon('heroicon-o-check-badge')->form(fn (Commission $record): array => [
                Select::make('decision_id')->label('Décision')->options($record->decisions()->where('status', 'pending_validation')->with('commissionApplication.application')->get()->mapWithKeys(fn (CommissionDecision $decision): array => [$decision->id => $decision->commissionApplication->application->reference.' · '.$decision->decision])->all())->required(),
            ])->visible(fn (Commission $record): bool => Gate::allows('validateDecision', $record) && $record->status === 'in_progress')->action(fn (Commission $record, array $data) => app(CommissionWorkflowService::class)->validateDecision(static::actor(), $record, $data['decision_id']))->requiresConfirmation()->successNotificationTitle('Décision validée et transmise au workflow.'),
            Action::make('minutes')->label('Procès-verbal')->icon('heroicon-o-document-text')->form([
                TextInput::make('summary')->label('Résumé')->required()->maxLength(1000),
                Textarea::make('content')->label('Procès-verbal')->required()->rows(12)->maxLength(30000),
                FileUpload::make('file_path')->label('Version PDF signée')->disk('local')->directory('commission-minutes')->acceptedFileTypes(['application/pdf'])->maxSize(20480),
            ])->visible(fn (Commission $record): bool => Gate::allows('manageMinutes', $record) && $record->status === 'in_progress')->action(fn (Commission $record, array $data) => app(CommissionWorkflowService::class)->saveMinutes(static::actor(), $record, $data['summary'], $data['content'], $data['file_path'] ?? null))->successNotificationTitle('Procès-verbal enregistré.'),
            Action::make('validate_minutes')->label('Valider le PV')->icon('heroicon-o-document-check')->visible(fn (Commission $record): bool => Gate::allows('validateMinutes', $record) && $record->status === 'in_progress' && $record->minutes?->status === 'draft')->action(fn (Commission $record) => app(CommissionWorkflowService::class)->validateMinutes(static::actor(), $record))->requiresConfirmation()->successNotificationTitle('Procès-verbal validé.'),
            Action::make('complete')->label('Clôturer la séance')->icon('heroicon-o-lock-closed')->visible(fn (Commission $record): bool => Gate::allows('update', $record) && $record->status === 'in_progress')->action(fn (Commission $record) => app(CommissionWorkflowService::class)->completeMeeting(static::actor(), $record))->requiresConfirmation()->successNotificationTitle('Séance clôturée.'),
            Action::make('cancel')->label('Annuler')->icon('heroicon-o-x-circle')->color('danger')->visible(fn (Commission $record): bool => Gate::allows('update', $record) && in_array($record->status, ['draft', 'scheduled'], true))->action(fn (Commission $record) => app(CommissionWorkflowService::class)->cancel(static::actor(), $record))->requiresConfirmation()->successNotificationTitle('Commission annulée.'),
            Action::make('archive')->label('Archiver')->icon('heroicon-o-archive-box')->visible(fn (Commission $record): bool => Gate::allows('update', $record) && in_array($record->status, ['completed', 'cancelled'], true))->action(fn (Commission $record) => app(CommissionWorkflowService::class)->archive(static::actor(), $record))->requiresConfirmation()->successNotificationTitle('Commission archivée.'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCommissions::route('/'),
            'create' => CreateCommission::route('/create'),
            'edit' => EditCommission::route('/{record}/edit'),
        ];
    }
}