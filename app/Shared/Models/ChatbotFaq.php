<?php

namespace App\Shared\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Pregunta frecuente del chatbot (RC-490).
 */
class ChatbotFaq extends Model
{
    use HasFactory;

    protected $table = 'chatbot_faqs';

    protected $fillable = [
        'question',
        'answer',
        'keywords',
        'category',
        'is_active',
        'sort_order',
        'hits',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'hits' => 'integer',
    ];

    /**
     * @return array<int, string>
     */
    public function keywordList(): array
    {
        return collect(explode(',', (string) $this->keywords))
            ->map(fn ($k) => mb_strtolower(trim($k)))
            ->filter()
            ->values()
            ->all();
    }
}
