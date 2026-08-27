<?php
namespace App\Filament\Resources\MessageThreads;
use App\Filament\Resources\BaseCrudResource; use App\Filament\Resources\MessageThreads\Pages\ManageMessageThreads; use App\Models\MessageThread; use BackedEnum;
class MessageThreadResource extends BaseCrudResource { protected static ?string $model=MessageThread::class; protected static string|BackedEnum|null $navigationIcon='heroicon-o-chat-bubble-left-right'; protected static ?string $navigationLabel='Messagerie'; protected static string $permission='notifications.send'; protected static array $fields=[['name'=>'subject'],['name'=>'status','required'=>true]]; public static function getPages():array{return ['index'=>ManageMessageThreads::route('/')];} }
