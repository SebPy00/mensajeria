<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GestionesDelaOperacion extends Model
{
    use HasFactory;
    protected $table = 'opeges';
    protected $primaryKey = 'regnro';
    public $timestamps = false;
    public $incrementing = false;
    
}
