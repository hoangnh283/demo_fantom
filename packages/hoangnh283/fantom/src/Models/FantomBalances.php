<?php

namespace Hoangnh283\Fantom\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FantomBalances extends Model
{
    use HasFactory;

    protected $table = 'wallets';

    protected $fillable = ['address_id', 'currency', 'amount'];

    public function address()
    {
        return $this->belongsTo(FantomAddress::class);
    }

}