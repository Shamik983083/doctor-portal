<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Tag extends Model
{
    protected $fillable = ['name', 'slug', 'partner_id', 'type', 'color', 'description'];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($m) {
            $m->slug = $m->slug ?? Str::slug($m->name);
        });
    }

    public function partner() { return $this->belongsTo(Partner::class); }
    public function cases() { return $this->belongsToMany(PatientCase::class, 'case_tags', 'tag_id', 'case_id')->withPivot('notes'); }
    public function patients() { return $this->belongsToMany(Patient::class, 'patient_tags')->withPivot('notes'); }
}
