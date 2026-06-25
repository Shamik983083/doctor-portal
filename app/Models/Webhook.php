<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Webhook extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid', 'partner_id', 'url', 'event_type', 'secret', 'status',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($m) {
            $m->uuid = $m->uuid ?? (string) Str::uuid();
            $m->secret = $m->secret ?? Str::random(32);
        });
    }

    public function partner() { return $this->belongsTo(Partner::class); }
    public function deliveries() { return $this->hasMany(WebhookDelivery::class); }
}
