<?php

namespace Hoangnh283\Fantom\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FantomWithdraw extends Model
{
    use HasFactory;

    protected $table = 'wallets_fantom_withdraw';

    protected $fillable = ['address_id', 'amount', 'currency', 'transaction_id'];

    public function address()
    {
        return $this->belongsTo(FantomAddress::class);
    }
    public function transaction()
    {
        return $this->belongsTo(FantomTransactions::class, 'transaction_id');
    }
}
