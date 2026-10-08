<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Knowledge base categories are managed with the same knowledge_base permissions as articles.
 */
class KbCategoryPolicy extends AreaPolicy
{
    protected function area(): string
    {
        return 'knowledge_base';
    }

    public function viewAny(User $user): bool
    {
        return $user->canEditKnowledgeBase();
    }

    public function view(User $user, Model $model): bool
    {
        return $user->canEditKnowledgeBase();
    }
}
