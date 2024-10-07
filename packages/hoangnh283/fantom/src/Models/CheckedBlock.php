<?php

namespace Hoangnh283\Fantom\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CheckedBlock extends Model
{
    use HasFactory;

    protected $table = 'checked_blocks';
    protected $fillable = ['block_number'];

}