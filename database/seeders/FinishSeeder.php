<?php

namespace Database\Seeders;

use App\Models\Finish;
use Illuminate\Database\Seeder;

class FinishSeeder extends Seeder
{
    public function run(): void
    {
        $finishes = [
            [
                'name'        => 'Sin acabado',
                'description' => 'La pieza se entrega tal como sale de la impresora.',
                'fixed_cost'  => 0.00,
            ],
            [
                'name'        => 'Lijado',
                'description' => 'Lijado manual para reducir las marcas de capa.',
                'fixed_cost'  => 30.00,
            ],
            [
                'name'        => 'Pintado',
                'description' => 'Lijado + pintura en aerosol del color indicado.',
                'fixed_cost'  => 80.00,
            ],
            [
                'name'        => 'Imprimación',
                'description' => 'Capa de imprimación para mejor adherencia de pintura.',
                'fixed_cost'  => 40.00,
            ],
        ];

        foreach ($finishes as $finish) {
            Finish::create($finish);
        }
    }
}