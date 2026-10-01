<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GraphWallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'graph_customer_id',
        'wallet_id',
        'account_number',
        'currency',
        'balance',
        'status',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
        'balance' => 'decimal:8',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function customer()
    {
        return $this->belongsTo(GraphCustomer::class, 'graph_customer_id');
    }
}
