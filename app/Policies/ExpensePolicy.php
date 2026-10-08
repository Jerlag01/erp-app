<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Incoming invoices follow the "expenses" permissions. Only pending ones can be edited or
 * deleted; "expenses.validate" accepts or rejects them.
 */
class ExpensePolicy extends AreaPolicy
{
    protected function area(): string
    {
        return 'expenses';
    }

    public function update(User $user, Model $expense): bool
    {
        return $expense->isPending() && parent::update($user, $expense);
    }

    public function delete(User $user, Model $expense): bool
    {
        return $expense->isPending() && parent::delete($user, $expense);
    }

    public function validate(User $user, Expense $expense): bool
    {
        return $expense->isPending() && $this->allows($user, 'validate');
    }
}
