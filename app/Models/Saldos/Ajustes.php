<?php

namespace App\Models\Saldos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use DateTimeInterface;
use Log;


class Ajustes extends Model
{
    use HasFactory;

    protected $fillable = ['factura_id', 'fecha_deposito', 'importe', 'comentarios', 'user_id', 'nota_de', 'empresa_id'];


    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format("Y-m-d H:i:s");
    }

    public function scopeJoinUsuario($query) {
      Log::info(__CLASS__." ".__FUNCTION__);
      return $query->join('users', 'users.id', '=', 'user_id');
    }

    public function scopeJoinEmpresa($query) {
      Log::info(__CLASS__." ".__FUNCTION__);
      return $query->join('empresas', 'empresas.id', '=', 'ajustes.empresa_id');
    }


}
