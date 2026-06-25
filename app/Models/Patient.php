<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Patient extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid', 'partner_id', 'user_id', 'external_id',
        'first_name', 'last_name', 'email', 'phone',
        'date_of_birth', 'gender', 'address', 'address2',
        'city', 'state', 'zip', 'country', 'status',
        'dosespot_patient_id', 'email_opt_in', 'sms_opt_in',
        'id_verified_status', 'id_verified_at', 'settings',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'email_opt_in' => 'boolean',
        'sms_opt_in' => 'boolean',
        'id_verified_at' => 'datetime',
        'settings' => 'array',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($m) => $m->uuid = $m->uuid ?? (string) Str::uuid());
    }

    public function partner() { return $this->belongsTo(Partner::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function cases() { return $this->hasMany(PatientCase::class); }
    public function subscriptions() { return $this->hasMany(PatientSubscription::class); }
    public function vouchers() { return $this->hasMany(Voucher::class); }
    public function messages() { return $this->hasMany(Message::class); }
    public function orders() { return $this->hasMany(Order::class); }
    public function files() { return $this->hasMany(PatientFile::class); }
    public function preferredPharmacies() { return $this->belongsToMany(Pharmacy::class, 'patient_preferred_pharmacies')->withPivot('is_primary'); }
    public function tags() { return $this->belongsToMany(Tag::class, 'patient_tags')->withPivot('notes'); }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
