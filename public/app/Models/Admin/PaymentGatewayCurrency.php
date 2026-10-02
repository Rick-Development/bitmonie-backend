<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentGatewayCurrency extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $callable_data;

    protected $appends = [];

    protected $casts = [
        'id'                        => 'integer',
        'payment_gateway_id'        => 'integer',
        'name'                      => 'string',
        'alias'                     => 'string',
        'currency_code'             => 'string',
        'currency_symbol'           => 'string',
        'image'                     => 'string',
        'min_limit'                 => 'decimal:16',
        'max_limit'                 => 'decimal:16',
        'percent_charge'            => 'decimal:16',
        'fixed_charge'              => 'decimal:16',
        'rate'                      => 'decimal:16',
        'created_at'                => 'date:Y-m-d',
        'updated_at'                => 'date:Y-m-d',
    ];

    /**
     * Get a subset of the model's attributes.
     *
     * @param  array|mixed  $attributes
     * @return array
     */
    public function getOnly($attributes)
    {
        $this->callable_data = $this->only($attributes);
        return $this;
    }

    public function makeJson() {
        return json_encode($this->callable_data);
    }

    public function gateway() {
        return $this->belongsTo(PaymentGateway::class,"payment_gateway_id");
    }

    public function getCryptoAttribute() {
        if($this->gateway->crypto == true) return true;
        return false;
    }

    public function getPaymentGatewayAliasAttribute()
    {
        return $this->gateway->alias;
    }
}
