<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResellerInvoice extends Model
{
    protected $guarded = [];

    protected $casts = [
        'period_started_at' => 'datetime',
        'period_ended_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items()
    {
        return $this->hasMany(ResellerInvoiceItem::class);
    }

    public function paidBy()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}
