<?php
namespace App\Filament\Resources\Applications;
use App\Filament\Resources\BaseCrudResource;
use App\Filament\Resources\Applications\Pages\ManageApplications;
use App\Models\Application;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Gate;
class ApplicationResource extends BaseCrudResource { protected static ?string $model=Application::class; protected static string|BackedEnum|null $navigationIcon='heroicon-o-document-text'; protected static ?string $navigationLabel='Candidatures'; protected static string $permission='applications.view'; protected static array $fields=[['name'=>'call_id','required'=>true],['name'=>'program_id','required'=>true],['name'=>'applicant_id','required'=>true],['name'=>'reference','required'=>true],['name'=>'status','required'=>true],['name'=>'applicant_note','type'=>'textarea']]; public static function table(Table $table): Table { return parent::table($table)->recordUrl(fn (Application $record): string => route('admin.applications.show', $record)); } public static function getPages():array{return ['index'=>ManageApplications::route('/')];} }
