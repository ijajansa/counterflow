<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class CreatePlatformAdmin extends Command
{
    protected $signature = 'dukaan:admin';
    protected $description = 'Create a verified platform administrator using interactive credentials';

    public function handle(): int
    {
        $data = [
            'name' => $this->ask('Administrator name'),
            'email' => Str::lower((string) $this->ask('Administrator email')),
            'password' => $this->secret('Password (at least 12 characters)'),
            'password_confirmation' => $this->secret('Confirm password'),
        ];
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(12)],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) $this->error($message);
            return self::FAILURE;
        }
        DB::transaction(function () use ($data) {
            $user = new User(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
            $user->forceFill(['is_platform_admin' => true, 'email_verified_at' => now()])->save();
            app(AuditService::class)->record(null, $user, 'platform.admin_created', $user, ['source' => 'console']);
        });
        $this->info('Platform administrator created. Sign in at '.url('/login').'.');
        return self::SUCCESS;
    }
}
