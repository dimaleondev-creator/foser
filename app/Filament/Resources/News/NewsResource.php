<?php
namespace App\Filament\Resources\News;
use App\Filament\Resources\BaseCrudResource; use App\Filament\Resources\News\Pages\ManageNews; use App\Models\News; use BackedEnum;
class NewsResource extends BaseCrudResource { protected static ?string $model=News::class; protected static string|BackedEnum|null $navigationIcon='heroicon-o-newspaper'; protected static ?string $navigationLabel='Actualités'; protected static string $permission='content.view'; protected static array $fields=[['name'=>'title','required'=>true],['name'=>'slug','required'=>true],['name'=>'excerpt'],['name'=>'body','type'=>'textarea','required'=>true],['name'=>'status','required'=>true],['name'=>'published_at','type'=>'date']]; public static function getPages():array{return ['index'=>ManageNews::route('/')];} }
