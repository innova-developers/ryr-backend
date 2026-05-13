<?php

namespace App\Shared\Models;

use App\Shared\Enums\CampaignStatus;
use Database\Factories\WhatsAppCampaignFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WhatsAppCampaign extends Model
{
    use HasFactory, SoftDeletes;

    protected static function newFactory(): WhatsAppCampaignFactory
    {
        return WhatsAppCampaignFactory::new();
    }

    protected $table = 'whatsapp_campaigns';

    protected $fillable = [
        'name',
        'description',
        'message_template',
        'image_url',
        'status',
        'segment_filters',
        'message_delay_ms',
        'send_time_start',
        'send_time_end',
        'franchise_id',
        'created_by',
        'total_recipients',
        'sent_count',
        'failed_count',
        'scheduled_at',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'status' => CampaignStatus::class,
        'segment_filters' => 'array',
        'message_delay_ms' => 'integer',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function franchise(): BelongsTo
    {
        return $this->belongsTo(Franchise::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsAppCampaignMessage::class, 'campaign_id');
    }
}
