<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Form Request para validar las credenciales del login.
 *
 * Si la validación falla, retorna automáticamente un JSON con
 * el formato de error estándar del sistema SIGO (status, message, errors).
 */
class LoginRequest extends FormRequest
{
    /**
     * Indica que cualquier usuario puede intentar autenticarse
     * (ruta pública, sin middleware auth).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación para el login.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Mensajes de error personalizados en español.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required'    => 'El campo email es obligatorio.',
            'email.email'       => 'El email no tiene un formato válido.',
            'password.required' => 'El campo contraseña es obligatorio.',
            'password.string'   => 'La contraseña debe ser una cadena de texto.',
        ];
    }

    /**
     * Sobreescribe el comportamiento al fallar la validación.
     * Devuelve JSON con el formato estándar en lugar de redirigir.
     */
    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            response()->json([
                'status'  => 'error',
                'message' => 'Los datos proporcionados no son válidos.',
                'errors'  => $validator->errors(),
            ], 422)
        );
    }
}
