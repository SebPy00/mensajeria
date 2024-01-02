<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FECodigoSeguridad extends Model
{
    use HasFactory;
    protected $table = 'fact_electronica_cod';
    public $timestamps = false;
}
