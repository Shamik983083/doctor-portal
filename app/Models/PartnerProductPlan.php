<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerProductPlan extends Model
{
    protected $table = 'partner_product_plans';

    protected $fillable = [
        'partner_id', 'product_key', 'offering_id', 'month_frequency', 'label',
    ];

    protected $casts = [
        'month_frequency' => 'integer',
    ];

    public function partner()  { return $this->belongsTo(Partner::class); }
    public function offering() { return $this->belongsTo(Offering::class); }
}
