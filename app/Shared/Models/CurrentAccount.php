<?php

namespace App\Shared\Models;

use App\Shared\Enums\CurrentAccountStatus;
use Database\Factories\CurrentAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CurrentAccount extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'customer_id',
        'type',
        'status',
        'amount',
        'description',
        'reference',
        'transaction_date',
        'balance',
        'payment_method',
        'observations',
        'user_id',
        'verified_by_user_id',
        'verified_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance' => 'decimal:2',
        'transaction_date' => 'date',
        'status' => CurrentAccountStatus::class,
        'verified_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    public function getFormattedAmountAttribute(): string
    {
        return '$' . number_format($this->amount, 2, ',', '.');
    }

    public function getFormattedBalanceAttribute(): string
    {
        return '$' . number_format($this->balance, 2, ',', '.');
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            'credit' => 'Ingreso',
            'debit' => 'Egreso',
            default => 'Desconocido',
        };
    }

    public function getPaymentMethodLabelAttribute(): string
    {
        return match ($this->payment_method) {
            'cash' => 'Efectivo',
            'transfer' => 'Transferencia',
            'check' => 'Cheque',
            'card' => 'Tarjeta',
            'other' => 'Otro',
            default => 'No especificado',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->status?->label() ?? 'Desconocido';
    }

    public static function newFactory(): CurrentAccountFactory
    {
        return CurrentAccountFactory::new();
    }
}
