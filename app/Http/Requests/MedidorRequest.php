<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MedidorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'instalacion_id' => 'required|exists:instalaciones,id',
            'numero_serie' => 'required|string',
            'identificador' => 'required|string',
            'tipo_medidor' => 'required|string',
        ];
    }
}
