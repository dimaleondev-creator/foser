<?php
namespace App\Filament\Resources\Payments;
use App\Filament\Resources\BaseCrudResource;
use App\Filament\Resources\Payments\Pages\ManagePayments;
use App\Models\Payment;
use BackedEnum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentResource extends BaseCrudResource
{
	protected static ?string $model = Payment::class;
	protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-currency-dollar';
	protected static ?string $navigationLabel = 'Paiements';
	protected static string $permission = 'finance.view';

	public static function table(Table $table): Table
	{
		return $table->columns([
			TextColumn::make('provider_reference')->label('Référence')->searchable(),
			TextColumn::make('beneficiary_id')->label('Bénéficiaire')->searchable(),
			TextColumn::make('amount')->label('Montant')->money('XOF'),
			TextColumn::make('payment_method')->label('Moyen'),
			TextColumn::make('status')->label('Statut')->badge(),
			TextColumn::make('paid_at')->label('Date de paiement')->dateTime(),
		]);
	}

	public static function getPages(): array
	{
		return ['index' => ManagePayments::route('/')];
	}
}
