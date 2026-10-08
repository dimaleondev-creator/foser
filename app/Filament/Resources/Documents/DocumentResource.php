<?php

namespace App\Filament\Resources\Documents;

use App\Filament\Resources\BaseCrudResource;
use App\Filament\Resources\Documents\Pages\ManageDocuments;
use App\Models\Document;
use App\Models\DocumentCategory;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class DocumentResource extends BaseCrudResource
{
    protected static ?string $model = Document::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-folder-open';
    protected static ?string $navigationLabel = 'Documents officiels';
    protected static string $permission = 'documents.view';
    protected static ?string $managePermission = 'documents.update';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->label('Titre')->required()->maxLength(255),
            Textarea::make('description')->label('Description')->maxLength(5000)->columnSpanFull(),
            TextInput::make('keywords')->label('Mots-clés')->helperText('Séparer les mots-clés par des virgules.')->maxLength(2000)->columnSpanFull(),
            Select::make('category_id')->label('Catégorie')->options(fn (): array => DocumentCategory::query()->orderBy('name')->pluck('name', 'id')->all())->searchable()->preload()->required(),
            TextInput::make('author')->label('Auteur / organisme')->maxLength(255),
            DatePicker::make('document_date')->label('Date du document'),
            TextInput::make('year')->label('Année')->numeric()->integer()->minValue(1900)->maxValue(2100),
            TextInput::make('reference')->label('Numéro / référence')->maxLength(120),
            Select::make('document_type')->label('Type de document')->options([
                'loi' => 'Loi', 'decret' => 'Décret', 'arrete' => 'Arrêté', 'reglement' => 'Règlement',
                'guide' => 'Guide', 'rapport' => 'Rapport', 'rapport_annuel' => 'Rapport annuel', 'etude' => 'Étude', 'formulaire' => 'Formulaire',
                'manuel' => 'Manuel', 'texte_reglementaire' => 'Texte réglementaire', 'institutionnel' => 'Document institutionnel', 'autre' => 'Autre document officiel',
            ])->searchable()->required(),
            TextInput::make('version_label')->label('Version')->maxLength(50),
            Select::make('language')->label('Langue')->options(['fr' => 'Français', 'en' => 'Anglais', 'pt' => 'Portugais', 'ar' => 'Arabe'])->default('fr')->required(),
            Select::make('status')->label('Statut')->options(fn (?Document $record): array => Gate::allows('documents.validate')
                ? ['draft' => 'Brouillon', 'published' => 'Publié', 'archived' => 'Archivé']
                : array_filter(['draft' => 'Brouillon', 'published' => $record?->status === 'published' ? 'Publié' : null, 'archived' => 'Archivé']))->default('draft')->required(),
            Select::make('visibility')->label('Visibilité')->options(fn (?Document $record): array => Gate::allows('documents.validate')
                ? ['public' => 'Publique', 'private' => 'Privée']
                : array_filter(['public' => $record?->visibility === 'public' ? 'Publique' : null, 'private' => 'Privée']))->default('private')->required(),
            DateTimePicker::make('published_at')->label('Date de publication')->seconds(false),
            FileUpload::make('path')
                ->label('Fichier officiel')
                ->disk('local')
                ->directory('official-documents')
                ->acceptedFileTypes([
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/vnd.ms-powerpoint',
                    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                    'application/vnd.oasis.opendocument.text',
                    'application/vnd.oasis.opendocument.spreadsheet',
                    'application/vnd.oasis.opendocument.presentation',
                    'text/plain',
                ])
                ->maxSize(20480)
                ->rules(['extensions:pdf,doc,docx,xls,xlsx,ppt,pptx,odt,ods,odp,txt'])
                ->getUploadedFileNameForStorageUsing(fn (TemporaryUploadedFile $file): string => Str::uuid().'.'.($file->guessExtension() ?: 'bin'))
                ->required(fn (?Document $record): bool => $record === null),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('title')->label('Titre')->searchable()->sortable()->limit(60),
            TextColumn::make('category.name')->label('Catégorie')->searchable(),
            TextColumn::make('keywords')->label('Mots-clés')->searchable()->limit(60),
            TextColumn::make('document_type')->label('Type / référence')->searchable(),
            TextColumn::make('author')->label('Auteur / organisme')->searchable(),
            TextColumn::make('reference')->label('Référence')->searchable(),
            TextColumn::make('document_date')->label('Date du document')->date('d/m/Y')->sortable(),
            TextColumn::make('year')->label('Année')->sortable(),
            TextColumn::make('language')->label('Langue')->badge(),
            TextColumn::make('status')->label('Statut')->badge(),
            TextColumn::make('visibility')->label('Visibilité')->badge(),
            TextColumn::make('versions_count')->counts('versions')->label('Versions')->sortable(),
            TextColumn::make('downloads_count')->counts('downloads')->label('Téléchargements')->sortable(),
            TextColumn::make('updated_at')->label('Modifié le')->dateTime('d/m/Y H:i')->sortable(),
        ])->filters([
            SelectFilter::make('category_id')->label('Catégorie')->relationship('category', 'name')->searchable()->preload(),
            SelectFilter::make('document_type')->label('Type')->options(fn (): array => Document::query()->distinct()->orderBy('document_type')->pluck('document_type', 'document_type')->all()),
            SelectFilter::make('language')->options(['fr' => 'Français', 'en' => 'Anglais', 'pt' => 'Portugais', 'ar' => 'Arabe']),
            SelectFilter::make('status')->options(['draft' => 'Brouillon', 'published' => 'Publié', 'archived' => 'Archivé']),
            SelectFilter::make('visibility')->options(['public' => 'Publique', 'private' => 'Privée']),
            SelectFilter::make('year')->options(fn (): array => Document::query()->whereNotNull('year')->distinct()->orderByDesc('year')->pluck('year', 'year')->all()),
            TrashedFilter::make(),
        ])->recordActions([
            Action::make('history')->label('Historique')->icon('heroicon-o-clock')->modalHeading('Historique des versions')->modalContent(fn (Document $record) => view('filament.documents.versions', ['versions' => $record->versions()->latest('version')->get()]))->modalSubmitAction(false)->modalCancelActionLabel('Fermer')->visible(fn (): bool => Gate::any(['documents.update', 'documents.validate'])),
            EditAction::make()->authorize('documents.update')->visible(fn (): bool => Gate::allows('documents.update')),
            Action::make('deletePermanently')->label('Supprimer définitivement')->icon('heroicon-o-trash')->color('danger')->requiresConfirmation()->modalDescription('Le document et tous ses fichiers de version seront supprimés définitivement.')->action(fn (Document $record) => app(\App\Services\DocumentFileService::class)->deletePermanently($record))->visible(fn (): bool => Gate::allows('documents.delete')),
        ])->defaultSort('updated_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereNotIn('documents.id', function ($query): void {
                $query->select('document_id')->from('application_documents');
            })
            ->whereNotIn('documents.id', function ($query): void {
                $query->select('document_id')->from('research_project_documents');
            })
            ->whereNotIn('documents.id', function ($query): void {
                $query->select('document_id')->from('research_publications')->whereNotNull('document_id');
            })
            ->whereNotIn('documents.id', function ($query): void {
                $query->select('document_id')->from('research_conventions')->whereNotNull('document_id');
            })
            ->whereNotIn('documents.id', function ($query): void {
                $query->select('document_id')->from('call_documents');
            })
            ->whereNotIn('documents.id', function ($query): void {
                $query->select('cv_document_id')->from('researcher_profiles')->whereNotNull('cv_document_id');
            });
    }

    public static function getPages(): array
    {
        return ['index' => ManageDocuments::route('/')];
    }
}