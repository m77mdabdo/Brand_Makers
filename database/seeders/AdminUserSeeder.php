<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    private const FALLBACK_EMAIL = 'admin@example.test';

    private const FALLBACK_PASSWORD = 'Xk9#mPz2Qw7!';

    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        // The placeholder credentials below are published in this file and
        // in the repository, so they must never reach a real environment.
        // Outside local/testing, missing values are a hard failure rather
        // than a warning someone scrolls past during a deploy.
        if (! app()->environment(['local', 'testing']) && (! $email || ! $password)) {
            $missing = implode(' and ', array_filter([
                $email ? null : 'ADMIN_EMAIL',
                $password ? null : 'ADMIN_PASSWORD',
            ]));

            throw new RuntimeException(
                "{$missing} must be set in .env before seeding in the '".app()->environment()."' environment. "
                .'Refusing to fall back to the placeholder dev credentials.'
            );
        }

        if (! $email) {
            $email = self::FALLBACK_EMAIL;
            $this->command?->warn("ADMIN_EMAIL not set in .env — using fallback dev email: {$email}");
        }

        if (! $password) {
            $password = self::FALLBACK_PASSWORD;
            $this->command?->warn('ADMIN_PASSWORD not set in .env — using the published placeholder dev password (set ADMIN_PASSWORD in .env).');
        }

        $admin = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Admin',
                'password' => Hash::make($password),
                'locale' => 'ar',
            ]
        );

        // is_admin is guarded, so it cannot travel through updateOrCreate.
        $admin->is_admin = true;
        $admin->save();
    }
}
