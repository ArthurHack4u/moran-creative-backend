<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'user_id', 'status', 'notes', 'end_use', 'deadline',
        'quoted_price', 'admin_notes', 'quoted_at', 'responded_at',
    ];

    protected $casts = [
        'deadline'     => 'date',
        'quoted_at'    => 'datetime',
        'responded_at' => 'datetime',
    ];

    public function getTicketAttribute(): string
    {
        return 'MC-' . str_pad($this->id, 4, '0', STR_PAD_LEFT);
    }

    public function canBeRespondedBy(User $user): bool
    {
        return !$user->isAdmin()
            && $this->user_id === $user->id
            && $this->status === 'cotizado';
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function files()
    {
        return $this->hasMany(OrderFile::class);
    }

    public function statusHistory()
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at', 'desc');
    }
}