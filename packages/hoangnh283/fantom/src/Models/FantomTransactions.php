<?php

namespace Hoangnh283\Fantom\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FantomTransactions extends Model
{
    use HasFactory;

    protected $table = 'wallets_fantom_transactions';

    protected $fillable = ['from_address', 'to_address','type', 'hash', 'gas', 'gas_price', 'fee', 'amount','nonce', 'status', 'block_number', 'currency'];

    public function address()
    {
        return $this->belongsTo(FantomAddress::class);
    }
    public function deposit()
    {
        return $this->hasOne(FantomDeposit::class, 'transaction_id');
    }

    public function withdraw()
    {
        return $this->hasOne(FantomWithdraw::class, 'transaction_id');
    }

}