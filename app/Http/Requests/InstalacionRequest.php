<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InstalacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contribuyente_id' => 'required|exists:contribuyentes,id',
            'clave_instalacion' => 'required|string|max:255',
            'nombre' => 'required|string',
        ];
    }
}
