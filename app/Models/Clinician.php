<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Clinician extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid', 'user_id', 'npi', 'license_number', 'license_state',
        'specialty', 'credentials', 'status', 'is_available',
        'max_daily_cases', 'priority', 'licensed_states',
    ];

    protected $casts = [
        'is_available' => 'boolean',
        'licensed_states' => 'array',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($m) => $m->uuid = $m->uuid ?? (string) Str::uuid());
    }

    public function user() { return $this->belongsTo(User::class); }
    public function cases() { return $this->hasMany(PatientCase::class); }
    public function clinicalNotes() { return $this->hasMany(ClinicalNote::class); }
    public function messages() { return $this->hasMany(Message::class); }

    public function getFullNameAttribute(): string
    {
        return trim(($this->credentials ? $this->credentials . ' ' : '') . $this->user->name);
    }

    public function isLicensedInState(string $state): bool
    {
        $states = $this->licensed_states ?? [];
        if (empty($states)) return true;
        return collect($states)->pluck('state')->contains(strtoupper($state));
    }
}
