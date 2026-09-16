<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicianSupervisorAssignment extends Model
{
    protected $fillable = [
        'clinician_id',
        'supervisor_physician_id',
        'state',
    ];

    public function clinician()
    {
        return $this->belongsTo(Clinician::class);
    }

    public function supervisorPhysician()
    {
        return $this->belongsTo(SupervisorPhysician::class);
    }
}
