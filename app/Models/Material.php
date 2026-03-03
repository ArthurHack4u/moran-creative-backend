<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Material extends Model
{
    protected $fillable = ['name', 'density_g_cm3', 'price_per_gram', 'active'];
    protected $casts    = ['active' => 'boolean'];

    public function colors()
    {
        return $this->hasMany(MaterialColor::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}