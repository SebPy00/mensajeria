<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Factura extends Model
{
    use HasFactory;
    protected $table = "fac";
    protected $primaryKey = "regnro";
    public $timestamps = false;
    public $incrementing = false;

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cli');
    }
}