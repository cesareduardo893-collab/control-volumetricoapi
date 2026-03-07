<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExistenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tanque_id' => 'required|exists:tanques,id',
            'producto_id' => 'required|exists:productos,id',
            'fecha' => 'required|date',
            'volumen_existente' => 'required|numeric',
            'tipo_movimiento' => 'required|string',
        ];
    }
}
