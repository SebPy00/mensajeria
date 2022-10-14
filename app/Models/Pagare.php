<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pagare extends Model
{
    use HasFactory;
    protected $table = 'pagare';
    protected $primaryKey = 'regnro';
}
