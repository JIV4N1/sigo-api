<?php

namespace App\Http\Requests\Empresa;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Valida la actualización de la configuración de la empresa activa del usuario.
 * Nombres de campo alineados con el escritorio C# (nombre_empresa, correo).
 */
class UpdateConfiguracionEmpresaRequest extends FormRequest
{
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
            'nombre_empresa' => 'required|string|max:200',
            'rfc'            => 'nullable|string|max:20',
            'direccion'      => 'nullable|string|max:300',
            'telefono'       => 'nullable|string|max:20',
            'correo'         => 'nullable|email|max:100',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre_empresa.required' => 'El nombre de la empresa es obligatorio.',
            'nombre_empresa.max'      => 'El nombre de la empresa no puede superar los 200 caracteres.',
            'rfc.max'                 => 'El RFC no puede superar los 20 caracteres.',
            'direccion.max'           => 'La dirección no puede superar los 300 caracteres.',
            'telefono.max'            => 'El teléfono no puede superar los 20 caracteres.',
            'correo.email'            => 'El correo electrónico no tiene un formato válido.',
            'correo.max'              => 'El correo electrónico no puede superar los 100 caracteres.',
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
