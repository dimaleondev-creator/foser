<?php
namespace App\Filament\Resources\Documents;
use App\Filament\Resources\BaseCrudResource;
use App\Filament\Resources\Documents\Pages\ManageDocuments;
use App\Models\Document;
use BackedEnum;
class DocumentResource extends BaseCrudResource { protected static ?string $model=Document::class; protected static string|BackedEnum|null $navigationIcon='heroicon-o-folder-open'; protected static ?string $navigationLabel='Documents'; protected static string $permission='documents.view'; protected static array $fields=[['name'=>'title','required'=>true],['name'=>'description','type'=>'textarea'],['name'=>'document_type','required'=>true],['name'=>'year','type'=>'number'],['name'=>'author'],['name'=>'language','required'=>true],['name'=>'status','required'=>true],['name'=>'published_at','type'=>'date'],['name'=>'path','required'=>true],['name'=>'disk','required'=>true],['name'=>'visibility','required'=>true]]; protected static array $tableColumns=[['name'=>'title','searchable'=>true],['name'=>'document_type'],['name'=>'year'],['name'=>'language'],['name'=>'status'],['name'=>'published_at'],['name'=>'downloads_count']]; public static function getPages():array{return ['index'=>ManageDocuments::route('/')];} }
