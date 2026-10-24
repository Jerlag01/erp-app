<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WasteEntry;
use App\Models\WasteEntryLine;
use Illuminate\Support\Facades\Gate;

/**
 * The regional battery lists follow the waste registry: whoever sees the registry sees its lines.
 * Lines are changed through their entry.
 */
class WasteEntryLinePolicy
{
    public function viewAny(User $user): bool
    {
        return Gate::forUser($user)->allows('viewAny', WasteEntry::class);
    }

    public function view(User $user, WasteEntryLine $line): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, WasteEntryLine $line): bool
    {
        return false;
    }

    public function delete(User $user, WasteEntryLine $line): bool
    {
        return false;
    }
}
