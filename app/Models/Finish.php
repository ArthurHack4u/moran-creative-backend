<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Finish extends Model
{
    protected $fillable = ['name', 'description', 'fixed_cost'];

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
}