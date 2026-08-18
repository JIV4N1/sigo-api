<?php

namespace App\Http\Requests\Registro;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * Valida el auto-registro público de un nuevo usuario.
 *
 * El usuario elige una empresa y un departamento de los catálogos existentes;
 * el rol se asigna más adelante, al aprobarse la solicitud (ver
 * Superadmin\SolicitudesController::aprobar), a partir del rol configurado
 * en el departamento elegido.
 */
class RegistroRequest extends FormRequest
{
    /**
     * Ruta pública: cualquiera puede intentar registrarse.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombre'          => 'required|string|max:100',
            'email'           => 'required|email|max:100|unique:usuarios,email',
            'password'        => 'required|string|min:8|confirmed',
            'telefono'        => 'nullable|string|max:20',
            'empresa_id'      => [
                'required',
                'integer',
                Rule::exists('empresas', 'id')->where('activo', true),
            ],
            'departamento_id' => [
                'required',
                'integer',
                Rule::exists('departamentos', 'id')
                    ->where(fn ($query) => $query->where('empresa_id', $this->empresa_id)->where('activo', true)),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required'          => 'El nombre es obligatorio.',
            'nombre.max'               => 'El nombre no puede superar los 100 caracteres.',
            'email.required'           => 'El correo electrónico es obligatorio.',
            'email.email'              => 'El correo electrónico no tiene un formato válido.',
            'email.unique'             => 'Ya existe un usuario con ese correo electrónico.',
            'password.required'        => 'La contraseña es obligatoria.',
            'password.min'             => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed'       => 'La confirmación de contraseña no coincide.',
            'telefono.max'             => 'El teléfono no puede superar los 20 caracteres.',
            'empresa_id.required'      => 'Debes seleccionar una empresa.',
            'empresa_id.exists'        => 'La empresa seleccionada no existe o no está activa.',
            'departamento_id.required' => 'Debes seleccionar un departamento.',
            'departamento_id.exists'   => 'El departamento seleccionado no existe, no está activo o no pertenece a la empresa elegida.',
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
