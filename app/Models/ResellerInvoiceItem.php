<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResellerInvoiceItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'order_date' => 'datetime',
    ];

    public function invoice()
    {
        return $this->belongsTo(ResellerInvoice::class, 'reseller_invoice_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
