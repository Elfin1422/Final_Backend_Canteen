<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::create(['name' => 'Admin User',    'email' => 'admin@canteen.com',    'password' => Hash::make('password'), 'role' => 'admin']);
        User::create(['name' => 'Cashier One',   'email' => 'cashier@canteen.com',  'password' => Hash::make('password'), 'role' => 'cashier']);
        User::create(['name' => 'Juan dela Cruz','email' => 'customer@canteen.com', 'password' => Hash::make('password'), 'role' => 'customer']);
    }
}
