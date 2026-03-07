<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TanqueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'instalacion_id' => 'required|exists:instalaciones,id',
            'identificador' => 'required|string|max:255',
            'material' => 'required|string',
            'capacidad_total' => 'required|numeric',
        ];
    }
}
