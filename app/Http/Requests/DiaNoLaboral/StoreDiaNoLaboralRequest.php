<?php

namespace App\Http\Requests\DiaNoLaboral;

use App\Http\Traits\AdminBypassTrait;
use App\Models\DiaNoLaboral;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * Valida los datos para crear un día no laboral (festivo/vacaciones/descanso).
 * La fecha debe ser única dentro de la empresa activa del usuario autenticado.
 */
class StoreDiaNoLaboralRequest extends FormRequest
{
    use AdminBypassTrait;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fecha'       => [
                'required',
                'date',
                Rule::unique('dias_no_laborales', 'fecha')
                    ->where(fn ($query) => $query->where('empresa_id', $this->getEmpresaId($this))),
            ],
            'descripcion' => 'nullable|string|max:150',
            'tipo'        => 'required|string|in:' . implode(',', DiaNoLaboral::TIPOS),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fecha.required' => 'La fecha es obligatoria.',
            'fecha.date'     => 'La fecha no tiene un formato válido.',
            'fecha.unique'   => 'Ya existe un día no laboral registrado en esa fecha.',
            'descripcion.max' => 'La descripción no puede superar los 150 caracteres.',
            'tipo.required'  => 'El tipo es obligatorio.',
            'tipo.in'        => 'El tipo debe ser uno de: ' . implode(', ', DiaNoLaboral::TIPOS) . '.',
        ];
    }

    /**
     * Retorna errores de validación en el formato estándar de la API SIGO.
     */
    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            response()->json([
                'status'  => 'error',
                'message' => 'Error de validación.',
                'errors'  => $validator->errors(),
            ], 422)
        );
    }
}
