<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Security tags can be unassigned, or belong to a user with a login who is not in the employee
     * register (e.g. a cleaner or an external), and can be marked lost.
     */
    public function up(): void
    {
        Schema::table('security_tags', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable()->change();
            $table->foreignId('user_id')->nullable()->unique()->after('employee_id')->constrained()->nullOnDelete();
            $table->boolean('is_lost')->default(false)->after('door_keys');
            $table->timestamp('lost_at')->nullable()->after('is_lost');
        });
    }

    public function down(): void
    {
        Schema::table('security_tags', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['is_lost', 'lost_at']);
        });
    }
};
