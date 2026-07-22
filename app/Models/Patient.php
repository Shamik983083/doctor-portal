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
        'date_of_birth', 'age', 'height', 'weight', 'bmi',
        'gender', 'address', 'address2',
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

    /**
     * Restrict to patients this admin may see (Devin msg 2117).
     *
     * A Doctor Admin sees a patient only if that patient has at least one case
     * belonging to one of their doctors. Patient records are PHI, so an admin
     * over nobody sees nobody, and a patient whose cases all sit with other
     * doctors is invisible.
     *
     * See PatientCase::scopeVisibleTo for why an empty id list must stay empty
     * rather than being read as "no restriction".
     *
     * TRACKS THE CASE SCOPE, DELIBERATELY. Since a Doctor Admin can now see
     * unassigned cases, they must also be able to see the PATIENT behind one,
     * otherwise the intake queue lists a case whose patient record 404s and the
     * admin cannot act on what they were just shown. Same three outcomes, same
     * load-bearing grouping, same empty-list-sees-nothing guarantee.
     */
    public function scopeVisibleTo($query, ?User $user)
    {
        $ids = $user?->visibleClinicianIds();

        if ($ids === null) {
            return $query;
        }

        if ($ids === []) {
            return $query->whereHas('cases', fn ($q) => $q->whereIn('clinician_id', $ids));
        }

        return $query->whereHas('cases', fn ($q) => $q->where(function ($w) use ($ids) {
            $w->whereIn('clinician_id', $ids)
              ->orWhereNull('clinician_id');
        }));
    }

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

    public function getAgeAttribute(): ?int
    {
        if (!is_null($this->attributes['age'] ?? null)) {
            return (int) $this->attributes['age'];
        }
        return $this->date_of_birth ? (int) $this->date_of_birth->age : null;
    }
}
