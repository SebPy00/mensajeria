<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoPagoFE extends Model
{
    use HasFactory;
    protected $table = 'factura_electronica_forma_pago';
    public $timestamps = false;
}
