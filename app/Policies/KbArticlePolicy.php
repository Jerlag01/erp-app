<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Everyone may read the knowledge base (on the "Knowledge base" page); managing articles needs
 * the knowledge_base permissions. The resource therefore only shows up for editors.
 */
class KbArticlePolicy extends AreaPolicy
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
