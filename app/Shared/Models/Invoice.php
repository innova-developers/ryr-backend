<?php

namespace App\Shared\Models;

use App\Shared\Enums\InvoiceStatus;
use App\Shared\Enums\InvoiceType;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'customer_id',
        'commission_id',
        'current_account_id',
        'franchise_id',
        'branch_id',
        'user_id',
        'tipo_comprobante',
        'punto_venta',
        'numero_comprobante',
        'fecha_emision',
        'cae',
        'cae_vencimiento',
        'importe_total',
        'importe_neto',
        'importe_iva',
        'iva_rate',
        'doc_tipo',
        'doc_numero',
        'razon_social',
        'domicilio_cliente',
        'condicion_iva',
        'concepto',
        'status',
        'observaciones',
    ];

    protected $casts = [
        'fecha_emision' => 'date',
        'importe_total' => 'decimal:2',
        'importe_neto' => 'decimal:2',
        'importe_iva' => 'decimal:2',
        'iva_rate' => 'decimal:2',
        'status' => InvoiceStatus::class,
    ];

    protected static function newFactory(): InvoiceFactory
    {
        return InvoiceFactory::new();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function commission(): BelongsTo
    {
        return $this->belongsTo(Commission::class);
    }

    public function currentAccount(): BelongsTo
    {
        return $this->belongsTo(CurrentAccount::class);
    }

    public function franchise(): BelongsTo
    {
        return $this->belongsTo(Franchise::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getInvoiceTypeAttribute(): ?InvoiceType
    {
        return InvoiceType::tryFrom($this->tipo_comprobante);
    }

    public function getFormattedNumberAttribute(): string
    {
        return str_pad($this->punto_venta, 5, '0', STR_PAD_LEFT)
            . '-'
            . str_pad($this->numero_comprobante, 8, '0', STR_PAD_LEFT);
    }

    public function getTypeLetterAttribute(): string
    {
        return $this->invoice_type?->letter() ?? '?';
    }

    public function getTypeLabelAttribute(): string
    {
        return $this->invoice_type?->label() ?? 'Desconocido';
    }
}
