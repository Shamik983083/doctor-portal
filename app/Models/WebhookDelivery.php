<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WebhookDelivery extends Model
{
    protected $table = 'webhook_deliveries';

    protected $fillable = [
        'uuid', 'webhook_id', 'event_type', 'payload', 'status',
        'attempts', 'max_attempts', 'last_attempted_at', 'next_retry_at',
        'response_code', 'response_body',
    ];

    protected $casts = [
        'payload' => 'array',
        'last_attempted_at' => 'datetime',
        'next_retry_at' => 'datetime',
    ];

    const STATUS_PENDING   = 'pending';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_FAILED    = 'failed';
    const STATUS_RETRYING  = 'retrying';

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($m) => $m->uuid = $m->uuid ?? (string) Str::uuid());
    }

    public function webhook() { return $this->belongsTo(Webhook::class); }

    public function canRetry(): bool
    {
        return $this->attempts < $this->max_attempts && $this->status !== self::STATUS_DELIVERED;
    }
}
