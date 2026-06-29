<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfferingCategory extends Model
{
    protected $fillable = ['name', 'description', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function offerings()
    {
        return $this->hasMany(Offering::class, 'category_id');
    }
}
