<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GestionesRuralCobranzas extends Model
{
    use HasFactory;
    protected $connection = 'servicios';
    protected $table = 'rural_cobranzas.gestiones';
    public $timestamps = false;
}
