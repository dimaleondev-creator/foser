<?php

namespace App\Filament\Resources;

use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

abstract class BaseCrudResource extends Resource
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static string|UnitEnum|null $navigationGroup = 'FOSER';
    protected static string $permission = 'users.view';
    protected static ?string $managePermission = null;
    protected static array $fields = [];
    protected static array $tableColumns = [];

    protected static function fieldLabel(string $name): string
    {
        return [
            'id' => 'Identifiant',
            'name' => 'Nom',
            'title' => 'Titre',
            'body' => 'Contenu',
            'description' => 'Description',
            'subject' => 'Objet',
            'status' => 'Statut',
            'type' => 'Type',
            'slug' => 'Identifiant URL',
            'code' => 'Code',
            'reference' => 'Référence',
            'audience' => 'Public cible',
            'starts_at' => 'Date de début',
            'ends_at' => 'Date de fin',
            'published_at' => 'Date de publication',
            'created_at' => 'Date de création',
            'updated_at' => 'Date de modification',
            'user_id' => 'Utilisateur',
            'author_id' => 'Auteur',
            'program_id' => 'Programme',
            'call_id' => 'Appel à candidatures',
            'application_id' => 'Candidature',
            'applicant_id' => 'Candidat',
            'evaluator_id' => 'Évaluateur',
            'university_id' => 'Université',
            'category_id' => 'Catégorie',
            'amount' => 'Montant',
            'budget' => 'Budget',
            'currency' => 'Devise',
            'year' => 'Année',
            'comment' => 'Commentaire',
            'notes' => 'Notes',
        ][strtolower($name)] ?? ucfirst(str_replace('_', ' ', $name));
    }

    public static function canAccess(): bool { return Gate::allows(static::$permission); }

    protected static function canManage(): bool
    {
        $permission = static::$managePermission;

        if (! $permission) {
            $permission = match (static::$permission) {
                'finance.view' => 'finance.manage',
                'research.view' => 'research.manage',
                'settings.manage' => 'settings.manage',
                default => str_replace('.view', '.update', static::$permission),
            };
        }

        return Gate::allows($permission);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(array_map(function (array $field) {
            $component = match ($field['type'] ?? 'text') {
                'textarea' => Textarea::make($field['name']),
                'select' => Select::make($field['name'])->options($field['options'] ?? []),
                'date' => DatePicker::make($field['name']),
                'number' => TextInput::make($field['name'])->numeric(),
                default => TextInput::make($field['name']),
            };
            $component
                ->label($field['label'] ?? static::fieldLabel($field['name']))
                ->required($field['required'] ?? false);

            if ($component instanceof TextInput || $component instanceof Textarea) {
                $component->maxLength($field['max'] ?? 255);
            }

            return $component;
        }, static::$fields));
    }

    public static function table(Table $table): Table
    {
        return $table->columns(array_map(fn (array $column) => TextColumn::make($column['name'])->label($column['label'] ?? static::fieldLabel($column['name']))->searchable($column['searchable'] ?? true)->sortable(), static::$tableColumns ?: array_map(fn (array $field) => ['name' => $field['name']], static::$fields)))->recordActions([
            EditAction::make()->visible(fn () => static::canManage()),
            DeleteAction::make()->visible(fn () => static::canManage()),
        ]);
    }
}
