<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class SubStorefront extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'partner_id',
        'name',
        'slug',
        'status',
        'collaborating_clinician_id',
        'healthie_api_key',
        'healthie_endpoint',
        'healthie_authorization_shard',
        'healthie_organization_id',
        'healthie_default_provider_id',
        'healthie_note_form_id',
        'healthie_default_group_id',
        'healthie_is_enabled',
        'healthie_sandbox_validated',
    ];

    protected $casts = [
        'healthie_api_key'          => 'encrypted',
        'healthie_is_enabled'       => 'boolean',
        'healthie_sandbox_validated' => 'boolean',
    ];

    protected $hidden = ['healthie_api_key'];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn ($m) => $m->uuid = $m->uuid ?? (string) Str::uuid());
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function collaboratingClinician(): BelongsTo
    {
        return $this->belongsTo(Clinician::class, 'collaborating_clinician_id');
    }

    /**
     * Clinicians explicitly assigned to this sub-storefront for case routing.
     * Global clinicians (is_global = true) are always eligible regardless of this.
     */
    public function clinicians(): BelongsToMany
    {
        return $this->belongsToMany(Clinician::class, 'clinician_sub_storefront')->withTimestamps();
    }

    public function cases(): HasMany
    {
        return $this->hasMany(PatientCase::class);
    }

    public function healthieMappings(): HasMany
    {
        return $this->hasMany(ClinicianHealthieMapping::class);
    }

    /**
     * True when this sub-storefront can actively push records to Healthie.
     * All four fields must be present; is_enabled and sandbox_validated must both
     * be true — enabling push is a deliberate act after sandbox validation.
     */
    public function isPushable(): bool
    {
        return $this->healthie_is_enabled
            && $this->healthie_sandbox_validated
            && ! empty($this->healthie_api_key)
            && ! empty($this->healthie_endpoint)
            && ! empty($this->healthie_organization_id);
    }

    /**
     * Returns the names of Healthie fields that are still missing.
     * An empty array means fully configured (though not necessarily enabled).
     */
    public function missingHealthieValues(): array
    {
        $missing = [];

        foreach (['healthie_api_key', 'healthie_endpoint', 'healthie_organization_id', 'healthie_default_provider_id'] as $field) {
            if (empty($this->$field)) {
                // Return friendly key names (strip the healthie_ prefix for display)
                $missing[] = str_replace('healthie_', '', $field);
            }
        }

        return $missing;
    }

    /**
     * Auth headers for Healthie API calls under this sub-storefront's credential.
     */
    public function authHeaders(): array
    {
        $headers = [
            'Authorization'       => 'Basic ' . $this->healthie_api_key,
            'AuthorizationSource' => 'API',
        ];

        if (! empty($this->healthie_authorization_shard)) {
            $headers['AuthorizationShard'] = $this->healthie_authorization_shard;
        }

        return $headers;
    }
}
