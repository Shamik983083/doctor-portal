<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Disease extends Model
{
    protected $fillable = ['icd_code', 'name', 'description'];

    public function cases() { return $this->belongsToMany(PatientCase::class, 'case_diseases', 'disease_id', 'case_id')->withPivot('is_primary'); }
}
