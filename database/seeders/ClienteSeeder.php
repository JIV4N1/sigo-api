<?php

namespace Database\Seeders;

use App\Models\Cliente;
use Illuminate\Database\Seeder;

class ClienteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $clientes = [
            [
                'razon_social' => 'Constructora ABC S.A.',
                'rfc' => 'ABC-2026-XXX',
                'nombre_contacto' => 'Ing. Roberto Sánchez',
                'telefono' => null,
                'email' => null,
                'direccion' => null,
                'activo' => true,
            ],
            [
                'razon_social' => 'Desarrolladora XYZ S.A.',
                'rfc' => 'XYZ-2026-XXX',
                'nombre_contacto' => 'Lic. María López',
                'telefono' => null,
                'email' => null,
                'direccion' => null,
                'activo' => true,
            ],
            [
                'razon_social' => 'Inmobiliaria del Centro S.A.',
                'rfc' => 'IDC-2026-XXX',
                'nombre_contacto' => 'Arq. Juan Pérez',
                'telefono' => null,
                'email' => null,
                'direccion' => null,
                'activo' => true,
            ],
        ];

        foreach ($clientes as $cliente) {
            Cliente::create($cliente);
        }
    }
}
