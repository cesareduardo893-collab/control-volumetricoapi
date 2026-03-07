<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegistroVolumetricoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'instalacion_id' => 'required|exists:instalaciones,id',
            'tanque_id' => 'required|exists:tanques,id',
            'producto_id' => 'required|exists:productos,id',
            'fecha' => 'required|date',
            'volumen_inicial' => 'required|numeric',
            'volumen_final' => 'required|numeric',
            'tipo_registro' => 'required|string',
        ];
    }
}
