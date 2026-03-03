<?php

namespace Database\Seeders;

use App\Models\Material;
use Illuminate\Database\Seeder;

class MaterialSeeder extends Seeder
{
    public function run(): void
    {
        $materials = [
            [
                'name'           => 'PLA',
                'density_g_cm3'  => 1.240,
                'price_per_gram' => 2.0,
                'colors' => [
                    ['color_name' => 'Negro',    'hex_code' => '#1C1C1C', 'extra_cost' => 0.00],
                    ['color_name' => 'Blanco',   'hex_code' => '#F5F5F5', 'extra_cost' => 0.00],
                    ['color_name' => 'Rojo',     'hex_code' => '#DC2626', 'extra_cost' => 0.00],
                    ['color_name' => 'Azul',     'hex_code' => '#2563EB', 'extra_cost' => 0.00],
                    ['color_name' => 'Verde',    'hex_code' => '#16A34A', 'extra_cost' => 0.00],
                    ['color_name' => 'Amarillo', 'hex_code' => '#EAB308', 'extra_cost' => 0.00],
                    ['color_name' => 'Gris',     'hex_code' => '#9CA3AF', 'extra_cost' => 0.00],
                ],
            ],
            [
                'name'           => 'PETG',
                'density_g_cm3'  => 1.270,
                'price_per_gram' => 1.8,
                'colors' => [
                    ['color_name' => 'Negro',        'hex_code' => '#1C1C1C', 'extra_cost' => 0.00],
                    ['color_name' => 'Transparente', 'hex_code' => '#E0F2FE', 'extra_cost' => 5.00],
                    ['color_name' => 'Gris',         'hex_code' => '#9CA3AF', 'extra_cost' => 0.00],
                ],
            ],
            [
                'name'           => 'ABS',
                'density_g_cm3'  => 1.050,
                'price_per_gram' => 3.0,
                'colors' => [
                    ['color_name' => 'Negro', 'hex_code' => '#1C1C1C', 'extra_cost' => 0.00],
                    ['color_name' => 'Blanco', 'hex_code' => '#F5F5F5', 'extra_cost' => 0.00],
                    ['color_name' => 'Gris',   'hex_code' => '#9CA3AF', 'extra_cost' => 0.00],
                ],
            ],
            [
                'name'           => 'Resina Estándar',
                'density_g_cm3'  => 1.100,
                'price_per_gram' => 4.0,
                'colors' => [
                    ['color_name' => 'Clara', 'hex_code' => '#DBEAFE', 'extra_cost' => 0.00],
                    ['color_name' => 'Negra', 'hex_code' => '#1C1C1C', 'extra_cost' => 0.00],
                    ['color_name' => 'Gris',  'hex_code' => '#9CA3AF', 'extra_cost' => 0.00],
                ],
            ],
            [
                'name'           => 'TPU Flexible',
                'density_g_cm3'  => 1.210,
                'price_per_gram' => 2.5,
                'colors' => [
                    ['color_name' => 'Negro', 'hex_code' => '#1C1C1C', 'extra_cost' => 0.00],
                    ['color_name' => 'Blanco', 'hex_code' => '#F5F5F5', 'extra_cost' => 0.00],
                ],
            ],
        ];

        foreach ($materials as $data) {
            $colors = $data['colors'];
            unset($data['colors']);
            
            // Busca el material por su nombre. Si existe, actualiza densidad y precio. Si no, lo crea.
            $material = Material::updateOrCreate(
                ['name' => $data['name']],
                $data
            );

            // Elimina los colores existentes para evitar duplicados y luego inserta los nuevos
            $material->colors()->delete();
            $material->colors()->createMany($colors);
        }
    }
}