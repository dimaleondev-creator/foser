<?php
namespace App\Filament\Resources\MediaAlbums;
use App\Filament\Resources\BaseCrudResource; use App\Filament\Resources\MediaAlbums\Pages\ManageMediaAlbums; use App\Models\MediaAlbum; use BackedEnum;
class MediaAlbumResource extends BaseCrudResource { protected static ?string $model=MediaAlbum::class; protected static string|BackedEnum|null $navigationIcon='heroicon-o-rectangle-stack'; protected static ?string $navigationLabel='Albums média'; protected static string $permission='content.view'; protected static array $fields=[['name'=>'name','required'=>true],['name'=>'slug','required'=>true],['name'=>'description','type'=>'textarea'],['name'=>'status','required'=>true]]; public static function getPages():array{return ['index'=>ManageMediaAlbums::route('/')];} }
