<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'partner_id',
        // Without this, AdminUserController::toggleActive()'s update() is
        // silently dropped by the mass-assignment guard, and the deactivate
        // button reports success while changing nothing.
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function clinician()
    {
        return $this->hasOne(Clinician::class);
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    /* ------------------------------------------------------------------
       Two-tier admin (Devin msg 2117).

       super_admin  = us. Sees everything, owns every integration.
       admin        = a Doctor Admin. Sees only the doctors they are over.

       Scoping is by CLINICIAN, not by storefront. That is a deliberate
       divergence from MA-DOCPORTAL, which scopes admins to storefronts.
       ------------------------------------------------------------------ */

    /** The doctors this admin is over. Empty for a super admin, who is over all of them. */
    public function managedClinicians()
    {
        return $this->belongsToMany(Clinician::class, 'admin_clinician');
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    /** A Doctor Admin: scoped, and NOT a super admin. */
    public function isDoctorAdmin(): bool
    {
        return $this->hasRole('admin') && ! $this->isSuperAdmin();
    }

    /**
     * The clinician ids this user may see, or null meaning "no restriction".
     *
     * Null and an empty array mean very different things and callers must not
     * confuse them:
     *   null => super admin, see everything.
     *   []   => a Doctor Admin who has been assigned nobody yet, so they see
     *           NOTHING. That is the safe reading. Treating "assigned nobody"
     *           as "see everything" is the failure mode this method exists to
     *           prevent, and it is exactly what a falsy check would do.
     */
    public function visibleClinicianIds(): ?array
    {
        if ($this->isSuperAdmin()) {
            return null;
        }

        return $this->managedClinicians()->pluck('clinicians.id')->all();
    }
}
