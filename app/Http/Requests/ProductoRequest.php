<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'clave_sat' => 'required|string|max:10',
            'codigo' => 'required|string|max:20',
            'nombre' => 'required|string',
        ];
    }
}
