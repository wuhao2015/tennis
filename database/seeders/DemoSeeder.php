<?php

namespace Database\Seeders;

use App\Models\Studio;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            '青松网球工作室' => [['lin', '林教练', true, true], ['zhou', '周教练', false, true]],
            '湖畔网球工作室' => [['chen', '陈教练', true, true]],
        ] as $name => $staff) {
            $studio = Studio::firstOrCreate(['name' => $name]);
            foreach ($staff as [$login, $nickname, $admin, $coach]) {
                $user = User::firstOrCreate(['email' => $login.'@example.test'], [
                    'nickname' => $nickname, 'password' => 'tennis-demo',
                ]);
                $studio->memberships()->firstOrCreate(['user_id' => $user->id], [
                    'status' => 'approved', 'is_admin' => $admin, 'is_coach' => $coach,
                ]);
            }
        }
    }
}
