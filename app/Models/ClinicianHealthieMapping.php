<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stores the Healthie user ID for a clinician within a specific partner sub-org.
 *
 * A global clinician who works across N storefronts has N rows here,
 * one per partner. Each row carries the Healthie user ID that was created
 * (or found) in that sub-org's context.
 *
 * At prescription time, EhrRecordService looks up the row keyed on
 * (clinician_id, partner_id) to get the correct Healthie user ID for that
 * specific storefront, rather than using the static default_provider_id.
 */
class ClinicianHealthieMapping extends Model
{
    const STATUS_PENDING = 'pending';
    const STATUS_SYNCED  = 'synced';
    const STATUS_FAILED  = 'failed';

    protected $fillable = [
        'clinician_id',
        'partner_id',
        'sub_storefront_id',
        'healthie_user_id',
        'healthie_org_id',
        'status',
        'last_error',
        'synced_at',
    ];

    protected $casts = [
        'synced_at' => 'datetime',
    ];

    public function clinician(): BelongsTo
    {
        return $this->belongsTo(Clinician::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function subStorefront(): BelongsTo
    {
        return $this->belongsTo(SubStorefront::class);
    }
}
