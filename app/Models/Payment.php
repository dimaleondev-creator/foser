<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Payment extends Model
{
	use HasUuids;

	protected $table = 'payment_records';
	protected $fillable = ['disbursement_id', 'beneficiary_id', 'provider_reference', 'idempotency_key', 'payment_method', 'amount', 'status', 'paid_at', 'processed_by', 'initiated_by', 'comment', 'executed_at', 'cancelled_at'];

	protected function casts(): array
	{
		return ['paid_at' => 'datetime', 'executed_at' => 'datetime', 'cancelled_at' => 'datetime', 'amount' => 'decimal:2'];
	}

	public function disbursement(): BelongsTo
	{
		return $this->belongsTo(Disbursement::class);
	}

	protected static function booted(): void
	{
		static::updating(function (self $model): void {
			if ($model->getOriginal('status') === 'paid') {
				throw new \LogicException('Un paiement confirmé ne peut pas être modifié directement.');
			}
		});

		static::deleting(function (self $model): void {
			if (in_array($model->status, ['valide', 'execute', 'paid', 'annule', 'reversed'], true)) {
				throw new \LogicException('Une opération financière validée ne peut pas être supprimée.');
			}
		});
	}
}
