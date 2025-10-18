<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketType extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'name',
        'category',
        'price',
        'quantity_total',
        'quantity_sold',
        'sales_start_at',
        'sales_end_at',
        'is_active',
    ];

    protected $casts = [
        'price'          => 'decimal:2',
        'quantity_total' => 'integer',
        'quantity_sold'  => 'integer',
        'sales_start_at' => 'datetime',
        'sales_end_at'   => 'datetime',
        'is_active'      => 'boolean',
    ];


    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }
}
