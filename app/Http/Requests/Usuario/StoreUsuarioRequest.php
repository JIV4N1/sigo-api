<?php

namespace App\Http\Requests\Usuario;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Valida los datos para crear un nuevo usuario.
 *
 * El empresa_id se asigna en el controlador a partir del usuario autenticado,
 * nunca se acepta desde el body. La verificación de rol (administrador)
 * se hace en el controlador.
 */
class StoreUsuarioRequest extends FormRequest
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
            'nombre'   => 'required|string|max:100',
            'email'    => 'required|email|max:100|unique:usuarios,email',
            'password' => 'required|string|min:8',
            'telefono' => 'nullable|string|max:20',
            'rol_id'   => 'required|integer|exists:roles,id',
            'activo'   => 'sometimes|boolean',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required'   => 'El nombre es obligatorio.',
            'nombre.max'        => 'El nombre no puede superar los 100 caracteres.',
            'email.required'    => 'El correo electrónico es obligatorio.',
            'email.email'       => 'El correo electrónico no tiene un formato válido.',
            'email.unique'      => 'Ya existe un usuario con ese correo electrónico.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min'      => 'La contraseña debe tener al menos 8 caracteres.',
            'telefono.max'      => 'El teléfono no puede superar los 20 caracteres.',
            'rol_id.required'   => 'El rol es obligatorio.',
            'rol_id.exists'     => 'El rol seleccionado no existe.',
            'activo.boolean'    => 'El campo activo debe ser verdadero o falso.',
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