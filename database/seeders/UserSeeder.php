<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Seeder para el administrador
        User::updateOrCreate(
            ['email'    => 'admin@morancreative.com'], // Busca por este campo
            [
                'name'     => 'Moran Creative',
                'password' => Hash::make('Admin1234!'),
                'role'     => 'admin',
            ] // Actualiza o crea estos campos
        );

        // Seeder para el cliente
        User::updateOrCreate(
            ['email'    => 'carlos@ejemplo.com'], // Busca por este campo
            [
                'name'     => 'Carlos Mendoza',
                'password' => Hash::make('Cliente123!'),
                'role'     => 'client',
                'phone'    => '981 234 5678',
                'address'  => 'Calle 57 #123, Centro, Campeche',
            ] // Actualiza o crea estos campos
        );
    }
}