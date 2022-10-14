<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CodigoEmpresa extends Model
{
    use HasFactory;

    protected $primaryKey = 'codemp';
    protected $table = 'codemp';

    public function mails() {

        return $this->hasMany(EmpresaMail::class, 'codemp', 'codemp');
    }
}
