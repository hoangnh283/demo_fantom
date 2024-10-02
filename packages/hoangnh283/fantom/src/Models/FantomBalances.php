<?php

namespace Hoangnh283\Fantom\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FantomBalances extends Model
{
    use HasFactory;

    protected $table = 'wallets_fantom_balances';

    protected $fillable = ['address_id', 'ftm', 'usdt'];

    public function address()
    {
        return $this->belongsTo(FantomAddress::class);
    }

}