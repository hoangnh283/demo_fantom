<?php

namespace Hoangnh283\Fantom\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CurrentBlock extends Model
{
    use HasFactory;

    protected $table = 'current_block';
    public $timestamps = false;
    protected $fillable = ['block_number'];

}