<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderFile extends Model
{
    protected $fillable = [
        'order_id', 'original_name', 'stored_name', 'path', 'mime_type', 'size_bytes'
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}