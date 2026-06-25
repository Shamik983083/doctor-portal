<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Partner extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid', 'name', 'slug', 'email', 'phone', 'website', 'logo',
        'description', 'status', 'webhook_secret', 'settings',
        'oauth_client_id', 'client_id', 'client_secret',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    protected $hidden = ['client_secret'];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            $model->uuid = $model->uuid ?? (string) Str::uuid();
            $model->webhook_secret = $model->webhook_secret ?? Str::random(32);
        });
    }

    public function patients() { return $this->hasMany(Patient::class); }
    public function cases() { return $this->hasMany(PatientCase::class); }
    public function offerings() { return $this->hasMany(Offering::class); }
    public function webhooks() { return $this->hasMany(Webhook::class); }
    public function vouchers() { return $this->hasMany(Voucher::class); }
    public function subscriptions() { return $this->hasMany(PatientSubscription::class); }
    public function orders() { return $this->hasMany(Order::class); }
    public function tags() { return $this->hasMany(Tag::class); }
    public function questionnaires() { return $this->hasMany(Questionnaire::class); }
}
