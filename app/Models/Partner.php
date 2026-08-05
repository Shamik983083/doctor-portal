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
        // E19: default collaborating clinician copied onto new patients from this storefront
        'collaborating_clinician_id',
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

    public function collaboratingClinician() { return $this->belongsTo(Clinician::class, 'collaborating_clinician_id'); }

    public function users() { return $this->hasMany(User::class); }
    public function patients() { return $this->hasMany(Patient::class); }
    public function cases() { return $this->hasMany(PatientCase::class); }

    // Ownership: offerings this partner created (partner_id FK).
    // Used for management endpoints (create / edit / delete).
    public function offerings() { return $this->hasMany(Offering::class); }

    // Access: offerings this partner may prescribe (Phase 1a pivot).
    // Use this for case creation, prescribe form, and routing lookups.
    public function accessibleOfferings()
    {
        return $this->belongsToMany(Offering::class, 'offering_partner')
            ->withPivot('sig_override', 'is_active')
            ->withTimestamps()
            ->wherePivot('is_active', true);
    }

    public function webhooks() { return $this->hasMany(Webhook::class); }
    public function vouchers() { return $this->hasMany(Voucher::class); }

    /** Per-company EHR credentials. One row per provider; there is no shared credential. */
    public function ehrSettings() { return $this->hasMany(PartnerEhrSetting::class); }
    public function healthieSettings() { return $this->hasOne(PartnerEhrSetting::class)->where('provider', 'healthie'); }
    public function subscriptions() { return $this->hasMany(PatientSubscription::class); }
    public function orders() { return $this->hasMany(Order::class); }
    public function tags() { return $this->hasMany(Tag::class); }
    public function questionnaires() { return $this->hasMany(Questionnaire::class); }
    public function productPlans()   { return $this->hasMany(PartnerProductPlan::class); }
}
