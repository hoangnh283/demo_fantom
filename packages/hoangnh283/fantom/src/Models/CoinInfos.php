<?php

namespace Hoangnh283\Fantom\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CoinInfos extends Model
{
    use HasFactory;

    protected $table = 'coin_infos';

    protected $fillable = ['name','address','network'];

}