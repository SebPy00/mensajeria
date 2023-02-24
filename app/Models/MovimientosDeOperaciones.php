<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MovimientosDeOperaciones extends Model
{
    use HasFactory;
    protected $table = 'opemov';
    protected $primaryKey = 'regnro';
}
