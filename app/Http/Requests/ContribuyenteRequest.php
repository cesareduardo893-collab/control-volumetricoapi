<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContribuyenteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rfc' => 'required|string|max:13',
            'razon_social' => 'required|string',
            'nombre_comercial' => 'nullable|string',
            'codigo_postal' => 'nullable|string|max:5',
        ];
    }
}
