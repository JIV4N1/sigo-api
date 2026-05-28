<?php

namespace App\Http\Requests\Asistencia;

use Illuminate\Foundation\Http\FormRequest;

class RegistrarEntradaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Autenticación manejada por Sanctum
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'latitud' => ['nullable', 'numeric', 'between:-90,90'],
            'longitud' => ['nullable', 'numeric', 'between:-180,180'],
            'proyecto_id' => ['nullable', 'exists:proyectos,id'],
        ];
    }
}
