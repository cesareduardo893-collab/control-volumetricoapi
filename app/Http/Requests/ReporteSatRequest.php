<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReporteSatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'instalacion_id' => 'required|exists:instalaciones,id',
            'folio' => 'required|string',
            'periodo' => 'required|string',
        ];
    }
}
