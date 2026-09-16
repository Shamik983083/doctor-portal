<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupervisorPhysician extends Model
{
    protected $fillable = [
        'user_id',
        'npi',
        'licensed_states',
    ];

    protected $casts = [
        'licensed_states' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getNameAttribute(): string
    {
        return $this->user->name ?? '';
    }

    public function getEmailAttribute(): string
    {
        return $this->user->email ?? '';
    }
}
