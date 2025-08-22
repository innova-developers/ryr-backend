<?php

namespace App;

use App\Shared\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CadetePayment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'cadete_id',
        'admin_id',
        'payment_type',
        'payment_method',
        'amount',
        'commission_amount',
        'base_salary',
        'bonus_amount',
        'deduction_amount',
        'net_amount',
        'status',
        'payment_date',
        'period_start',
        'period_end',
        'description',
        'notes',
        'reference_number',
        'transaction_id',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'base_salary' => 'decimal:2',
        'bonus_amount' => 'decimal:2',
        'deduction_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'payment_date' => 'date',
        'period_start' => 'date',
        'period_end' => 'date',
        'paid_at' => 'datetime',
    ];

    // Constantes para tipos de pago
    const PAYMENT_TYPE_MONTHLY = 'monthly';
    const PAYMENT_TYPE_BIWEEKLY = 'biweekly';
    const PAYMENT_TYPE_WEEKLY = 'weekly';
    const PAYMENT_TYPE_BONUS = 'bonus';
    const PAYMENT_TYPE_ADVANCE = 'advance';
    const PAYMENT_TYPE_OTHER = 'other';

    // Constantes para métodos de pago
    const PAYMENT_METHOD_CASH = 'cash';
    const PAYMENT_METHOD_BANK_TRANSFER = 'bank_transfer';
    const PAYMENT_METHOD_CHECK = 'check';
    const PAYMENT_METHOD_OTHER = 'other';

    // Constantes para estados
    const STATUS_PENDING = 'pending';
    const STATUS_PAID = 'paid';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Obtener tipos de pago disponibles
     */
    public static function getPaymentTypes(): array
    {
        return [
            self::PAYMENT_TYPE_MONTHLY => 'Mensual',
            self::PAYMENT_TYPE_BIWEEKLY => 'Quincenal',
            self::PAYMENT_TYPE_WEEKLY => 'Semanal',
            self::PAYMENT_TYPE_BONUS => 'Bono',
            self::PAYMENT_TYPE_ADVANCE => 'Adelanto',
            self::PAYMENT_TYPE_OTHER => 'Otro',
        ];
    }

    /**
     * Obtener métodos de pago disponibles
     */
    public static function getPaymentMethods(): array
    {
        return [
            self::PAYMENT_METHOD_CASH => 'Efectivo',
            self::PAYMENT_METHOD_BANK_TRANSFER => 'Transferencia Bancaria',
            self::PAYMENT_METHOD_CHECK => 'Cheque',
            self::PAYMENT_METHOD_OTHER => 'Otro',
        ];
    }

    /**
     * Obtener estados disponibles
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pendiente',
            self::STATUS_PAID => 'Pagado',
            self::STATUS_CANCELLED => 'Cancelado',
        ];
    }

    /**
     * Relación con el cadete
     */
    public function cadete(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cadete_id');
    }

    /**
     * Relación con el administrador que creó el pago
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /**
     * Scope para filtrar por cadete
     */
    public function scopeForCadete($query, $cadeteId)
    {
        return $query->where('cadete_id', $cadeteId);
    }

    /**
     * Scope para filtrar por estado
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope para filtrar por tipo de pago
     */
    public function scopeByPaymentType($query, $paymentType)
    {
        return $query->where('payment_type', $paymentType);
    }

    /**
     * Scope para filtrar por período
     */
    public function scopeByPeriod($query, $startDate, $endDate)
    {
        return $query->whereBetween('payment_date', [
            \Carbon\Carbon::parse($startDate)->startOfDay(),
            \Carbon\Carbon::parse($endDate)->endOfDay()
        ]);
    }

    /**
     * Marcar como pagado
     */
    public function markAsPaid(): bool
    {
        return $this->update([
            'status' => self::STATUS_PAID,
            'paid_at' => now(),
        ]);
    }

    /**
     * Marcar como cancelado
     */
    public function markAsCancelled(): bool
    {
        return $this->update([
            'status' => self::STATUS_CANCELLED,
        ]);
    }

    /**
     * Verificar si está pagado
     */
    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /**
     * Verificar si está pendiente
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Verificar si está cancelado
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Obtener el label del tipo de pago
     */
    public function getPaymentTypeLabelAttribute(): string
    {
        return self::getPaymentTypes()[$this->payment_type] ?? $this->payment_type;
    }

    /**
     * Obtener el label del método de pago
     */
    public function getPaymentMethodLabelAttribute(): string
    {
        return self::getPaymentMethods()[$this->payment_method] ?? $this->payment_method;
    }

    /**
     * Obtener el label del estado
     */
    public function getStatusLabelAttribute(): string
    {
        return self::getStatuses()[$this->status] ?? $this->status;
    }

    /**
     * Calcular el monto neto automáticamente
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($payment) {
            if (empty($payment->net_amount)) {
                $payment->net_amount = $payment->base_salary + $payment->commission_amount + $payment->bonus_amount - $payment->deduction_amount;
            }
        });

        static::updating(function ($payment) {
            if ($payment->isDirty(['base_salary', 'commission_amount', 'bonus_amount', 'deduction_amount'])) {
                $payment->net_amount = $payment->base_salary + $payment->commission_amount + $payment->bonus_amount - $payment->deduction_amount;
            }
        });
    }
}
