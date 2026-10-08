<?php

namespace App\Actions\Employees;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Adds an employee to the register together with their login account, and sends the welcome
 * mail. An existing account (e.g. made earlier under Users) can be linked instead; then no
 * new account or welcome mail is created.
 */
class RegisterEmployee
{
    public function __construct(protected SendWelcomeMail $sendWelcomeMail) {}

    /**
     * @param  array<string, mixed>  $data  employee fields plus the account fields login_email,
     *                                      role_id, teams, locale, or existing_user_id
     * @return array{employee: Employee, mailed: bool}
     */
    public function handle(array $data): array
    {
        $account = Arr::only($data, ['existing_user_id', 'login_email', 'role_id', 'teams', 'locale', 'job_title']);
        $fields = Arr::except($data, array_keys($account));

        [$employee, $isNewAccount] = DB::transaction(function () use ($account, $fields): array {
            if (filled($account['existing_user_id'] ?? null)) {
                $user = User::query()->findOrFail($account['existing_user_id']);
                $isNew = false;
            } else {
                $user = User::create([
                    'name' => trim($fields['first_name'].' '.$fields['last_name']),
                    'email' => strtolower(trim((string) $account['login_email'])),
                    // Unknown random password: the employee chooses their own through the welcome mail.
                    'password' => Str::password(40),
                    'role_id' => $account['role_id'] ?? Role::guest()?->id,
                    'job_title' => $account['job_title'] ?? null,
                    'phone' => $fields['private_phone'] ?? null,
                    'locale' => $account['locale'] ?? config('app.locale'),
                    'is_active' => true,
                ]);
                $isNew = true;
            }

            if (filled($account['teams'] ?? null) && modules()->teams()) {
                $user->teams()->syncWithoutDetaching($account['teams']);
                $user->flushTeamIds();
            }

            return [Employee::create([...$fields, 'user_id' => $user->id]), $isNew];
        });

        $mailed = $isNewAccount && $this->sendWelcomeMail->handle($employee);

        return ['employee' => $employee, 'mailed' => $mailed];
    }
}
