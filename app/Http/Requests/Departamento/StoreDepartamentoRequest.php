<?php

namespace App\Http\Requests\Departamento;

use App\Http\Traits\AdminBypassTrait;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * Valida la creación de un departamento. El nombre debe ser único
 * dentro de la empresa activa del usuario autenticado.
 */
class StoreDepartamentoRequest extends FormRequest
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
            'nombre' => [
                'required',
                'string',
                'max:100',
                Rule::unique('departamentos', 'nombre')
                    ->where(fn ($query) => $query->where('empresa_id', $this->getEmpresaId($this))),
            ],
            'descripcion' => 'nullable|string',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del departamento es obligatorio.',
            'nombre.max'      => 'El nombre no puede superar los 100 caracteres.',
            'nombre.unique'   => 'Ya existe un departamento con ese nombre en tu empresa.',
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
                'message' => 'Error de validación',
                'errors'  => $validator->errors(),
            ], 422)
        );
    }
}
