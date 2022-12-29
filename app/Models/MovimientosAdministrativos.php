<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MovimientosAdministrativos extends Model
{
    use HasFactory;
    protected $table = 'admmov';
    protected $primaryKey = 'regnro';
}
