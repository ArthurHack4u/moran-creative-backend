<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaterialColor extends Model
{
    protected $fillable = ['material_id', 'color_name', 'hex_code', 'extra_cost'];

    public function material()
    {
        return $this->belongsTo(Material::class);
    }
}