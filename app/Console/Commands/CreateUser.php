<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

#[Signature('erp:create-user {--admin : Give the user the administrator role}')]
#[Description('Create a user, e.g. the first administrator')]
class CreateUser extends Command
{
    public function handle(): int
    {
        $name = text('Name', required: true);
        $email = text('Email', required: true, validate: fn (string $value): ?string => Validator::make(
            ['email' => $value],
            ['email' => ['email', 'unique:users,email']],
        )->errors()->first('email') ?: null);
        $password = password('Password', required: true, validate: fn (string $value): ?string => Validator::make(
            ['password' => $value],
            ['password' => [Password::defaults()]],
        )->errors()->first('password') ?: null);

        $role = $this->option('admin')
            ? Role::where('is_admin', true)->first() ?? Role::create(['name' => 'Beheerder', 'is_admin' => true])
            : Role::find(select('Role', Role::orderBy('name')->pluck('name', 'id')->all()));

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role_id' => $role->id,
            'locale' => config('app.locale'),
            'is_active' => true,
        ]);

        $this->components->info("User {$user->email} created with role {$role->name}.");

        return self::SUCCESS;
    }
}
