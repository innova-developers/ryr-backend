<?php

namespace App\Shared\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedbackSurvey extends Model
{
    use HasFactory;

    protected $fillable = [
        'commission_id',
        'customer_id',
        'franchise_id',
        'rating',
        'comment',
        'status',
        'token',
        'sent_at',
        'responded_at',
    ];

    protected $casts = [
        'rating' => 'integer',
        'sent_at' => 'datetime',
        'responded_at' => 'datetime',
    ];

    protected static function newFactory(): \Database\Factories\FeedbackSurveyFactory
    {
        return \Database\Factories\FeedbackSurveyFactory::new();
    }

    public function commission(): BelongsTo
    {
        return $this->belongsTo(Commission::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function franchise(): BelongsTo
    {
        return $this->belongsTo(Franchise::class);
    }
}
