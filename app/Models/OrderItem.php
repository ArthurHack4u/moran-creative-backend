<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'material_id', 'color_id', 'finish_id',
        'piece_name', 'quantity', 'preferred_color', 'item_notes',
        'dim_x', 'dim_y', 'dim_z', 'infill_percent',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function material()
    {
        return $this->belongsTo(Material::class);
    }

    public function color()
    {
        return $this->belongsTo(MaterialColor::class, 'color_id');
    }

    public function finish()
    {
        return $this->belongsTo(Finish::class);
    }
}