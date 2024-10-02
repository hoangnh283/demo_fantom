<?php

namespace Hoangnh283\Fantom\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FantomAddress extends Model
{
    use HasFactory;

    protected $table = 'wallets_fantom_address';

    protected $fillable = ['address','private_key'];

    public function transactions()
    {
        return $this->hasMany(FantomTransactions::class);
    }

    public function deposits()
    {
        return $this->hasMany(FantomDeposit::class);
    }

    public function withdraws()
    {
        return $this->hasMany(FantomWithdraw::class);
    }

}