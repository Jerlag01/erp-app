<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The conversation on an idea or complaint: replies of administrators and of the employee who
 * submitted it, with text and photos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suggestion_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('suggestion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Kept as written, also when the account is removed later.
            $table->string('author_name', 150);
            $table->boolean('from_admin')->default(false);
            $table->text('body');
            $table->json('photos')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suggestion_responses');
    }
};
