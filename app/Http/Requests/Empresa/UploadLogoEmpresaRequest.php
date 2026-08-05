<?php

namespace App\Http\Requests\Empresa;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Valida la subida del logo de la empresa activa del usuario.
 */
class UploadLogoEmpresaRequest extends FormRequest
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
            'logo' => 'required|image|mimes:jpg,jpeg,png,bmp,gif|max:2048',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'logo.required' => 'Debe seleccionar una imagen para el logo.',
            'logo.image'    => 'El logo debe ser un archivo de imagen.',
            'logo.mimes'    => 'El logo debe ser un archivo jpg, jpeg, png, bmp o gif.',
            'logo.max'      => 'El logo no puede superar los 2 MB.',
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
