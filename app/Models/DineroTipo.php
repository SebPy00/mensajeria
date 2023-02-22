<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DineroTipo extends Model
{
    use HasFactory;
    protected $table = 'dintip';
    protected $primaryKey = "dintip";
    public $timestamps = false;
    public $incrementing = false;
}
